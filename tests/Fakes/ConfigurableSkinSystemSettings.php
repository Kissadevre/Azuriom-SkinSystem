<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Fakes;

use Azuriom\Models\Server;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;

class ConfigurableSkinSystemSettings extends SkinSystemSettings
{
    public bool $syncEnabled = true;

    public ?int $selectedServerId = null;

    public function enabled(): bool
    {
        return $this->syncEnabled;
    }

    public function serverId(): ?int
    {
        return $this->selectedServerId;
    }

    public function findServer(int $serverId): ?Server
    {
        return Server::query()
            ->whereKey($serverId)
            ->whereIn('type', self::supportedServerTypes())
            ->first();
    }
}
