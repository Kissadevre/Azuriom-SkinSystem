<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Feature;

use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemDebugLogger;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Azuriom\Plugin\SkinSystem\Tests\TestCase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Mockery;

class SkinSystemDebugLoggerTest extends TestCase
{
    public function test_it_does_not_build_a_log_channel_when_debug_is_disabled(): void
    {
        Log::shouldReceive('build')->never();

        app(SkinSystemDebugLogger::class)->debug('This must not be written.');
    }

    public function test_it_writes_to_a_daily_channel_and_redacts_sensitive_context(): void
    {
        Setting::updateSettings(SkinSystemSettings::DEBUG_ENABLED_KEY, true);

        $channel = Mockery::mock(Logger::class);
        $channel->shouldReceive('debug')
            ->once()
            ->with('[SkinSystem] Diagnostic event.', [
                'user_id' => 42,
                'api_key' => '[REDACTED]',
                'nested' => ['authorization' => '[REDACTED]'],
            ]);

        Log::shouldReceive('build')
            ->once()
            ->with(Mockery::on(fn (array $configuration) => $configuration['driver'] === 'daily'
                && $configuration['path'] === storage_path('logs/skinsystem-debug.log')
                && $configuration['days'] === 14))
            ->andReturn($channel);

        app(SkinSystemDebugLogger::class)->debug('Diagnostic event.', [
            'user_id' => 42,
            'api_key' => 'never-log-this',
            'nested' => ['authorization' => 'Bearer never-log-this'],
        ]);
    }
}
