<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Illuminate\Support\Facades\Cache;

class MineSkinCapeCatalog
{
    public function __construct(
        private readonly MineSkinClient $client,
        private readonly SkinSystemSettings $settings,
    ) {}

    /**
     * @return array<int, array{uuid: string, alias: string, url: string}>
     */
    public function all(): array
    {
        $apiKey = $this->settings->mineSkinApiKey();

        if ($apiKey === null || ! $this->settings->capeSelectionEnabled()) {
            return [];
        }

        return Cache::remember(
            'skinsystem:mineskin:capes:'.hash('sha256', $apiKey),
            now()->addMinutes(15),
            fn () => $this->client->capes($apiKey),
        );
    }

    /**
     * @return array{uuid: string, alias: string, url: string}|null
     */
    public function find(string $uuid): ?array
    {
        foreach ($this->all() as $cape) {
            if (hash_equals($cape['uuid'], strtolower($uuid))) {
                return $cape;
            }
        }

        return null;
    }
}
