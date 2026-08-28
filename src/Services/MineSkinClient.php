<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Plugin\SkinSystem\Exceptions\MineSkinApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class MineSkinClient
{
    public const BASE_URL = 'https://api.mineskin.org/v2';

    /**
     * Confirm that the credential belongs to a MineSkin account and return the
     * capabilities that affect SkinSystem's interface.
     *
     * @return array{capes: bool}
     */
    public function verifyApiKey(string $apiKey): array
    {
        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withUserAgent($this->userAgent())
                ->connectTimeout(5)
                ->timeout(10)
                ->get(self::BASE_URL.'/me');
        } catch (ConnectionException) {
            throw new MineSkinApiException('unavailable', true);
        }

        if ($response->status() === 401 || $response->status() === 403 || $response->status() === 404) {
            throw new MineSkinApiException('invalid_key');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new MineSkinApiException('unavailable', $response->serverError() || $response->status() === 429);
        }

        $grants = $response->json('grants', []);
        $capes = data_get($grants, 'capes') === true
            || (is_array($grants) && in_array('capes', $grants, true));

        return ['capes' => $capes];
    }

    private function userAgent(): string
    {
        return sprintf(
            'Azuriom-SkinSystem/%s (%s)',
            (string) (app('plugins')->findDescription('skinsystem')?->version ?? 'development'),
            rtrim((string) config('app.url'), '/'),
        );
    }
}
