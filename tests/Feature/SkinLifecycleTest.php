<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Commands\CleanupSkinRevisions;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncTarget;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Azuriom\Plugin\SkinSystem\Services\SkinProcessor;
use Azuriom\Plugin\SkinSystem\Services\SkinsRestorerCommandBuilder;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Services\SkinSyncTargetRegistry;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\ConfigurableSkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Console\Tester\CommandTester;

class SkinLifecycleTest extends TestCase
{
    public function test_upload_replace_delete_cleanup_and_reupload_keep_monotonic_revisions(): void
    {
        $user = $this->createUser();
        $server = $this->createServer();
        $settings = new ConfigurableSkinSystemSettings;
        $settings->selectedServerId = $server->id;
        $manager = new ManageSkin(
            app(SkinProcessor::class),
            app(SkinStorage::class),
            $settings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );
        $upload = $this->uploadedSkin(60, 100, 180);

        $first = $manager->store($user, $upload, Skin::VARIANT_CLASSIC);
        $same = $manager->store($user, $this->uploadedSkin(60, 100, 180), Skin::VARIANT_CLASSIC);
        $second = $manager->store($user, $this->uploadedSkin(60, 100, 180), Skin::VARIANT_SLIM);

        $this->assertTrue($first['changed']);
        $this->assertFalse($same['changed']);
        $this->assertTrue($second['changed']);
        $this->assertSame(1, $first['skin']->revision);
        $this->assertSame(2, $second['skin']->revision);
        $this->assertSame(2, SkinRevision::query()->count());
        $this->assertSame(SkinSyncState::ACTION_SET, SkinSyncState::query()->sole()->action);
        $this->assertSame(self::PRIMARY_UUID, SkinSyncState::query()->sole()->target_uuid);
        $this->assertSame($server->id, SkinSyncState::query()->sole()->target_server_id);
        $this->assertSame(1, SkinSyncTarget::query()->count());

        $clearState = $manager->delete($user);
        $this->assertNotNull($clearState);
        $this->assertSame(SkinSyncState::ACTION_CLEAR, $clearState->action);
        $this->assertSame(2, $clearState->skin_revision);
        $this->assertSame(
            SkinSyncTarget::STATUS_CLEAR_PENDING,
            SkinSyncTarget::query()->sole()->status,
        );
        $this->assertSame(0, Skin::query()->count());

        $old = now()->subDays(CleanupSkinRevisions::DEFAULT_RETENTION_DAYS + 1);
        SkinRevision::query()->update(['created_at' => $old, 'updated_at' => $old]);

        foreach (Storage::disk('local')->allFiles('skinsystem/skins') as $path) {
            touch(Storage::disk('local')->path($path), $old->getTimestamp());
        }

        $command = app(CleanupSkinRevisions::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);
        $this->assertSame(0, $tester->execute(['--days' => CleanupSkinRevisions::DEFAULT_RETENTION_DAYS]));
        $this->assertSame(0, SkinRevision::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('skinsystem/skins'));

        $third = $manager->store($user, $this->uploadedSkin(80, 130, 210), Skin::VARIANT_CLASSIC);

        $this->assertSame(3, $third['skin']->revision);
        $this->assertSame(3, SkinSyncState::query()->sole()->skin_revision);
        $this->assertSame(1, SkinRevision::query()->count());
    }

    public function test_cleanup_keeps_the_active_revision_and_rejects_invalid_retention(): void
    {
        $user = $this->createUser();
        $hash = str_repeat('f', 64);
        $path = app(SkinStorage::class)->path($user->id, $hash);
        Storage::disk('local')->put($path, 'active');
        $old = now()->subDays(60);
        touch(Storage::disk('local')->path($path), $old->getTimestamp());
        Skin::create([
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
            'created_at' => $old,
            'updated_at' => $old,
        ]);
        $command = app(CleanupSkinRevisions::class);
        $command->setLaravel($this->app);
        $tester = new CommandTester($command);

        $this->assertSame(0, $tester->execute(['--days' => 30]));
        $this->assertSame(1, SkinRevision::query()->count());
        Storage::disk('local')->assertExists($path);

        $this->assertSame(2, $tester->execute(['--days' => 0]));
    }

    private function uploadedSkin(int $red, int $green, int $blue): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'skinsystem-test-');
        $image = imagecreatetruecolor(64, 64);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $color = imagecolorallocatealpha($image, $red, $green, $blue, 0);
        imagefill($image, 0, 0, $color);
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'skin.png', 'image/png', null, true);
    }
}
