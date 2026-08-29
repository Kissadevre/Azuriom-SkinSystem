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

    public const USER_MENU_ENABLED_KEY = 'skinsystem.user_menu_enabled';

    public const USER_MENU_ICON_KEY = 'skinsystem.user_menu_icon';

    public const DELIVERY_MODE_KEY = 'skinsystem.delivery_mode';

    public const MINESKIN_API_KEY_KEY = 'skinsystem.mineskin_api_key';

    public const MINESKIN_VERIFIED_AT_KEY = 'skinsystem.mineskin_verified_at';

    public const MINESKIN_CAPES_GRANTED_KEY = 'skinsystem.mineskin_capes_granted';

    public const DELIVERY_DIRECT = 'direct';

    public const DELIVERY_MINESKIN = 'mineskin';

    public const DELIVERY_HYBRID = 'hybrid';

    public const DEFAULT_LIBRARY_LIMIT = 10;

    public const DEFAULT_USER_MENU_ICON = 'bi-person-bounding-box';

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

    public function showInUserMenu(): bool
    {
        return filter_var(setting(self::USER_MENU_ENABLED_KEY, true), FILTER_VALIDATE_BOOL);
    }

    public function userMenuIcon(): string
    {
        $icon = setting(self::USER_MENU_ICON_KEY, self::DEFAULT_USER_MENU_ICON);

        if (! is_string($icon) || preg_match('/^bi-[a-z0-9]+(?:-[a-z0-9]+)*$/D', $icon) !== 1) {
            return self::DEFAULT_USER_MENU_ICON;
        }

        return $icon;
    }

    /**
     * Return the configured delivery strategy. Direct remains the default so
     * upgrading an existing installation never introduces a third-party call.
     */
    public function deliveryMode(): string
    {
        $mode = setting(self::DELIVERY_MODE_KEY, self::DELIVERY_DIRECT);

        return is_string($mode) && in_array($mode, self::deliveryModes(), true)
            ? $mode
            : self::DELIVERY_DIRECT;
    }

    /**
     * @return array<int, string>
     */
    public static function deliveryModes(): array
    {
        return [
            self::DELIVERY_DIRECT,
            self::DELIVERY_MINESKIN,
            self::DELIVERY_HYBRID,
        ];
    }

    public function mineSkinApiKey(): ?string
    {
        $key = setting(self::MINESKIN_API_KEY_KEY);

        if (! is_string($key) || trim($key) === '') {
            return null;
        }

        return trim($key);
    }

    public function hasMineSkinApiKey(): bool
    {
        return $this->mineSkinApiKey() !== null;
    }

    public function mineSkinCapesGranted(): bool
    {
        return $this->hasMineSkinApiKey()
            && filter_var(setting(self::MINESKIN_CAPES_GRANTED_KEY, false), FILTER_VALIDATE_BOOL);
    }

    public function capeSelectionEnabled(): bool
    {
        return $this->deliveryMode() !== self::DELIVERY_DIRECT
            && $this->mineSkinCapesGranted();
    }

    public function deliveryStrategyFor(?string $capeId): string
    {
        return match ($this->deliveryMode()) {
            self::DELIVERY_MINESKIN => self::DELIVERY_MINESKIN,
            self::DELIVERY_HYBRID => $capeId === null
                ? self::DELIVERY_DIRECT
                : self::DELIVERY_MINESKIN,
            default => self::DELIVERY_DIRECT,
        };
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
