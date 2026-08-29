<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Providers\SkinSystemServiceProvider;
use Azuriom\Plugin\SkinSystem\Requests\UpdateSettingsRequest;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminSettingsValidationTest extends TestCase
{
    public function test_saved_skin_limit_accepts_only_positive_integers(): void
    {
        foreach (['letters', '10skins', '1.5', '-1', '0'] as $value) {
            $request = UpdateSettingsRequest::create('/', 'PUT', [
                'sync_enabled' => '0',
                'delivery_mode' => 'direct',
                'remove_mineskin_api_key' => '0',
                'user_menu_enabled' => '1',
                'user_menu_icon' => SkinSystemSettings::DEFAULT_USER_MENU_ICON,
                'library_limit' => $value,
                'server_id' => null,
            ]);
            $request->setContainer($this->app);

            $validator = Validator::make($request->all(), $request->rules(), $request->messages());

            $this->assertTrue($validator->fails(), "The value {$value} should be rejected.");
            $this->assertArrayHasKey('library_limit', $validator->errors()->toArray());
        }

        foreach (['1', '10', '100'] as $value) {
            $request = UpdateSettingsRequest::create('/', 'PUT', [
                'sync_enabled' => '0',
                'delivery_mode' => 'direct',
                'remove_mineskin_api_key' => '0',
                'user_menu_enabled' => '1',
                'user_menu_icon' => SkinSystemSettings::DEFAULT_USER_MENU_ICON,
                'library_limit' => $value,
                'server_id' => null,
            ]);
            $request->setContainer($this->app);

            $this->assertFalse(
                Validator::make($request->all(), $request->rules(), $request->messages())->fails(),
                "The value {$value} should be accepted.",
            );
        }
    }

    public function test_delivery_mode_is_restricted_to_supported_strategies(): void
    {
        foreach (['direct', 'mineskin', 'hybrid'] as $mode) {
            $request = $this->settingsRequest(['delivery_mode' => $mode]);

            $this->assertFalse(Validator::make($request->all(), $request->rules())->fails());
        }

        $request = $this->settingsRequest(['delivery_mode' => 'automatic']);

        $this->assertTrue(Validator::make($request->all(), $request->rules())->fails());
    }

    public function test_global_mineskin_key_is_encrypted_at_rest(): void
    {
        (new SkinSystemServiceProvider($this->app))->register();

        Setting::updateSettings([
            SkinSystemSettings::MINESKIN_API_KEY_KEY => 'msk_plaintext_secret',
        ]);

        $stored = DB::table('settings')
            ->where('name', SkinSystemSettings::MINESKIN_API_KEY_KEY)
            ->value('value');

        $this->assertNotSame('msk_plaintext_secret', $stored);
        $this->assertSame(
            'msk_plaintext_secret',
            app(SkinSystemSettings::class)->mineSkinApiKey(),
        );
    }

    public function test_mineskin_only_mode_requires_a_global_key(): void
    {
        $request = $this->settingsRequest(['delivery_mode' => SkinSystemSettings::DELIVERY_MINESKIN]);
        $validator = Validator::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mineskin_api_key', $validator->errors()->toArray());
    }

    public function test_user_menu_settings_have_enabled_and_safe_icon_defaults(): void
    {
        $settings = app(SkinSystemSettings::class);
        $provider = new SkinSystemServiceProvider($this->app);
        $navigation = new \ReflectionMethod($provider, 'userNavigation');

        $this->assertTrue($settings->showInUserMenu());
        $this->assertSame(SkinSystemSettings::DEFAULT_USER_MENU_ICON, $settings->userMenuIcon());
        $this->assertSame(
            'bi '.SkinSystemSettings::DEFAULT_USER_MENU_ICON,
            $navigation->invoke($provider)['skinsystem']['icon'],
        );

        Setting::updateSettings([
            SkinSystemSettings::USER_MENU_ENABLED_KEY => false,
            SkinSystemSettings::USER_MENU_ICON_KEY => 'bi-palette-fill',
        ]);

        $this->assertFalse($settings->showInUserMenu());
        $this->assertSame([], $navigation->invoke($provider));

        Setting::updateSettings(SkinSystemSettings::USER_MENU_ENABLED_KEY, true);

        $this->assertSame('bi bi-palette-fill', $navigation->invoke($provider)['skinsystem']['icon']);
    }

    public function test_user_menu_icon_rejects_unsafe_values_and_falls_back_defensively(): void
    {
        foreach (['bi-person text-danger', 'person-bounding-box', 'bi-PERSON', 'bi-person<script>'] as $icon) {
            $request = $this->settingsRequest(['user_menu_icon' => $icon]);

            $this->assertTrue(Validator::make($request->all(), $request->rules())->fails());
        }

        Setting::updateSettings(SkinSystemSettings::USER_MENU_ICON_KEY, 'bi-person text-danger');

        $this->assertSame(
            SkinSystemSettings::DEFAULT_USER_MENU_ICON,
            app(SkinSystemSettings::class)->userMenuIcon(),
        );
    }

    private function settingsRequest(array $overrides = []): UpdateSettingsRequest
    {
        $request = UpdateSettingsRequest::create('/', 'PUT', array_merge([
            'sync_enabled' => '0',
            'delivery_mode' => 'direct',
            'remove_mineskin_api_key' => '0',
            'user_menu_enabled' => '1',
            'user_menu_icon' => SkinSystemSettings::DEFAULT_USER_MENU_ICON,
            'library_limit' => '10',
            'server_id' => null,
        ], $overrides));
        $request->setContainer($this->app);

        return $request;
    }
}
