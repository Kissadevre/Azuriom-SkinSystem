<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Azuriom\Plugin\SkinSystem\Services\SkinProcessor;
use Azuriom\Plugin\SkinSystem\Services\SkinsRestorerCommandBuilder;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Services\SkinSyncTargetRegistry;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\ConfigurableSkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SkinLibraryTest extends TestCase
{
    public function test_library_limit_uses_a_safe_default_and_accepts_valid_admin_values(): void
    {
        $settings = app(SkinSystemSettings::class);

        $this->assertSame(10, $settings->libraryLimit());

        Setting::updateSettings([SkinSystemSettings::LIBRARY_LIMIT_KEY => 24]);
        $this->assertSame(24, $settings->libraryLimit());

        Setting::updateSettings([SkinSystemSettings::LIBRARY_LIMIT_KEY => 999]);
        $this->assertSame(10, $settings->libraryLimit());
    }

    public function test_saved_skins_can_be_activated_without_uploading_again(): void
    {
        $user = $this->createUser();
        $manager = $this->manager();

        $first = $manager->store($user, $this->uploadedSkin(30), Skin::VARIANT_CLASSIC, true, 'Knight', 3);
        $second = $manager->store($user, $this->uploadedSkin(90), Skin::VARIANT_SLIM, true, 'Ranger', 3);

        $this->assertSame(2, SavedSkin::query()->count());
        $this->assertSame('Ranger', SavedSkin::query()->latest('id')->firstOrFail()->name);
        $this->assertSame($second['skin']->sha256, Skin::query()->sole()->sha256);

        $result = $manager->activate($user, SavedSkin::query()->where('name', 'Knight')->sole());

        $this->assertTrue($result['changed']);
        $this->assertSame($first['skin']->sha256, $result['skin']->sha256);
        $this->assertSame(3, $result['skin']->revision);
        $this->assertSame(3, SkinRevision::query()->count());
    }

    public function test_library_limit_rejects_only_new_entries_and_deletion_does_not_clear_active_skin(): void
    {
        $user = $this->createUser();
        $manager = $this->manager();

        $manager->store($user, $this->uploadedSkin(30), Skin::VARIANT_CLASSIC, true, 'Original', 1);
        $duplicate = $manager->store($user, $this->uploadedSkin(30), Skin::VARIANT_CLASSIC, true, 'Renamed', 1);

        $this->assertFalse($duplicate['changed']);
        $this->assertSame('Renamed', SavedSkin::query()->sole()->name);

        try {
            $manager->store($user, $this->uploadedSkin(120), Skin::VARIANT_CLASSIC, true, 'Second', 1);
            $this->fail('The library quota should reject a new saved skin.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('skin', $exception->errors());
        }

        $activeId = Skin::query()->sole()->id;
        $manager->deleteSaved($user, SavedSkin::query()->sole());

        $this->assertSame(0, SavedSkin::query()->count());
        $this->assertSame($activeId, Skin::query()->sole()->id);
    }

    private function manager(): ManageSkin
    {
        return new ManageSkin(
            app(SkinProcessor::class),
            app(SkinStorage::class),
            new ConfigurableSkinSystemSettings,
            app(SkinsRestorerCommandBuilder::class),
            app(SkinSyncTargetRegistry::class),
        );
    }

    private function uploadedSkin(int $red): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'skinsystem-library-');
        $image = imagecreatetruecolor(64, 64);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, $red, 80, 160, 0));
        imagepng($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'skin.png', 'image/png', null, true);
    }
}
