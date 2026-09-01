<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Plugin\SkinSystem\Exceptions\SyncPreconditionException;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Services\SkinsRestorerCommandBuilder;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;

class SkinsRestorerCommandBuilderTest extends TestCase
{
    public function test_set_and_clear_commands_use_canonical_uuid_and_model(): void
    {
        $builder = app(SkinsRestorerCommandBuilder::class);
        $skin = $this->skin();

        $this->assertSame(
            'skin set "https://skins.example.com/api/skinsystem/skins/1/3-'
                .str_repeat('a', 64).'.png" '.self::PRIMARY_UUID.' slim',
            $builder->setSkin($skin, '123456781234423482341234567890AB'),
        );
        $this->assertSame(
            'skin clear '.self::PRIMARY_UUID,
            $builder->clearSkin('123456781234423482341234567890AB'),
        );
    }

    public function test_set_and_clear_commands_accept_a_valid_minecraft_username(): void
    {
        $builder = app(SkinsRestorerCommandBuilder::class);

        $this->assertSame(
            'skin set "https://skins.example.com/api/skinsystem/skins/1/3-'
                .str_repeat('a', 64).'.png" Player_123 slim',
            $builder->setSkin(
                $this->skin(),
                'Player_123',
                targetType: SkinSystemSettings::TARGET_USERNAME,
            ),
        );
        $this->assertSame(
            'skin clear Player_123',
            $builder->clearSkin('Player_123', SkinSystemSettings::TARGET_USERNAME),
        );

        foreach (['', 'name-with-dash', 'this_name_is_too_long'] as $username) {
            try {
                $builder->setSkin(
                    $this->skin(),
                    $username,
                    targetType: SkinSystemSettings::TARGET_USERNAME,
                );
                $this->fail("The invalid username {$username} was accepted.");
            } catch (SyncPreconditionException $exception) {
                $this->assertSame('invalid_game_username', $exception->reason);
            }
        }
    }

    public function test_private_ipv4_and_bracketed_ipv6_hosts_are_rejected(): void
    {
        foreach ([
            'https://127.0.0.1',
            'https://10.0.0.1',
            'https://[::1]',
            'https://[fc00::1]',
            'https://[fe80::1]',
        ] as $url) {
            config(['app.url' => $url]);

            try {
                app(SkinsRestorerCommandBuilder::class)->setSkin($this->skin(), self::PRIMARY_UUID);
                $this->fail("The private public URL {$url} was accepted.");
            } catch (SyncPreconditionException $exception) {
                $this->assertSame('unreachable_public_url', $exception->reason);
            }
        }
    }

    public function test_http_public_url_is_rejected_before_dispatch(): void
    {
        config(['app.url' => 'http://skins.example.com']);

        try {
            app(SkinsRestorerCommandBuilder::class)->setSkin($this->skin(), self::PRIMARY_UUID);
            $this->fail('An insecure public URL was accepted.');
        } catch (SyncPreconditionException $exception) {
            $this->assertSame('insecure_public_url', $exception->reason);
        }
    }

    private function skin(): Skin
    {
        return new Skin([
            'user_id' => 1,
            'revision' => 3,
            'sha256' => str_repeat('a', 64),
            'resolved_variant' => Skin::VARIANT_SLIM,
        ]);
    }
}
