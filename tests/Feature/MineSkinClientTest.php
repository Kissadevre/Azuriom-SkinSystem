<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Exceptions\MineSkinApiException;
use Azuriom\Plugin\SkinSystem\Services\MineSkinClient;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class MineSkinClientTest extends TestCase
{
    public function test_it_verifies_a_key_and_detects_the_capes_grant(): void
    {
        Http::fake([
            MineSkinClient::BASE_URL.'/me' => Http::response([
                'uuid' => 'test-account',
                'grants' => ['capes' => true],
            ]),
        ]);

        $result = app(MineSkinClient::class)->verifyApiKey('msk_test_secret');

        $this->assertSame(['capes' => true], $result);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer msk_test_secret'));
    }

    public function test_it_rejects_an_invalid_key_without_exposing_it(): void
    {
        Http::fake([
            MineSkinClient::BASE_URL.'/me' => Http::response(['error' => 'forbidden'], 403),
        ]);

        try {
            app(MineSkinClient::class)->verifyApiKey('msk_invalid_secret');
            $this->fail('The invalid MineSkin key should have been rejected.');
        } catch (MineSkinApiException $exception) {
            $this->assertSame('invalid_key', $exception->reason);
            $this->assertStringNotContainsString('msk_invalid_secret', $exception->getMessage());
        }
    }

    public function test_it_returns_only_supported_and_safe_capes(): void
    {
        Http::fake([
            MineSkinClient::BASE_URL.'/capes' => Http::response([
                'success' => true,
                'capes' => [
                    [
                        'uuid' => '123456781234423482341234567890ab',
                        'alias' => 'Migrator',
                        'url' => 'http://textures.minecraft.net/texture/cape',
                        'supported' => true,
                    ],
                    [
                        'uuid' => 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
                        'alias' => 'Unsupported',
                        'url' => 'https://textures.minecraft.net/texture/old',
                        'supported' => false,
                    ],
                    [
                        'uuid' => 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb',
                        'alias' => 'Unsafe',
                        'url' => 'https://example.com/cape.png',
                        'supported' => true,
                    ],
                ],
            ]),
        ]);

        $capes = app(MineSkinClient::class)->capes('msk_test_secret');

        $this->assertSame([[
            'uuid' => '123456781234423482341234567890ab',
            'alias' => 'Migrator',
            'url' => 'https://textures.minecraft.net/texture/cape',
        ]], $capes);
    }
}
