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
}
