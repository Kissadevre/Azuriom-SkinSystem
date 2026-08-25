<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Fakes;

use Azuriom\Games\Game;
use Azuriom\Models\User;

class RecordingGame extends Game
{
    public function id(): string
    {
        return 'mc-offline';
    }

    public function name(): string
    {
        return 'Minecraft';
    }

    public function getAvatarUrl(User $user, int $size = 64): string
    {
        return '';
    }

    public function getUserUniqueId(string $name): ?string
    {
        return $name;
    }

    public function getUserName(User $user): ?string
    {
        return $user->name;
    }

    public function getSupportedServers(): array
    {
        return [
            'mc-azlink' => RecordingServerBridge::class,
            'mc-rcon' => RecordingServerBridge::class,
        ];
    }
}
