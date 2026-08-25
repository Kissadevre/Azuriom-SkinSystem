<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\User;
use Closure;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class UserSkinLock
{
    public function __construct(
        private readonly int $lockSeconds = 60,
        private readonly int $waitSeconds = 10,
    ) {}

    public function run(User $user, Closure $callback): mixed
    {
        return $this->runForUserId((int) $user->getKey(), $callback);
    }

    public function runForUserId(int $userId, Closure $callback): mixed
    {
        if ($userId < 1) {
            throw new InvalidArgumentException('A positive user ID is required for a SkinSystem lock.');
        }

        return Cache::lock(
            'skinsystem:user:'.$userId,
            $this->lockSeconds,
        )->block($this->waitSeconds, $callback);
    }
}
