<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageSkin
{
    /** Retry transient lock conflicts on SQLite, where row locks are unavailable. */
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly SkinProcessor $processor,
        private readonly SkinStorage $storage,
    ) {
    }

    /**
     * Store the normalized skin and make it the user's current revision.
     *
     * @return array{skin: Skin, changed: bool}
     *
     */
    public function store(User $user, UploadedFile $file, string $variant): array
    {
        $processed = $this->processor->process($file);

        if ($processed['height'] === 32 && $variant === Skin::VARIANT_SLIM) {
            throw ValidationException::withMessages([
                'variant' => trans('skinsystem::messages.validation.legacy_slim'),
            ]);
        }

        $resolvedVariant = $variant === Skin::VARIANT_AUTO
            ? $processed['detected_variant']
            : $variant;

        $path = $this->storage->put(
            (int) $user->getKey(),
            $processed['sha256'],
            $processed['contents'],
        );

        return DB::transaction(function () use ($user, $variant, $resolvedVariant, $path, $processed) {
            // The user row always exists and gives concurrent first uploads a
            // shared lock target before the unique skin row has been created.
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $skin = Skin::query()
                ->where('user_id', $user->getKey())
                ->first();

            if ($skin !== null
                && hash_equals($skin->sha256, $processed['sha256'])
                && $skin->variant === $variant
                && $skin->resolved_variant === $resolvedVariant) {
                return ['skin' => $skin, 'changed' => false];
            }

            if ($skin === null) {
                $skin = new Skin([
                    'user_id' => $user->getKey(),
                    'revision' => 1,
                ]);
            } else {
                $skin->revision++;
            }

            $skin->fill([
                'file' => $path,
                'sha256' => $processed['sha256'],
                'variant' => $variant,
                'resolved_variant' => $resolvedVariant,
            ])->save();

            return ['skin' => $skin, 'changed' => true];
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Remove the user's active skin record.
     *
     * Immutable blobs are retained temporarily so a queued server command never
     * resolves to different bytes or a premature 404.
     */
    public function delete(User $user): bool
    {
        return DB::transaction(function () use ($user) {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            return (bool) Skin::query()
                ->where('user_id', $user->getKey())
                ->delete();
        }, self::TRANSACTION_ATTEMPTS);
    }
}
