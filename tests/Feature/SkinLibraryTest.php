<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Requests\StoreSkinRequest;
use Azuriom\Plugin\SkinSystem\Services\ManageSkin;
use Azuriom\Plugin\SkinSystem\Services\SkinProcessor;
use Azuriom\Plugin\SkinSystem\Services\SkinsRestorerCommandBuilder;
use Azuriom\Plugin\SkinSystem\Services\SkinStorage;
use Azuriom\Plugin\SkinSystem\Services\SkinSyncTargetRegistry;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\Fakes\ConfigurableSkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SkinLibraryTest extends TestCase
{
    public function test_saved_skin_names_are_limited_to_sixteen_ascii_letters_and_numbers(): void
    {
        $user = $this->createUser();

        foreach (['Has spaces', 'Skin!', 'SeventeenChars123'] as $name) {
            $request = StoreSkinRequest::create('/', 'POST', [
                'action' => 'save',
                'variant' => Skin::VARIANT_CLASSIC,
                'name' => $name,
            ], [], ['skin' => $this->uploadedSkin(45)]);
            $request->setContainer($this->app);
            $request->setUserResolver(fn () => $user);

            $validator = Validator::make($request->all(), $request->rules(), $request->messages());
            $this->assertTrue($validator->fails(), "The name {$name} should be rejected.");
            $this->assertArrayHasKey('name', $validator->errors()->toArray());
        }

        $request = StoreSkinRequest::create('/', 'POST', [
            'action' => 'save',
            'variant' => Skin::VARIANT_CLASSIC,
            'name' => 'RedstoneHero123',
        ], [], ['skin' => $this->uploadedSkin(45)]);
        $request->setContainer($this->app);
        $request->setUserResolver(fn () => $user);

        $this->assertFalse(Validator::make($request->all(), $request->rules(), $request->messages())->fails());
    }

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

        $first = $manager->save($user, $this->uploadedSkin(30), Skin::VARIANT_CLASSIC, 'Knight', 3);
        $manager->save($user, $this->uploadedSkin(90), Skin::VARIANT_SLIM, 'Ranger', 3);

        $this->assertSame(2, SavedSkin::query()->count());
        $this->assertSame('Ranger', SavedSkin::query()->latest('id')->firstOrFail()->name);
        $this->assertSame(0, Skin::query()->count());
        $this->assertSame(0, SkinRevision::query()->count());

        $result = $manager->activate($user, SavedSkin::query()->where('name', 'Knight')->sole());

        $this->assertTrue($result['changed']);
        $this->assertSame($first->sha256, $result['skin']->sha256);
        $this->assertSame(1, $result['skin']->revision);
        $this->assertSame(1, SkinRevision::query()->count());
    }

    public function test_library_limit_rejects_only_new_entries_and_deletion_does_not_clear_active_skin(): void
    {
        $user = $this->createUser();
        $manager = $this->manager();

        $original = $manager->save($user, $this->uploadedSkin(30), Skin::VARIANT_CLASSIC, 'Original', 1);
        $duplicate = $manager->save($user, $this->uploadedSkin(30), Skin::VARIANT_CLASSIC, 'Renamed', 1);

        $this->assertSame($original->id, $duplicate->id);
        $this->assertSame('Renamed', SavedSkin::query()->sole()->name);

        try {
            $manager->save($user, $this->uploadedSkin(120), Skin::VARIANT_CLASSIC, 'Second', 1);
            $this->fail('The library quota should reject a new saved skin.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('replacement_id', $exception->errors());
        }

        $replacement = $manager->save(
            $user,
            $this->uploadedSkin(120),
            Skin::VARIANT_CLASSIC,
            'Second',
            1,
            $original->id,
        );
        $this->assertSame('Second', SavedSkin::query()->sole()->name);
        $manager->activate($user, $replacement);
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
