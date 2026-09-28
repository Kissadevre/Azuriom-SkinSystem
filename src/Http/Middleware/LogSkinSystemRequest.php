<?php

namespace Azuriom\Plugin\SkinSystem\Http\Middleware;

use Azuriom\Plugin\SkinSystem\Services\SkinSystemDebugLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LogSkinSystemRequest
{
    public function __construct(private readonly SkinSystemDebugLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $context = [
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
            'user_id' => $request->user()?->getAuthIdentifier(),
        ];

        $this->logger->debug('HTTP request started.', $context);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logger->error('HTTP request failed.', $context + [
                'duration_ms' => $this->duration($startedAt),
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $this->logger->debug('HTTP request completed.', $context + [
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $this->duration($startedAt),
        ]);

        return $response;
    }

    private function duration(int $startedAt): float
    {
        return round((hrtime(true) - $startedAt) / 1_000_000, 2);
    }
}
