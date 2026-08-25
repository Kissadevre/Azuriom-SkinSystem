<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\Server;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class SkinSystemSettings
{
    public const MAX_DATABASE_ID = 2147483647;

    public const ENABLED_KEY = 'skinsystem.sync_enabled';

    public const SERVER_KEY = 'skinsystem.server_id';

    public const LIBRARY_LIMIT_KEY = 'skinsystem.library_limit';

    public const DEFAULT_LIBRARY_LIMIT = 10;

    public const MAX_LIBRARY_LIMIT = 100;

    /**
     * @return array<int, string>
     */
    public static function supportedServerTypes(): array
    {
        return ['mc-azlink', 'mc-rcon'];
    }

    public function enabled(): bool
    {
        return filter_var(setting(self::ENABLED_KEY, false), FILTER_VALIDATE_BOOL);
    }

    public function serverId(): ?int
    {
        $value = setting(self::SERVER_KEY);

        if (! is_int($value) && (! is_string($value) || preg_match('/^[1-9][0-9]*$/D', $value) !== 1)) {
            return null;
        }

        $serverId = (int) $value;

        return $serverId > 0 && $serverId <= self::MAX_DATABASE_ID ? $serverId : null;
    }

    public function libraryLimit(): int
    {
        $value = filter_var(setting(self::LIBRARY_LIMIT_KEY, self::DEFAULT_LIBRARY_LIMIT), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_LIBRARY_LIMIT],
        ]);

        return $value === false ? self::DEFAULT_LIBRARY_LIMIT : $value;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, \Azuriom\Models\Server>
     */
    public function availableServers(): Collection
    {
        return $this->availableServerQuery()
            ->orderBy('name')
            ->get();
    }

    public function server(): ?Server
    {
        $serverId = $this->serverId();

        return $serverId === null ? null : $this->findServer($serverId);
    }

    public function findServer(int $serverId): ?Server
    {
        return $this->availableServerQuery()->find($serverId);
    }

    private function availableServerQuery(): Builder
    {
        return Server::query()
            ->executable()
            ->whereIn('type', self::supportedServerTypes());
    }
}
