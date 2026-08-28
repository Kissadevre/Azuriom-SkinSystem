<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Plugin\SkinSystem\Exceptions\SyncPreconditionException;
use Azuriom\Plugin\SkinSystem\Models\Skin;

class SkinsRestorerCommandBuilder
{
    public const MAX_URL_LENGTH = 266;

    public function setSkin(Skin $skin, string $targetUuid, ?string $sourceUrl = null): string
    {
        $uuid = $this->canonicalUuid($targetUuid);

        if ($uuid === null) {
            throw new SyncPreconditionException('invalid_game_id');
        }

        if (! in_array($skin->resolved_variant, [Skin::VARIANT_CLASSIC, Skin::VARIANT_SLIM], true)) {
            throw new SyncPreconditionException('invalid_variant');
        }

        $url = $sourceUrl ?? $skin->publicUrl();
        $this->validatePublicUrl($url);

        return sprintf('skin set "%s" %s %s', $url, $uuid, $skin->resolved_variant);
    }

    public function clearSkin(string $targetUuid): string
    {
        $uuid = $this->canonicalUuid($targetUuid);

        if ($uuid === null) {
            throw new SyncPreconditionException('invalid_game_id');
        }

        return 'skin clear '.$uuid;
    }

    public function canonicalUuid(?string $gameId): ?string
    {
        if ($gameId === null
            || preg_match('/^(?:[a-fA-F0-9]{32}|[a-fA-F0-9]{8}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{4}-[a-fA-F0-9]{12})$/D', $gameId) !== 1) {
            return null;
        }

        $hex = strtolower(str_replace('-', '', $gameId));

        return substr($hex, 0, 8).'-'
            .substr($hex, 8, 4).'-'
            .substr($hex, 12, 4).'-'
            .substr($hex, 16, 4).'-'
            .substr($hex, 20, 12);
    }

    private function validatePublicUrl(string $url): void
    {
        if (strlen($url) > self::MAX_URL_LENGTH) {
            throw new SyncPreconditionException('public_url_too_long');
        }

        if (preg_match('/[^\x21-\x7E]/D', $url) === 1
            || str_contains($url, '"')
            || str_contains($url, '\\')) {
            throw new SyncPreconditionException('invalid_public_url');
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            throw new SyncPreconditionException('invalid_public_url');
        }

        if (strtolower($parts['scheme']) !== 'https') {
            throw new SyncPreconditionException('insecure_public_url');
        }

        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new SyncPreconditionException('invalid_public_url');
        }

        $host = strtolower($parts['host']);
        $ipHost = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? substr($host, 1, -1)
            : $host;

        if ($host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test')
            || (filter_var($ipHost, FILTER_VALIDATE_IP) !== false
                && filter_var($ipHost, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false)) {
            throw new SyncPreconditionException('unreachable_public_url');
        }
    }
}
