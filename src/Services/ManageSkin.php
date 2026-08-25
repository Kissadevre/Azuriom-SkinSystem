<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\ServerCommand;
use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinRevision;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
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
        private readonly SkinSystemSettings $settings,
        private readonly SkinsRestorerCommandBuilder $commands,
        private readonly SkinSyncTargetRegistry $targets,
    ) {}

    /**
     * Store the normalized skin and make it the user's current revision.
     *
     * @return array{skin: Skin, changed: bool}
     */
    public function store(
        User $user,
        UploadedFile $file,
        string $variant,
        bool $saveToLibrary = false,
        ?string $name = null,
        int $libraryLimit = SkinSystemSettings::DEFAULT_LIBRARY_LIMIT,
    ): array {
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

        return DB::transaction(function () use (
            $user,
            $variant,
            $resolvedVariant,
            $path,
            $processed,
            $saveToLibrary,
            $name,
            $libraryLimit,
        ) {
            // The user row always exists and gives concurrent first uploads a
            // shared lock target before the unique skin row has been created.
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($saveToLibrary) {
                $saved = SavedSkin::query()
                    ->where('user_id', $user->getKey())
                    ->where('sha256', $processed['sha256'])
                    ->where('variant', $variant)
                    ->first();

                if ($saved === null && SavedSkin::query()->where('user_id', $user->getKey())->count() >= $libraryLimit) {
                    throw ValidationException::withMessages([
                        'skin' => trans('skinsystem::messages.validation.library_full', ['limit' => $libraryLimit]),
                    ]);
                }

                SavedSkin::query()->updateOrCreate(
                    [
                        'user_id' => $user->getKey(),
                        'sha256' => $processed['sha256'],
                        'variant' => $variant,
                    ],
                    [
                        'name' => $name ?: trans('skinsystem::messages.library.default_name'),
                        'file' => $path,
                        'resolved_variant' => $resolvedVariant,
                    ],
                );
            }

            return $this->persistActive($user, $path, $processed['sha256'], $variant, $resolvedVariant);
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Make one saved library entry the user's active server skin.
     *
     * @return array{skin: Skin, changed: bool}
     */
    public function activate(User $user, SavedSkin $savedSkin): array
    {
        return DB::transaction(function () use ($user, $savedSkin) {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $savedSkin = SavedSkin::query()
                ->whereKey($savedSkin->getKey())
                ->where('user_id', $user->getKey())
                ->firstOrFail();

            return $this->persistActive(
                $user,
                $savedSkin->file,
                $savedSkin->sha256,
                $savedSkin->variant,
                $savedSkin->resolved_variant,
            );
        }, self::TRANSACTION_ATTEMPTS);
    }

    public function deleteSaved(User $user, SavedSkin $savedSkin): void
    {
        DB::transaction(function () use ($user, $savedSkin) {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            SavedSkin::query()
                ->whereKey($savedSkin->getKey())
                ->where('user_id', $user->getKey())
                ->delete();
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * Remove the user's active skin and persist a recoverable clear operation.
     *
     * Immutable blobs are retained temporarily so a queued server command never
     * resolves to different bytes or a premature 404.
     */
    public function delete(User $user): ?SkinSyncState
    {
        return DB::transaction(function () use ($user) {
            User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            $skin = Skin::query()
                ->where('user_id', $user->getKey())
                ->first();

            if ($skin === null) {
                return null;
            }

            $previousState = SkinSyncState::query()
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->first();

            $this->forgetQueuedCommand($user, $previousState);

            $targetUuid = $previousState?->target_uuid
                ?? $this->commands->canonicalUuid($user->game_id);
            $targetServerId = $previousState?->target_server_id
                ?? $this->settings->serverId();

            $this->targets->beginClear(
                (int) $user->getKey(),
                $skin->revision,
                $targetUuid,
                $targetServerId,
            );

            $state = SkinSyncState::query()->updateOrCreate(
                ['user_id' => $user->getKey()],
                [
                    'action' => SkinSyncState::ACTION_CLEAR,
                    'skin_revision' => $skin->revision,
                    'status' => SkinSyncState::STATUS_PENDING,
                    'target_uuid' => $targetUuid,
                    'target_server_id' => $targetServerId,
                    'queued_command_id' => null,
                    'dispatched_at' => null,
                    'error' => null,
                ],
            );

            $skin->delete();

            return $state;
        }, self::TRANSACTION_ATTEMPTS);
    }

    /**
     * @return array{skin: Skin, changed: bool}
     */
    private function persistActive(
        User $user,
        string $path,
        string $sha256,
        string $variant,
        string $resolvedVariant,
    ): array {
        $previousState = SkinSyncState::query()
            ->where('user_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        $skin = Skin::query()->where('user_id', $user->getKey())->first();

        if ($skin !== null
            && hash_equals($skin->sha256, $sha256)
            && $skin->variant === $variant
            && $skin->resolved_variant === $resolvedVariant) {
            return ['skin' => $skin, 'changed' => false];
        }

        if ($skin === null) {
            $lastRevision = max(
                (int) SkinRevision::query()->where('user_id', $user->getKey())->max('revision'),
                (int) $previousState?->skin_revision,
            );
            $skin = new Skin(['user_id' => $user->getKey(), 'revision' => $lastRevision + 1]);
        } else {
            $skin->revision++;
        }

        $skin->fill([
            'file' => $path,
            'sha256' => $sha256,
            'variant' => $variant,
            'resolved_variant' => $resolvedVariant,
        ])->save();

        SkinRevision::query()->create([
            'user_id' => $user->getKey(),
            'revision' => $skin->revision,
            'file' => $skin->file,
            'sha256' => $skin->sha256,
            'resolved_variant' => $skin->resolved_variant,
        ]);

        if ($previousState !== null) {
            $this->targets->rememberPotential(
                (int) $user->getKey(),
                $previousState->target_uuid,
                $previousState->target_server_id,
            );
        }

        $this->forgetQueuedCommand($user, $previousState);
        $targetUuid = $this->commands->canonicalUuid($user->game_id);
        $targetServerId = $this->settings->serverId();
        $this->targets->activate((int) $user->getKey(), $targetUuid, $targetServerId);

        SkinSyncState::query()->updateOrCreate(
            ['user_id' => $user->getKey()],
            [
                'action' => SkinSyncState::ACTION_SET,
                'skin_revision' => $skin->revision,
                'status' => SkinSyncState::STATUS_PENDING,
                'target_uuid' => $targetUuid,
                'target_server_id' => $targetServerId,
                'queued_command_id' => null,
                'dispatched_at' => null,
                'error' => null,
            ],
        );

        return ['skin' => $skin, 'changed' => true];
    }

    private function forgetQueuedCommand(User $user, ?SkinSyncState $state = null): void
    {
        $state ??= SkinSyncState::query()
            ->where('user_id', $user->getKey())
            ->first();

        if ($state?->queued_command_id === null) {
            return;
        }

        ServerCommand::query()
            ->whereKey($state->queued_command_id)
            ->where('user_id', $user->getKey())
            ->when(
                $state->target_server_id !== null,
                fn ($query) => $query->where('server_id', $state->target_server_id),
            )
            ->delete();
    }
}
