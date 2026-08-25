<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncTarget;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SkinMigrationTest extends TestCase
{
    public function test_upgrade_migrations_backfill_revisions_targets_and_clear_ownership(): void
    {
        Schema::drop('skinsystem_sync_targets');
        Schema::drop('skinsystem_sync_states');
        Schema::drop('skinsystem_skin_revisions');
        $user = $this->createUser('123456781234423482341234567890AB');
        $server = $this->createServer();
        $newServer = $this->createServer(name: 'New target');
        Setting::create(['name' => 'skinsystem.server_id', 'value' => (string) $server->id]);
        $hash = str_repeat('e', 64);
        Skin::create([
            'user_id' => $user->id,
            'file' => "skinsystem/skins/{$user->id}/{$hash}.png",
            'sha256' => $hash,
            'variant' => Skin::VARIANT_AUTO,
            'resolved_variant' => Skin::VARIANT_SLIM,
            'revision' => 9,
        ]);

        $revisionMigration = require dirname(__DIR__, 2)
            .'/database/migrations/2026_08_25_000050_create_skinsystem_skin_revisions_table.php';
        $syncMigration = require dirname(__DIR__, 2)
            .'/database/migrations/2026_08_25_000100_create_skinsystem_sync_states_table.php';
        $targetMigration = require dirname(__DIR__, 2)
            .'/database/migrations/2026_08_25_000150_create_skinsystem_sync_targets_table.php';
        $revisionMigration->up();
        $syncMigration->up();

        $revision = SkinRevision::query()->sole();
        $state = SkinSyncState::query()->sole();
        $this->assertSame(9, $revision->revision);
        $this->assertSame($hash, $revision->sha256);
        $this->assertSame(SkinSyncState::ACTION_SET, $state->action);
        $this->assertSame(9, $state->skin_revision);
        $this->assertSame(self::PRIMARY_UUID, $state->target_uuid);
        $this->assertSame($server->id, $state->target_server_id);

        Schema::table('skinsystem_sync_states', function (Blueprint $table) {
            $table->char('last_set_uuid', 36)->nullable();
            $table->unsignedInteger('last_set_server_id')->nullable();
        });
        $queued = $newServer->commands()->create([
            'user_id' => $user->id,
            'need_online' => false,
            'command' => 'skin clear '.self::SECONDARY_UUID,
        ]);
        $state->forceFill([
            'action' => SkinSyncState::ACTION_CLEAR,
            'status' => SkinSyncState::STATUS_SUBMITTED,
            'target_uuid' => self::SECONDARY_UUID,
            'target_server_id' => $newServer->id,
            'queued_command_id' => $queued->id,
            'dispatched_at' => now(),
            'last_set_uuid' => self::PRIMARY_UUID,
            'last_set_server_id' => $server->id,
        ])->save();

        $targetMigration->up();

        $originalTarget = SkinSyncTarget::query()
            ->where('target_uuid', self::PRIMARY_UUID)
            ->sole();
        $currentTarget = SkinSyncTarget::query()
            ->where('target_uuid', self::SECONDARY_UUID)
            ->sole();
        $this->assertSame(2, SkinSyncTarget::query()->count());
        $this->assertSame(SkinSyncTarget::STATUS_CLEAR_PENDING, $originalTarget->status);
        $this->assertSame(9, $originalTarget->clear_revision);
        $this->assertNull($originalTarget->queued_clear_command_id);
        $this->assertFalse($originalTarget->clear_may_be_in_flight);
        $this->assertSame(SkinSyncTarget::STATUS_CLEAR_SUBMITTED, $currentTarget->status);
        $this->assertSame(9, $currentTarget->clear_revision);
        $this->assertSame($queued->id, $currentTarget->queued_clear_command_id);
        $this->assertTrue($currentTarget->clear_may_be_in_flight);
        $this->assertNull($state->fresh()->queued_command_id);

        $server->delete();
        $this->assertSame($server->id, $originalTarget->fresh()->target_server_id);

        $targetMigration->down();
        $syncMigration->down();
        $revisionMigration->down();
        $this->assertTrue(Schema::hasTable('skinsystem_skins'));
        $this->assertSame(1, Skin::query()->count());
    }
}
