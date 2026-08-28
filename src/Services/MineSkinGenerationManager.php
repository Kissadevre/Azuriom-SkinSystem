<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Plugin\SkinSystem\Exceptions\MineSkinApiException;
use Azuriom\Plugin\SkinSystem\Models\MineSkinGeneration;
use Azuriom\Plugin\SkinSystem\Models\Skin;

class MineSkinGenerationManager
{
    private const POLL_DELAY_SECONDS = 3;

    private const RETRY_DELAY_SECONDS = 30;

    public function __construct(
        private readonly MineSkinClient $client,
        private readonly SkinStorage $storage,
        private readonly SkinSystemSettings $settings,
    ) {}

    public function ensure(Skin $skin, bool $retryFailed = false): MineSkinGeneration
    {
        $appearanceHash = $this->appearanceHash($skin);
        $generation = MineSkinGeneration::query()->firstOrCreate(
            ['user_id' => $skin->user_id, 'skin_revision' => $skin->revision],
            ['appearance_hash' => $appearanceHash, 'status' => MineSkinGeneration::STATUS_PENDING],
        );

        if (! hash_equals($generation->appearance_hash, $appearanceHash)) {
            $generation->fill([
                'appearance_hash' => $appearanceHash,
                'status' => MineSkinGeneration::STATUS_PENDING,
                'job_id' => null,
                'result_uuid' => null,
                'result_url' => null,
                'error' => null,
                'attempts' => 0,
                'next_poll_at' => null,
                'last_polled_at' => null,
                'completed_at' => null,
            ])->save();
        }

        if ($generation->isComplete()) {
            return $generation;
        }

        if ($generation->status === MineSkinGeneration::STATUS_FAILED) {
            if (! $retryFailed) {
                return $generation;
            }

            $generation->fill([
                'status' => MineSkinGeneration::STATUS_PENDING,
                'job_id' => null,
                'error' => null,
                'attempts' => 0,
                'next_poll_at' => null,
            ])->save();
        }

        $reusable = MineSkinGeneration::query()
            ->where('appearance_hash', $appearanceHash)
            ->where('status', MineSkinGeneration::STATUS_COMPLETED)
            ->whereNotNull('result_url')
            ->latest('id')
            ->first();

        if ($reusable !== null) {
            $generation->fill([
                'status' => MineSkinGeneration::STATUS_COMPLETED,
                'result_uuid' => $reusable->result_uuid,
                'result_url' => $reusable->result_url,
                'error' => null,
                'completed_at' => now(),
                'next_poll_at' => null,
            ])->save();

            return $generation;
        }

        if ($generation->job_id !== null) {
            return $this->advance($skin, $generation);
        }

        return $this->submit($skin, $generation);
    }

    public function advance(Skin $skin, ?MineSkinGeneration $generation = null): MineSkinGeneration
    {
        $generation ??= MineSkinGeneration::query()
            ->where('user_id', $skin->user_id)
            ->where('skin_revision', $skin->revision)
            ->firstOrFail();

        if ($generation->isComplete() || $generation->status === MineSkinGeneration::STATUS_FAILED) {
            return $generation;
        }

        if ($generation->job_id === null) {
            return $this->submit($skin, $generation);
        }

        if ($generation->next_poll_at?->isFuture()) {
            return $generation;
        }

        $apiKey = $this->settings->mineSkinApiKey();

        if ($apiKey === null) {
            return $this->fail($generation, 'mineskin_not_configured');
        }

        try {
            $result = $this->client->job($apiKey, $generation->job_id);
        } catch (MineSkinApiException $exception) {
            return $this->handleException($generation, $exception);
        }

        return $this->applyResult($generation, $result);
    }

    private function submit(Skin $skin, MineSkinGeneration $generation): MineSkinGeneration
    {
        $apiKey = $this->settings->mineSkinApiKey();

        if ($apiKey === null) {
            return $this->fail($generation, 'mineskin_not_configured');
        }

        if (! $this->storage->disk()->exists($skin->file)) {
            return $this->fail($generation, 'mineskin_source_missing');
        }

        try {
            $result = $this->client->queue(
                $apiKey,
                $this->storage->disk()->get($skin->file),
                $skin->resolved_variant,
                $skin->cape_id,
            );
        } catch (MineSkinApiException $exception) {
            return $this->handleException($generation, $exception);
        }

        return $this->applyResult($generation, $result);
    }

    /**
     * @param  array{status: string, job_id: string|null, result_uuid: string|null, result_url: string|null}  $result
     */
    private function applyResult(MineSkinGeneration $generation, array $result): MineSkinGeneration
    {
        $generation->attempts++;
        $generation->last_polled_at = now();
        $generation->error = null;

        if ($result['status'] === 'completed') {
            $generation->fill([
                'status' => MineSkinGeneration::STATUS_COMPLETED,
                'job_id' => $result['job_id'] ?? $generation->job_id,
                'result_uuid' => $result['result_uuid'],
                'result_url' => $result['result_url'],
                'next_poll_at' => null,
                'completed_at' => now(),
            ])->save();

            return $generation;
        }

        $generation->fill([
            'status' => $result['status'] === 'pending'
                ? MineSkinGeneration::STATUS_PENDING
                : MineSkinGeneration::STATUS_PROCESSING,
            'job_id' => $result['job_id'],
            'next_poll_at' => now()->addSeconds(self::POLL_DELAY_SECONDS),
        ])->save();

        return $generation;
    }

    private function handleException(
        MineSkinGeneration $generation,
        MineSkinApiException $exception,
    ): MineSkinGeneration {
        $error = 'mineskin_'.$exception->reason;

        if (! $exception->retryable) {
            return $this->fail($generation, $error);
        }

        $generation->fill([
            'status' => MineSkinGeneration::STATUS_PROCESSING,
            'error' => $error,
            'attempts' => $generation->attempts + 1,
            'last_polled_at' => now(),
            'next_poll_at' => now()->addSeconds(self::RETRY_DELAY_SECONDS),
        ])->save();

        return $generation;
    }

    private function fail(MineSkinGeneration $generation, string $error): MineSkinGeneration
    {
        $generation->fill([
            'status' => MineSkinGeneration::STATUS_FAILED,
            'error' => $error,
            'next_poll_at' => null,
            'last_polled_at' => now(),
        ])->save();

        return $generation;
    }

    private function appearanceHash(Skin $skin): string
    {
        return hash(
            'sha256',
            $skin->sha256.'|'.$skin->resolved_variant.'|'.($skin->cape_id ?? 'none'),
        );
    }
}
