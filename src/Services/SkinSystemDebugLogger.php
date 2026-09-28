<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Log;
use Throwable;

class SkinSystemDebugLogger
{
    private ?Logger $logger = null;

    public function __construct(private readonly SkinSystemSettings $settings) {}

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if (! $this->settings->debugEnabled()) {
            return;
        }

        $this->logger ??= Log::build([
            'driver' => 'daily',
            'path' => storage_path('logs/skinsystem-debug.log'),
            'level' => 'debug',
            'days' => 14,
            'replace_placeholders' => true,
        ]);

        $this->logger->{$level}('[SkinSystem] '.$message, $this->sanitize($context));
    }

    private function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && preg_match('/(?:api[_-]?key|authorization|cookie|password|secret|token)/i', $key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if ($value instanceof Throwable) {
                $sanitized[$key] = [
                    'class' => $value::class,
                    'message' => $value->getMessage(),
                    'code' => $value->getCode(),
                    'file' => $value->getFile(),
                    'line' => $value->getLine(),
                ];

                continue;
            }

            $sanitized[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $sanitized;
    }
}
