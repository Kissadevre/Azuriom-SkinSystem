<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Requests\UpdateSettingsRequest;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
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

    private function settingsRequest(array $overrides = []): UpdateSettingsRequest
    {
        $request = UpdateSettingsRequest::create('/', 'PUT', array_merge([
            'sync_enabled' => '0',
            'delivery_mode' => 'direct',
            'remove_mineskin_api_key' => '0',
            'library_limit' => '10',
            'server_id' => null,
        ], $overrides));
        $request->setContainer($this->app);

        return $request;
    }
}
