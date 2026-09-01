<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Fakes;

use Azuriom\Models\Server;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;

class ConfigurableSkinSystemSettings extends SkinSystemSettings
{
    public bool $syncEnabled = true;

    public ?int $selectedServerId = null;

    public string $selectedDeliveryMode = self::DELIVERY_DIRECT;

    public string $selectedApplicationTarget = self::TARGET_UUID;

    public ?string $configuredMineSkinApiKey = null;

    public function enabled(): bool
    {
        return $this->syncEnabled;
    }

    public function serverId(): ?int
    {
        return $this->selectedServerId;
    }

    public function deliveryMode(): string
    {
        return $this->selectedDeliveryMode;
    }

    public function applicationTarget(): string
    {
        return $this->selectedApplicationTarget;
    }

    public function mineSkinApiKey(): ?string
    {
        return $this->configuredMineSkinApiKey;
    }

    public function findServer(int $serverId): ?Server
    {
        return Server::query()
            ->whereKey($serverId)
            ->whereIn('type', self::supportedServerTypes())
            ->first();
    }
}
