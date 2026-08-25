<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SkinStorage
{
    public const DISK = 'local';

    /**
     * Build a path exclusively from validated internal identifiers.
     */
    public function path(int $userId, string $sha256): string
    {
        if ($userId < 1 || preg_match('/^[a-f0-9]{64}$/D', $sha256) !== 1) {
            throw new RuntimeException('Invalid SkinSystem storage identifier.');
        }

        return "skinsystem/skins/{$userId}/{$sha256}.png";
    }

    /**
     * Persist an immutable normalized skin blob.
     */
    public function put(int $userId, string $sha256, string $contents): string
    {
        $path = $this->path($userId, $sha256);
        $disk = $this->disk();

        if (! $disk->exists($path) && ! $disk->put($path, $contents)) {
            throw new RuntimeException('Unable to store the normalized Minecraft skin.');
        }

        return $path;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(self::DISK);
    }
}
