<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Services\UserSkinLock;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

class UserSkinLockTest extends TestCase
{
    public function test_a_busy_user_operation_times_out_without_running_the_callback(): void
    {
        $user = $this->createUser();
        $held = Cache::lock('skinsystem:user:'.$user->id, 60);
        $this->assertTrue($held->get());
        $ran = false;

        try {
            (new UserSkinLock(60, 0))->run($user, function () use (&$ran) {
                $ran = true;
            });
            $this->fail('The busy user lock did not time out.');
        } catch (LockTimeoutException) {
            $this->assertFalse($ran);
        } finally {
            $held->release();
        }
    }
}
