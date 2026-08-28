<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Plugin\SkinSystem\Exceptions\MineSkinApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class MineSkinClient
{
    public const BASE_URL = 'https://api.mineskin.org/v2';

    /**
     * Confirm that the credential belongs to a MineSkin account and return the
     * capabilities that affect SkinSystem's interface.
     *
     * @return array{capes: bool}
     */
    public function verifyApiKey(string $apiKey): array
    {
        try {
            $response = $this->request($apiKey)->get(self::BASE_URL.'/me');
        } catch (ConnectionException) {
            throw new MineSkinApiException('unavailable', true);
        }

        if ($response->status() === 401 || $response->status() === 403 || $response->status() === 404) {
            throw new MineSkinApiException('invalid_key');
        }

        if (! $response->successful() || ! is_array($response->json())) {
            throw new MineSkinApiException('unavailable', $response->serverError() || $response->status() === 429);
        }

        $grants = $response->json('grants', []);
        $capes = data_get($grants, 'capes') === true
            || (is_array($grants) && in_array('capes', $grants, true));

        return ['capes' => $capes];
    }

    /**
     * @return array{status: string, job_id: string|null, result_uuid: string|null, result_url: string|null}
     */
    public function queue(
        string $apiKey,
        string $contents,
        string $variant,
        ?string $capeId = null,
    ): array {
        $parameters = ['variant' => $variant, 'visibility' => 'unlisted'];

        if ($capeId !== null) {
            $parameters['cape'] = $capeId;
        }

        try {
            $response = $this->request($apiKey)
                ->attach('file', $contents, 'skin.png')
                ->post(self::BASE_URL.'/queue', $parameters);
        } catch (ConnectionException) {
            throw new MineSkinApiException('unavailable', true);
        }

        $this->ensureGenerationResponse($response);

        return $this->normalizeGenerationResponse($response->json());
    }

    /**
     * @return array{status: string, job_id: string|null, result_uuid: string|null, result_url: string|null}
     */
    public function job(string $apiKey, string $jobId): array
    {
        if (preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $jobId) !== 1) {
            throw new MineSkinApiException('invalid_response');
        }

        try {
            $response = $this->request($apiKey)->get(self::BASE_URL.'/queue/'.$jobId);
        } catch (ConnectionException) {
            throw new MineSkinApiException('unavailable', true);
        }

        $this->ensureGenerationResponse($response);

        return $this->normalizeGenerationResponse($response->json(), $jobId);
    }

    /**
     * @return array<int, array{uuid: string, alias: string, url: string}>
     */
    public function capes(string $apiKey): array
    {
        try {
            $response = $this->request($apiKey)->get(self::BASE_URL.'/capes');
        } catch (ConnectionException) {
            throw new MineSkinApiException('unavailable', true);
        }

        $this->ensureGenerationResponse($response);
        $capes = $response->json('capes', []);

        if (! is_array($capes)) {
            throw new MineSkinApiException('invalid_response');
        }

        return collect($capes)
            ->filter(fn ($cape) => is_array($cape) && data_get($cape, 'supported') === true)
            ->map(function (array $cape) {
                $uuid = data_get($cape, 'uuid');
                $alias = trim((string) data_get($cape, 'alias'));
                $url = (string) data_get($cape, 'url');

                if (str_starts_with(strtolower($url), 'http://')) {
                    $url = 'https://'.substr($url, 7);
                }

                if (! is_string($uuid)
                    || preg_match('/^(?:[a-fA-F0-9]{32}|[a-fA-F0-9-]{36})$/D', $uuid) !== 1
                    || $alias === ''
                    || mb_strlen($alias) > 64
                    || ! $this->isSafeCapeUrl($url)) {
                    return null;
                }

                return [
                    'uuid' => strtolower($uuid),
                    'alias' => $alias,
                    'url' => $url,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function request(string $apiKey): PendingRequest
    {
        return Http::withToken($apiKey)
            ->acceptJson()
            ->withUserAgent($this->userAgent())
            ->connectTimeout(5)
            ->timeout(10);
    }

    private function ensureGenerationResponse(Response $response): void
    {
        if ($response->status() === 401 || $response->status() === 403) {
            throw new MineSkinApiException('invalid_key');
        }

        if ($response->status() === 429) {
            throw new MineSkinApiException('rate_limited', true);
        }

        if (! $response->successful()) {
            throw new MineSkinApiException(
                $response->serverError() ? 'unavailable' : 'request_rejected',
                $response->serverError(),
            );
        }

        if (! is_array($response->json())) {
            throw new MineSkinApiException('invalid_response');
        }

        if ($response->json('success') === false) {
            $status = strtolower((string) $response->json('job.status'));

            throw new MineSkinApiException($status === 'failed' ? 'generation_failed' : 'request_rejected');
        }
    }

    /**
     * Normalize both immediate queue responses and asynchronously polled jobs.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status: string, job_id: string|null, result_uuid: string|null, result_url: string|null}
     */
    private function normalizeGenerationResponse(array $payload, ?string $knownJobId = null): array
    {
        $skinUuid = data_get($payload, 'skin.uuid')
            ?? data_get($payload, 'result.skin.uuid')
            ?? data_get($payload, 'job.result.skin.uuid');

        if (is_string($skinUuid)
            && preg_match('/^(?:[a-fA-F0-9]{32}|[a-fA-F0-9-]{36})$/D', $skinUuid) === 1) {
            $normalizedUuid = strtolower(str_replace('-', '', $skinUuid));

            return [
                'status' => 'completed',
                'job_id' => $this->jobIdFrom($payload, $knownJobId),
                'result_uuid' => $normalizedUuid,
                'result_url' => 'https://minesk.in/'.$normalizedUuid,
            ];
        }

        $status = strtolower((string) (
            data_get($payload, 'status')
            ?? data_get($payload, 'job.status')
            ?? 'pending'
        ));

        if ($status === 'failed') {
            throw new MineSkinApiException('generation_failed');
        }

        $jobId = $this->jobIdFrom($payload, $knownJobId);

        if ($jobId === null) {
            throw new MineSkinApiException('invalid_response');
        }

        return [
            'status' => in_array($status, ['queued', 'pending'], true) ? 'pending' : 'processing',
            'job_id' => $jobId,
            'result_uuid' => null,
            'result_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jobIdFrom(array $payload, ?string $fallback): ?string
    {
        $jobId = data_get($payload, 'job.id')
            ?? data_get($payload, 'job.uuid')
            ?? data_get($payload, 'id')
            ?? $fallback;

        return is_string($jobId) && preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $jobId) === 1
            ? $jobId
            : null;
    }

    private function isSafeCapeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        return $host === 'textures.minecraft.net' || str_ends_with($host, '.minecraft.net');
    }

    private function userAgent(): string
    {
        return sprintf(
            'Azuriom-SkinSystem/%s (%s)',
            (string) (app('plugins')->findDescription('skinsystem')?->version ?? 'development'),
            rtrim((string) config('app.url'), '/'),
        );
    }
}
