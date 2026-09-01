<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Models\Server;
use Azuriom\Models\ServerCommand;
use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncTarget;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Azuriom\Plugin\SkinSystem\Services\SkinProcessor;
use Azuriom\Plugin\SkinSystem\Services\SkinsRestorerCommandBuilder;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Services\SkinSynchronizer;
use Azuriom\Plugin\SkinSystem\Services\SkinSyncTargetRegistry;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Support\SyncResult;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\ConfigurableSkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\RecordingServerBridge;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\DataProvider;

class SkinSynchronizationTest extends TestCase
{
    public function test_username_mode_is_snapshotted_for_set_and_clear_commands(): void
    {
        $user = $this->createUser(name: 'Player_123');
        $server = $this->createServer();
        $settings = $this->settings($server);
        $settings->selectedApplicationTarget = SkinSystemSettings::TARGET_USERNAME;
        $stored = $this->manager($settings)->store(
            $user,
            $this->uploadedSkin(60, 120, 180),
            Skin::VARIANT_CLASSIC,
        );
        $skin = $stored['skin'];
        $state = SkinSyncState::query()->sole();

        $this->assertSame(SkinSystemSettings::TARGET_USERNAME, $state->target_type);
        $this->assertSame('Player_123', $state->target_value);

        $set = $this->synchronizer($settings)->apply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $set->status);
        $this->assertStringEndsWith(
            ' Player_123 classic',
            ServerCommand::query()->findOrFail($state->fresh()->queued_command_id)->command,
        );

