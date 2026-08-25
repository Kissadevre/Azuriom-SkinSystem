<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Fakes;

use Azuriom\Games\ServerBridge;
use Azuriom\Models\User;
use RuntimeException;

class RecordingServerBridge extends ServerBridge
{
    /** @var array<int, array{server_id: int, commands: array<int, string>, user_id: int, require_online: bool}> */
    public static array $calls = [];

    public static int $notifications = 0;

    public static bool $throwAfterRecording = false;

    public static bool $throwOnNotification = false;

    public static function reset(): void
    {
        self::$calls = [];
        self::$notifications = 0;
        self::$throwAfterRecording = false;
        self::$throwOnNotification = false;
    }

    public function getServerData(): ?array
    {
        return null;
    }

    public function verifyLink(): bool
    {
        return true;
    }

    public function sendCommands(array $commands, User $user, bool $needConnected = false): void
    {
        self::$calls[] = [
            'server_id' => (int) $this->server->getKey(),
            'commands' => array_values($commands),
            'user_id' => (int) $user->getKey(),
            'require_online' => $needConnected,
        ];

        if (self::$throwAfterRecording) {
            throw new RuntimeException('Simulated bridge failure after dispatch began.');
        }
    }

    public function sendServerRequest(): void
    {
        self::$notifications++;

        if (self::$throwOnNotification) {
            throw new RuntimeException('Simulated AzLink notification failure.');
        }
    }

    public function canExecuteCommand(): bool
    {
        return true;
    }
}