        $clearState = $this->manager($settings)->delete($user);
        $clear = $this->synchronizer($settings)->clear($user, $clearState?->skin_revision);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $clear->status);
        $this->assertTrue(
            ServerCommand::query()->where('command', 'skin clear Player_123')->exists(),
        );
    }

    public function test_azlink_queues_the_exact_command_and_replaces_only_its_owned_row(): void
    {
        $user = $this->createUser('123456781234423482341234567890AB');
        $server = $this->createServer();
        [$skin, $state] = $this->createActiveSkin($user, $server);
        $owned = $server->commands()->create([
            'user_id' => $user->id,
            'need_online' => false,
            'command' => 'skin set "https://skins.example.com/obsolete.png" '.self::PRIMARY_UUID.' classic',
        ]);
        $unrelated = $server->commands()->create([
            'user_id' => $user->id,
            'need_online' => false,
            'command' => 'skin clear '.self::SECONDARY_UUID,
        ]);
        $state->update(['queued_command_id' => $owned->id]);
        $settings = $this->settings($server);

        $result = $this->synchronizer($settings)->apply($skin, $user);

        $expected = 'skin set "https://skins.example.com/api/skinsystem/skins/'
            .$user->id.'/1-'.str_repeat('a', 64).'.png" '.self::PRIMARY_UUID.' classic';
        $freshState = $state->fresh();

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $result->status);
        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $freshState->status);
        $this->assertSame(1, SkinSyncTarget::query()->count());
        $this->assertSame(
            SkinSyncTarget::STATUS_POSSIBLE_ACTIVE,
            SkinSyncTarget::query()->sole()->status,
        );
        $this->assertNotNull($freshState->dispatched_at);
        $this->assertFalse(ServerCommand::query()->whereKey($owned)->exists());
        $this->assertTrue(ServerCommand::query()->whereKey($unrelated)->exists());
        $this->assertSame($expected, ServerCommand::query()->findOrFail($freshState->queued_command_id)->command);
        $this->assertSame(1, RecordingServerBridge::$notifications);
        $this->assertSame([], RecordingServerBridge::$calls);
    }

    public function test_an_azlink_notification_failure_keeps_the_durable_command_submitted(): void
    {
        $user = $this->createUser();
        $server = $this->createServer();
        [$skin, $state] = $this->createActiveSkin($user, $server);
        RecordingServerBridge::$throwOnNotification = true;

        $result = $this->synchronizer($this->settings($server))->apply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $result->status);
        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $state->fresh()->status);
        $this->assertNotNull($state->fresh()->queued_command_id);
        $this->assertSame(1, ServerCommand::query()->count());
    }

    public function test_azlink_no_ping_still_queues_without_an_outbound_notification(): void
    {
        $user = $this->createUser();
        $server = $this->createServer();
        $server->update(['data' => ['azlink-ping' => false]]);
        [$skin, $state] = $this->createActiveSkin($user, $server);

        $result = $this->synchronizer($this->settings($server))->apply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $result->status);
        $this->assertNotNull($state->fresh()->queued_command_id);
        $this->assertSame(0, RecordingServerBridge::$notifications);
    }

    public function test_rcon_failure_after_dispatch_is_uncertain_and_retry_is_manual(): void
    {
        $user = $this->createUser();
        $server = $this->createServer('mc-rcon');
        [$skin, $state] = $this->createActiveSkin($user, $server);
        SkinSyncTarget::query()->delete();
        $synchronizer = $this->synchronizer($this->settings($server));
        RecordingServerBridge::$throwAfterRecording = true;

        $first = $synchronizer->apply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_UNCERTAIN, $first->status);
        $this->assertSame('dispatch_exception', $first->error);
        $this->assertSame(SkinSyncState::STATUS_UNCERTAIN, $state->fresh()->status);
        $this->assertSame(1, SkinSyncTarget::query()->count());
        $this->assertCount(1, RecordingServerBridge::$calls);

        RecordingServerBridge::$throwAfterRecording = false;
        $second = $synchronizer->apply($skin, $user);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $second->status);
        $this->assertCount(2, RecordingServerBridge::$calls);
        $this->assertFalse(RecordingServerBridge::$calls[1]['require_online']);
    }

    #[DataProvider('azLinkSetFetchStates')]
    public function test_delete_clears_every_possible_target_around_an_azlink_fetch(
        bool $newSetWasFetched,
    ): void {
        $user = $this->createUser();
        $originalServer = $this->createServer(name: 'Original AzLink');
        $newServer = $this->createServer(name: 'New AzLink');
        [$originalSkin, $state] = $this->createActiveSkin($user, $originalServer);
        $originalSettings = $this->settings($originalServer);

        $this->synchronizer($originalSettings)->apply($originalSkin, $user);
        ServerCommand::query()->whereKey($state->fresh()->queued_command_id)->delete();

        $user->update(['game_id' => self::SECONDARY_UUID]);
        $newSettings = $this->settings($newServer);
        $manager = $this->manager($newSettings);
        $replacement = $manager->store(
            $user->fresh(),
            $this->uploadedSkin(30, 120, 210),
            Skin::VARIANT_CLASSIC,
        );

        $this->assertTrue($replacement['changed']);
        $this->assertSame(2, SkinSyncTarget::query()->count());

        $this->synchronizer($newSettings)->apply($replacement['skin'], $user->fresh());
        $newSetCommandId = $state->fresh()->queued_command_id;
        $unrelated = $newServer->commands()->create([
            'user_id' => $user->id,
            'need_online' => false,
            'command' => 'say this command does not belong to SkinSystem',
        ]);

        if ($newSetWasFetched) {
            ServerCommand::query()->whereKey($newSetCommandId)->delete();
        }

        $clearState = $manager->delete($user->fresh());

        $this->assertNotNull($clearState);
        $this->assertFalse(ServerCommand::query()->whereKey($newSetCommandId)->exists());
        $this->assertTrue(ServerCommand::query()->whereKey($unrelated)->exists());
        $this->assertSame(
            [self::PRIMARY_UUID, self::SECONDARY_UUID],
            SkinSyncTarget::query()->orderBy('id')->pluck('target_uuid')->all(),
        );
        $this->assertSame(
            [SkinSyncTarget::STATUS_CLEAR_PENDING, SkinSyncTarget::STATUS_CLEAR_PENDING],
            SkinSyncTarget::query()->orderBy('id')->pluck('status')->all(),
        );

        $result = $this->synchronizer($newSettings)->clear(
            $user->fresh(),
            $clearState->skin_revision,
        );

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $result->status);
        $this->assertTrue(ServerCommand::query()->whereKey($unrelated)->exists());
        $this->assertEqualsCanonicalizing(
            [
                'say this command does not belong to SkinSystem',
                'skin clear '.self::PRIMARY_UUID,
                'skin clear '.self::SECONDARY_UUID,
            ],
            ServerCommand::query()->pluck('command')->all(),
        );

        foreach (SkinSyncTarget::query()->get() as $target) {
            $queuedClear = ServerCommand::query()->findOrFail($target->queued_clear_command_id);

            $this->assertSame(SkinSyncTarget::STATUS_CLEAR_SUBMITTED, $target->status);
            $this->assertSame(2, $target->clear_revision);
            $this->assertSame($target->target_server_id, $queuedClear->server_id);
            $this->assertSame('skin clear '.$target->target_uuid, $queuedClear->command);
        }
    }

    public function test_clear_reports_partial_failure_and_retains_the_unavailable_target(): void
    {
        $user = $this->createUser();
        $availableServer = $this->createServer('mc-rcon', 'Available server');
        $unavailableServer = $this->createServer('mc-rcon', 'Unavailable server');
        [, $state] = $this->createActiveSkin($user, $availableServer);
        SkinSyncTarget::create([
            'user_id' => $user->id,
            'target_uuid' => self::SECONDARY_UUID,
            'target_server_id' => $unavailableServer->id,
            'status' => SkinSyncTarget::STATUS_POSSIBLE_ACTIVE,
        ]);
        $settings = $this->settings($availableServer);
        $clearState = $this->manager($settings)->delete($user);
        $unavailableServer->delete();

        $result = $this->synchronizer($settings)->clear($user, $clearState?->skin_revision);

        $this->assertSame(SkinSyncState::STATUS_FAILED, $result->status);
        $this->assertSame('server_unavailable', $result->error);
        $this->assertSame(SkinSyncState::STATUS_FAILED, $state->fresh()->status);
        $this->assertCount(1, RecordingServerBridge::$calls);
        $this->assertSame($availableServer->id, RecordingServerBridge::$calls[0]['server_id']);
        $this->assertSame(
            SkinSyncTarget::STATUS_CLEAR_SUBMITTED,
            SkinSyncTarget::query()->where('target_server_id', $availableServer->id)->sole()->status,
        );
        $failedTarget = SkinSyncTarget::query()
            ->where('target_server_id', $unavailableServer->id)
            ->sole();
        $this->assertSame(SkinSyncTarget::STATUS_CLEAR_FAILED, $failedTarget->status);
        $this->assertSame('server_unavailable', $failedTarget->error);
    }

    #[DataProvider('azLinkClearFetchStates')]
    public function test_reupload_preserves_an_unacknowledged_clear_risk(
        bool $currentTargetsClearWasFetched,
    ): void {
        $user = $this->createUser();
        $server = $this->createServer();
        $otherServer = $this->createServer(name: 'Other AzLink target');
        [, $state] = $this->createActiveSkin($user, $server);
        SkinSyncTarget::create([
            'user_id' => $user->id,
            'target_uuid' => self::SECONDARY_UUID,
            'target_server_id' => $otherServer->id,
            'status' => SkinSyncTarget::STATUS_POSSIBLE_ACTIVE,
        ]);
        $settings = $this->settings($server);
        $manager = $this->manager($settings);
        $clearState = $manager->delete($user);

        $this->synchronizer($settings)->clear($user, $clearState?->skin_revision);
        $target = SkinSyncTarget::query()
            ->where('target_server_id', $server->id)
            ->sole();
        $otherTarget = SkinSyncTarget::query()
            ->where('target_server_id', $otherServer->id)
            ->sole();
        $clearCommandId = $target->queued_clear_command_id;
        $otherClearCommandId = $otherTarget->queued_clear_command_id;
        $unrelated = $server->commands()->create([
            'user_id' => $user->id,
            'need_online' => false,
            'command' => 'say keep this unrelated command',
        ]);

        if ($currentTargetsClearWasFetched) {
            ServerCommand::query()->whereKey($clearCommandId)->delete();
        }

        $replacement = $manager->store(
            $user,
            $this->uploadedSkin(200, 80, 40),
            Skin::VARIANT_CLASSIC,
        );

        $this->assertTrue($replacement['changed']);
        $this->assertFalse(ServerCommand::query()->whereKey($clearCommandId)->exists());
        $this->assertTrue(ServerCommand::query()->whereKey($otherClearCommandId)->exists());
        $this->assertTrue(ServerCommand::query()->whereKey($unrelated)->exists());
        $this->assertSame(SkinSyncState::ACTION_SET, $state->fresh()->action);
        $this->assertSame(2, $state->fresh()->skin_revision);
        $this->assertSame(SkinSyncTarget::STATUS_POSSIBLE_ACTIVE, $target->fresh()->status);
        $this->assertNull($target->fresh()->clear_revision);
        $this->assertNull($target->fresh()->queued_clear_command_id);
        $this->assertTrue($target->fresh()->clear_may_be_in_flight);
        $this->assertSame(
            SkinSyncTarget::STATUS_CLEAR_SUBMITTED,
            $otherTarget->fresh()->status,
        );
        $this->assertSame($otherClearCommandId, $otherTarget->fresh()->queued_clear_command_id);

        $setResult = $this->synchronizer($settings)->apply($replacement['skin'], $user);

        $this->assertSame(SkinSyncState::STATUS_UNCERTAIN, $setResult->status);
        $this->assertSame('clear_may_be_in_flight', $setResult->error);
        $this->assertSame(SkinSyncState::STATUS_UNCERTAIN, $state->fresh()->status);
        $this->assertSame('clear_may_be_in_flight', $state->fresh()->error);
        $this->assertNotNull($state->fresh()->queued_command_id);
        $this->assertTrue(ServerCommand::query()->whereKey($otherClearCommandId)->exists());
        $this->assertTrue(ServerCommand::query()->whereKey($unrelated)->exists());

        $staleClear = $this->synchronizer($settings)->clear($user, $clearState?->skin_revision);
        $this->assertSame(SyncResult::STALE, $staleClear->status);
        $this->assertSame('stale_revision', $staleClear->error);
        $this->assertSame(SkinSyncTarget::STATUS_POSSIBLE_ACTIVE, $target->fresh()->status);
    }

    public function test_clear_retries_the_recorded_uuid_and_server_snapshot(): void
    {
        $user = $this->createUser();
        $originalServer = $this->createServer('mc-rcon', 'Original server');
        $newServer = $this->createServer('mc-rcon', 'New server');
        [$skin, $state] = $this->createActiveSkin($user, $originalServer);
        $state->update(['status' => SkinSyncState::STATUS_SUBMITTED]);
        $user->update(['game_id' => self::SECONDARY_UUID]);
        $settings = $this->settings($newServer);
        $manager = new ManageSkin(
            app(SkinProcessor::class),
            app(SkinStorage::class),
            $settings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );

        $clearState = $manager->delete($user->fresh());

        $this->assertNotNull($clearState);
        $this->assertFalse(Skin::query()->whereKey($skin)->exists());
        $this->assertSame(self::PRIMARY_UUID, $clearState->target_uuid);
        $this->assertSame($originalServer->id, $clearState->target_server_id);

        RecordingServerBridge::$throwAfterRecording = true;
        $synchronizer = $this->synchronizer($settings);
        $first = $synchronizer->clear($user->fresh(), $clearState->skin_revision);

        $this->assertSame(SkinSyncState::STATUS_UNCERTAIN, $first->status);
        $this->assertSame($originalServer->id, RecordingServerBridge::$calls[0]['server_id']);
        $this->assertSame(['skin clear '.self::PRIMARY_UUID], RecordingServerBridge::$calls[0]['commands']);

        RecordingServerBridge::$throwAfterRecording = false;
        $second = $synchronizer->clear($user->fresh(), $clearState->skin_revision);

        $this->assertSame(SkinSyncState::STATUS_SUBMITTED, $second->status);
        $this->assertSame($originalServer->id, RecordingServerBridge::$calls[1]['server_id']);
        $this->assertSame(['skin clear '.self::PRIMARY_UUID], RecordingServerBridge::$calls[1]['commands']);
    }

    public function test_disabled_missing_and_invalid_targets_fail_without_dispatch(): void
    {
        $user = $this->createUser('not-a-uuid');
        $server = $this->createServer('mc-rcon');
        [$skin, $state] = $this->createActiveSkin($user, $server, targetUuid: null);
        $settings = $this->settings($server);
        $settings->syncEnabled = false;
        $synchronizer = $this->synchronizer($settings);

        $disabled = $synchronizer->apply($skin, $user);
        $this->assertSame(SkinSyncState::STATUS_NOT_CONFIGURED, $disabled->status);

        $settings->syncEnabled = true;
        $invalid = $synchronizer->apply($skin, $user);
        $this->assertSame(SkinSyncState::STATUS_FAILED, $invalid->status);
        $this->assertSame('invalid_game_id', $invalid->error);

        $user->update(['game_id' => self::PRIMARY_UUID]);
        $state->update([
            'target_uuid' => self::PRIMARY_UUID,
            'target_server_id' => 4294967295,
            'status' => SkinSyncState::STATUS_PENDING,
            'error' => null,
        ]);
        $missing = $synchronizer->apply($skin, $user->fresh());

        $this->assertSame(SkinSyncState::STATUS_FAILED, $missing->status);
        $this->assertSame('server_unavailable', $missing->error);
        $this->assertSame([], RecordingServerBridge::$calls);
        $this->assertSame(0, ServerCommand::query()->count());
    }

    public function test_a_superseded_revision_is_never_dispatched(): void
    {
        $user = $this->createUser();
        $server = $this->createServer('mc-rcon');
        [$skin, $state] = $this->createActiveSkin($user, $server);
        $state->update(['skin_revision' => 2]);

        $result = $this->synchronizer($this->settings($server))->apply($skin, $user);

        $this->assertSame(SyncResult::STALE, $result->status);
        $this->assertSame('stale_revision', $result->error);
        $this->assertSame([], RecordingServerBridge::$calls);
    }

    /**
     * @return array{Skin, SkinSyncState}
     */
    private function createActiveSkin(
        User $user,
        Server $server,
        ?string $targetUuid = self::PRIMARY_UUID,
    ): array {
        $hash = str_repeat('a', 64);
        $path = "skinsystem/skins/{$user->id}/{$hash}.png";
        $skin = Skin::create([
            'user_id' => $user->id,
            'file' => $path,
            'sha256' => $hash,
            'variant' => Skin::VARIANT_CLASSIC,
            'resolved_variant' => Skin::VARIANT_CLASSIC,
            'revision' => 1,
        ]);
        SkinRevision::create([
            'user_id' => $user->id,
            'revision' => 1,
            'file' => $path,
            'sha256' => $hash,
            'resolved_variant' => Skin::VARIANT_CLASSIC,
        ]);
        $state = SkinSyncState::create([
            'user_id' => $user->id,
            'action' => SkinSyncState::ACTION_SET,
            'skin_revision' => 1,
            'status' => SkinSyncState::STATUS_PENDING,
            'target_uuid' => $targetUuid,
            'target_server_id' => $server->id,
        ]);
        if ($targetUuid !== null) {
            SkinSyncTarget::create([
                'user_id' => $user->id,
                'target_uuid' => $targetUuid,
                'target_server_id' => $server->id,
                'status' => SkinSyncTarget::STATUS_POSSIBLE_ACTIVE,
            ]);
        }

        return [$skin, $state];
    }

    private function settings(Server $server): ConfigurableSkinSystemSettings
    {
        $settings = new ConfigurableSkinSystemSettings;
        $settings->selectedServerId = $server->id;

        return $settings;
    }

    private function manager(ConfigurableSkinSystemSettings $settings): ManageSkin
    {
        return new ManageSkin(
            app(SkinProcessor::class),
            app(SkinStorage::class),
            $settings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );
    }

    private function uploadedSkin(int $red, int $green, int $blue): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'skinsystem-sync-test-');
        $image = imagecreatetruecolor(64, 64);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $color = imagecolorallocatealpha($image, $red, $green, $blue, 0);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'skin.png', 'image/png', null, true);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function azLinkSetFetchStates(): array
    {
        return [
            'SET still queued' => [false],
            'SET already fetched' => [true],
        ];
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function azLinkClearFetchStates(): array
    {
        return [
            'CLEAR still queued' => [false],
            'CLEAR already fetched' => [true],
        ];
    }

    private function synchronizer(ConfigurableSkinSystemSettings $settings): SkinSynchronizer
    {
        return new SkinSynchronizer(
            $settings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );
    }
}
