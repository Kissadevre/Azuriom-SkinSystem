<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\Server;
use Azuriom\Models\ServerCommand;
use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Exceptions\StaleSyncOperationException;
use Azuriom\Plugin\SkinSystem\Exceptions\SyncPreconditionException;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncTarget;
use Azuriom\Plugin\SkinSystem\Support\SyncResult;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class SkinSynchronizer
{
    private const TRANSACTION_ATTEMPTS = 3;

    public function __construct(
        private readonly SkinSystemSettings $settings,
        private readonly SkinsRestorerCommandBuilder $commands,
        private readonly SkinSyncTargetRegistry $targets,
    ) {}

    /**
     * Submit the active skin revision to the configured Minecraft bridge.
     */
    public function apply(Skin $skin, User $user, ?string $sourceUrl = null): SyncResult
    {
        $state = $this->currentSetState($skin, $user);

        if ($state === null) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        }

        if (! $this->settings->enabled()) {
            return $this->record($state, SkinSyncState::STATUS_NOT_CONFIGURED, 'sync_disabled');
        }

        $target = $this->resolveTarget($state, $user);

        if ($target instanceof SyncResult) {
            return $target;
        }

        try {
            $command = $this->commands->setSkin(
                $skin,
                $target['value'],
                $sourceUrl,
                $target['type'],
            );
        } catch (SyncPreconditionException $exception) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, $exception->reason);
        }

        if (! $this->operationIsCurrent($state) || $this->currentSetState($skin, $user) === null) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        }

        return $this->dispatchSet($state, $target['server'], $user, $command);
    }

    /**
     * Submit compensating CLEAR commands to every destination that may have
     * observed a SkinSystem SET, including commands fetched before cancellation.
     */
    public function clear(User $user, ?int $skinRevision = null): SyncResult
    {
        $stateQuery = SkinSyncState::query()
            ->where('user_id', $user->getKey())
            ->where('action', SkinSyncState::ACTION_CLEAR);

        if ($skinRevision !== null) {
            $stateQuery->where('skin_revision', $skinRevision);
        }

        $state = $stateQuery->first();

        if ($state === null) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        }

        if (! $this->settings->enabled()) {
            return $this->record($state, SkinSyncState::STATUS_NOT_CONFIGURED, 'sync_disabled');
        }

        $targets = $this->prepareClearTargets($state, $user);

        if ($targets instanceof SyncResult) {
            return $targets;
        }

        $results = [];

        foreach ($targets as $target) {
            if (! $this->operationIsCurrent($state)) {
                return new SyncResult(SyncResult::STALE, 'stale_revision');
            }

            $result = $this->dispatchClearTarget($state, $target, $user);

            if ($result->status === SyncResult::STALE) {
                return $result;
            }

            $results[] = $result;
        }

        $aggregate = $this->aggregateClearResults($results);

        return $this->record(
            $state,
            $aggregate->status,
            $aggregate->error,
            collect($results)->contains(
                fn (SyncResult $result) => in_array($result->status, [
                    SkinSyncState::STATUS_SUBMITTED,
                    SkinSyncState::STATUS_UNCERTAIN,
                ], true),
            ),
        );
    }

    private function currentSetState(Skin $skin, User $user): ?SkinSyncState
    {
        $skinExists = Skin::query()
            ->whereKey($skin->getKey())
            ->where('user_id', $user->getKey())
            ->where('revision', $skin->revision)
            ->exists();

        if (! $skinExists) {
            return null;
        }

        return SkinSyncState::query()
            ->where('user_id', $user->getKey())
            ->where('action', SkinSyncState::ACTION_SET)
            ->where('skin_revision', $skin->revision)
            ->first();
    }

    private function operationIsCurrent(SkinSyncState $state): bool
    {
        return $this->operationQuery($state)->exists();
    }

    /**
     * Resolve immutable dispatch targets, binding missing legacy values once.
     *
     * @return array{uuid: string, type: string, value: string, server: Server}|SyncResult
     */
    private function resolveTarget(SkinSyncState $state, User $user): array|SyncResult
    {
        $identity = $this->resolveTargetIdentity($state, $user);

        if ($identity instanceof SyncResult) {
            return $identity;
        }

        $server = $this->settings->findServer($identity['server_id']);

        if ($server === null) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, 'server_unavailable');
        }

        return [
            'uuid' => $identity['uuid'],
            'type' => $identity['type'],
            'value' => $identity['value'],
            'server' => $server,
        ];
    }

    /**
     * @return array{uuid: string, type: string, value: string, server_id: int}|SyncResult
     */
    private function resolveTargetIdentity(SkinSyncState $state, User $user): array|SyncResult
    {
        $targetUuid = $state->target_uuid;

        if ($targetUuid === null) {
            $targetUuid = $this->commands->canonicalUuid($user->game_id);

            if ($targetUuid === null) {
                return $this->record($state, SkinSyncState::STATUS_FAILED, 'invalid_game_id');
            }

            if (! $this->bindMissingTarget($state, 'target_uuid', $targetUuid)) {
                return new SyncResult(SyncResult::STALE, 'stale_revision');
            }
        }

        if ($this->commands->canonicalUuid($targetUuid) !== $targetUuid) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, 'invalid_game_id');
        }

        $targetType = $state->target_type ?: $this->settings->applicationTarget();

        if (! in_array($targetType, SkinSystemSettings::applicationTargets(), true)) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, 'invalid_application_target');
        }

        $targetValue = $state->target_value;

        if ($targetValue === null) {
            $targetValue = $targetType === SkinSystemSettings::TARGET_USERNAME
                ? $this->commands->canonicalUsername($user->name)
                : $targetUuid;

            if ($targetValue === null) {
                return $this->record($state, SkinSyncState::STATUS_FAILED, 'invalid_game_username');
            }

            if (! $this->bindMissingTarget($state, 'target_value', $targetValue)) {
                return new SyncResult(SyncResult::STALE, 'stale_revision');
            }
        }

        try {
            $targetValue = $this->commands->validatedTarget($targetValue, $targetType);
        } catch (SyncPreconditionException $exception) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, $exception->reason);
        }

        $targetServerId = $state->target_server_id;

        if ($targetServerId !== null
            && ($targetServerId < 1 || $targetServerId > SkinSystemSettings::MAX_DATABASE_ID)) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, 'server_unavailable');
        }

        if ($targetServerId === null) {
            $targetServerId = $this->settings->serverId();

            if ($targetServerId === null) {
                return $this->record($state, SkinSyncState::STATUS_FAILED, 'server_unavailable');
            }

            if (! $this->bindMissingTarget($state, 'target_server_id', $targetServerId)) {
                return new SyncResult(SyncResult::STALE, 'stale_revision');
            }
        }

        return [
            'uuid' => $targetUuid,
            'type' => $targetType,
            'value' => $targetValue,
            'server_id' => $targetServerId,
        ];
    }

    private function bindMissingTarget(SkinSyncState $state, string $attribute, string|int $value): bool
    {
        $updated = SkinSyncState::query()
            ->whereKey($state->getKey())
            ->where('user_id', $state->user_id)
            ->where('action', $state->action)
            ->where('skin_revision', $state->skin_revision)
            ->whereNull($attribute)
            ->update([$attribute => $value]);

        if ($updated === 1) {
            $state->setAttribute($attribute, $value);

            return true;
        }

        $fresh = SkinSyncState::query()->find($state->getKey());

        if ($fresh === null
            || $fresh->user_id !== $state->user_id
            || $fresh->action !== $state->action
            || $fresh->skin_revision !== $state->skin_revision
            || $fresh->getAttribute($attribute) !== $value) {
            return false;
        }

        $state->setAttribute($attribute, $value);

        return true;
    }

    private function dispatchSet(
        SkinSyncState $state,
        Server $server,
        User $user,
        string $command,
    ): SyncResult {
        if ($server->type === 'mc-azlink') {
            return $this->queueAzLinkSet($state, $server, $user, $command);
        }

        $prepared = $this->prepareSetTarget($state, $user);

        if ($prepared instanceof SyncResult) {
            return $prepared;
        }

        $clearMayBeInFlight = $prepared;

        try {
            $server->bridge()->sendCommands([$command], $user, false);
        } catch (Throwable $exception) {
            report($exception);

            return $this->record(
                $state,
                SkinSyncState::STATUS_UNCERTAIN,
                'dispatch_exception',
                true,
            );
        }

        return $clearMayBeInFlight
            ? $this->record(
                $state,
                SkinSyncState::STATUS_UNCERTAIN,
                'clear_may_be_in_flight',
                true,
            )
            : $this->record($state, SkinSyncState::STATUS_SUBMITTED, null, true);
    }

    /**
     * Persist the possible SET destination before crossing the RCON boundary.
     */
    private function prepareSetTarget(SkinSyncState $state, User $user): bool|SyncResult
    {
        try {
            $clearMayBeInFlight = DB::transaction(function () use ($state, $user) {
                $this->lockUser($user);
                $current = $this->lockCurrentOperation($state);

                if ($current === null) {
                    throw new StaleSyncOperationException;
                }

                $this->forgetQueuedSet($current, $user);

                $target = $this->targets->activate(
                    (int) $user->getKey(),
                    $current->target_uuid,
                    $current->target_server_id,
                    $current->target_type,
                    $current->target_value,
                );

                if ($target === null) {
                    throw new SyncPreconditionException('invalid_game_id');
                }

                if ($current->queued_command_id !== null
                    && $this->operationQuery($current)->update(['queued_command_id' => null]) !== 1) {
                    throw new StaleSyncOperationException;
                }

                return $target->clear_may_be_in_flight;
            }, self::TRANSACTION_ATTEMPTS);

            $state->queued_command_id = null;
        } catch (StaleSyncOperationException) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        } catch (SyncPreconditionException $exception) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, $exception->reason);
        } catch (Throwable $exception) {
            report($exception);

            return $this->record($state, SkinSyncState::STATUS_FAILED, 'queue_cleanup_failed');
        }

        return $clearMayBeInFlight;
    }

    /**
     * Atomically replace only the AzLink SET row owned by the current operation.
     */
    private function queueAzLinkSet(
        SkinSyncState $state,
        Server $server,
        User $user,
        string $command,
    ): SyncResult {
        try {
            $attributes = DB::transaction(function () use ($state, $server, $user, $command) {
                $this->lockUser($user);
                $current = $this->lockCurrentOperation($state);

                if ($current === null) {
                    throw new StaleSyncOperationException;
                }

                $this->forgetQueuedSet($current, $user);

                $target = $this->targets->activate(
                    (int) $user->getKey(),
                    $current->target_uuid,
                    $current->target_server_id,
                    $current->target_type,
                    $current->target_value,
                );

                if ($target === null) {
                    throw new SyncPreconditionException('invalid_game_id');
                }

                $queued = $server->commands()->create([
                    'command' => $command,
                    'user_id' => $user->getKey(),
                    'need_online' => false,
                ]);

                $status = $target->clear_may_be_in_flight
                    ? SkinSyncState::STATUS_UNCERTAIN
                    : SkinSyncState::STATUS_SUBMITTED;
                $error = $target->clear_may_be_in_flight
                    ? 'clear_may_be_in_flight'
                    : null;

                $attributes = [
                    ...$this->recordAttributes(
                        $status,
                        $error,
                        true,
                    ),
                    'queued_command_id' => $queued->getKey(),
                ];

                if ($this->operationQuery($current)->update($attributes) !== 1) {
                    throw new StaleSyncOperationException;
                }

                return $attributes;
            }, self::TRANSACTION_ATTEMPTS);

            // Mutate the captured model only after COMMIT succeeds. Laravel may
            // rerun the transaction closure after a transient database failure.
            $state->forceFill($attributes);
        } catch (StaleSyncOperationException) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        } catch (SyncPreconditionException $exception) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, $exception->reason);
        } catch (Throwable $exception) {
            report($exception);

            return $this->record($state, SkinSyncState::STATUS_FAILED, 'queue_dispatch_failed');
        }

        $this->notifyAzLink($server);

        return new SyncResult($attributes['status'], $attributes['error']);
    }

    /**
     * @return Collection<int, SkinSyncTarget>|SyncResult
     */
    private function prepareClearTargets(SkinSyncState $state, User $user): Collection|SyncResult
    {
        $hasTargets = SkinSyncTarget::query()
            ->where('user_id', $user->getKey())
            ->exists();
        $fallbackUuid = null;
        $fallbackServerId = null;
        $fallbackType = null;
        $fallbackValue = null;

        if ($this->validStoredIdentity(
            $state->target_uuid,
            $state->target_server_id,
            $state->target_type,
            $state->target_value,
        )) {
            $fallbackUuid = $state->target_uuid;
            $fallbackServerId = $state->target_server_id;
            $fallbackType = $state->target_type;
            $fallbackValue = $state->target_value;
        } elseif (! $hasTargets) {
            $identity = $this->resolveTargetIdentity($state, $user);

            if ($identity instanceof SyncResult) {
                return $identity;
            }

            $fallbackUuid = $identity['uuid'];
            $fallbackServerId = $identity['server_id'];
            $fallbackType = $identity['type'];
            $fallbackValue = $identity['value'];
        }

        try {
            $targets = DB::transaction(function () use (
                $state,
                $user,
                $fallbackUuid,
                $fallbackServerId,
                $fallbackType,
                $fallbackValue,
            ) {
                $this->lockUser($user);
                $current = $this->lockCurrentOperation($state);

                if ($current === null) {
                    throw new StaleSyncOperationException;
                }

                $targets = $this->targets->ensureClear(
                    (int) $user->getKey(),
                    (int) $current->skin_revision,
                    $fallbackUuid,
                    $fallbackServerId,
                    $fallbackType,
                    $fallbackValue,
                );

                if ($targets->isEmpty()) {
                    throw new SyncPreconditionException('server_unavailable');
                }

                return $targets;
            }, self::TRANSACTION_ATTEMPTS);
        } catch (StaleSyncOperationException) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        } catch (SyncPreconditionException $exception) {
            return $this->record($state, SkinSyncState::STATUS_FAILED, $exception->reason);
        } catch (Throwable $exception) {
            report($exception);

            return $this->record($state, SkinSyncState::STATUS_FAILED, 'queue_dispatch_failed');
        }

        return $targets;
    }

    private function dispatchClearTarget(
        SkinSyncState $state,
        SkinSyncTarget $target,
        User $user,
    ): SyncResult {
        if ($this->commands->canonicalUuid($target->target_uuid) !== $target->target_uuid) {
            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                'invalid_game_id',
            );
        }

        try {
            $commandTarget = $this->commands->validatedTarget(
                $target->target_value ?? $target->target_uuid,
                $target->target_type,
            );
        } catch (SyncPreconditionException $exception) {
            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                $exception->reason,
            );
        }

        if ($target->target_server_id < 1
            || $target->target_server_id > SkinSystemSettings::MAX_DATABASE_ID) {
            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                'server_unavailable',
            );
        }

        $server = $this->settings->findServer($target->target_server_id);

        if ($server === null) {
            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                'server_unavailable',
            );
        }

        try {
            $command = $this->commands->clearSkin($commandTarget, $target->target_type);
        } catch (SyncPreconditionException $exception) {
            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                $exception->reason,
            );
        }

        if ($server->type === 'mc-azlink') {
            return $this->queueAzLinkClear($state, $target, $server, $user, $command);
        }

        $prepared = $this->prepareClearTarget($state, $target, $user);

        if ($prepared !== null) {
            return $prepared;
        }

        try {
            $server->bridge()->sendCommands([$command], $user, false);
        } catch (Throwable $exception) {
            report($exception);

            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_UNCERTAIN,
                SkinSyncState::STATUS_UNCERTAIN,
                'dispatch_exception',
                true,
            );
        }

        return $this->recordClearTarget(
            $state,
            $target,
            $user,
            SkinSyncTarget::STATUS_CLEAR_SUBMITTED,
            SkinSyncState::STATUS_SUBMITTED,
            attempted: true,
        );
    }

    private function prepareClearTarget(
        SkinSyncState $state,
        SkinSyncTarget $target,
        User $user,
    ): ?SyncResult {
        try {
            DB::transaction(function () use ($state, $target, $user) {
                $this->lockUser($user);
                $currentState = $this->lockCurrentOperation($state);

                if ($currentState === null) {
                    throw new StaleSyncOperationException;
                }

                $currentTarget = $this->lockCurrentClearTarget($target, $currentState);

                if ($currentTarget === null) {
                    throw new StaleSyncOperationException;
                }

                $this->targets->forgetQueuedClear($currentTarget);

                $clearMayBeInFlight = $currentTarget->clear_may_be_in_flight
                    || $currentTarget->queued_clear_command_id !== null
                    || $currentTarget->status === SkinSyncTarget::STATUS_CLEAR_UNCERTAIN;

                if ($currentTarget->queued_clear_command_id !== null
                    || $clearMayBeInFlight !== $currentTarget->clear_may_be_in_flight) {
                    $currentTarget->forceFill([
                        'queued_clear_command_id' => null,
                        'clear_may_be_in_flight' => $clearMayBeInFlight,
                    ])->save();
                }
            }, self::TRANSACTION_ATTEMPTS);

            $target->queued_clear_command_id = null;
        } catch (StaleSyncOperationException) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        } catch (Throwable $exception) {
            report($exception);

            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                'queue_cleanup_failed',
            );
        }

        return null;
    }

    private function queueAzLinkClear(
        SkinSyncState $state,
        SkinSyncTarget $target,
        Server $server,
        User $user,
        string $command,
    ): SyncResult {
        try {
            $attributes = DB::transaction(function () use ($state, $target, $server, $user, $command) {
                $this->lockUser($user);
                $currentState = $this->lockCurrentOperation($state);

                if ($currentState === null) {
                    throw new StaleSyncOperationException;
                }

                $currentTarget = $this->lockCurrentClearTarget($target, $currentState);

                if ($currentTarget === null) {
                    throw new StaleSyncOperationException;
                }

                $this->targets->forgetQueuedClear($currentTarget);

                $queued = $server->commands()->create([
                    'command' => $command,
                    'user_id' => $user->getKey(),
                    'need_online' => false,
                ]);

                $attributes = [
                    'status' => SkinSyncTarget::STATUS_CLEAR_SUBMITTED,
                    'queued_clear_command_id' => $queued->getKey(),
                    'clear_may_be_in_flight' => true,
                    'dispatched_at' => now(),
                    'error' => null,
                ];

                $currentTarget->forceFill($attributes)->save();

                return $attributes;
            }, self::TRANSACTION_ATTEMPTS);

            $target->forceFill($attributes);
        } catch (StaleSyncOperationException) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        } catch (Throwable $exception) {
            report($exception);

            return $this->recordClearTarget(
                $state,
                $target,
                $user,
                SkinSyncTarget::STATUS_CLEAR_FAILED,
                SkinSyncState::STATUS_FAILED,
                'queue_dispatch_failed',
            );
        }

        $this->notifyAzLink($server);

        return new SyncResult(SkinSyncState::STATUS_SUBMITTED);
    }

    private function recordClearTarget(
        SkinSyncState $state,
        SkinSyncTarget $target,
        User $user,
        string $targetStatus,
        string $resultStatus,
        ?string $error = null,
        bool $attempted = false,
    ): SyncResult {
        try {
            $attributes = DB::transaction(function () use (
                $state,
                $target,
                $user,
                $targetStatus,
                $error,
                $attempted,
            ) {
                $this->lockUser($user);
                $currentState = $this->lockCurrentOperation($state);

                if ($currentState === null) {
                    throw new StaleSyncOperationException;
                }

                $currentTarget = $this->lockCurrentClearTarget($target, $currentState);

                if ($currentTarget === null) {
                    throw new StaleSyncOperationException;
                }

                $attributes = [
                    'status' => $targetStatus,
                    'error' => $error,
                ];

                if ($attempted) {
                    $attributes['dispatched_at'] = now();
                }

                if ($targetStatus === SkinSyncTarget::STATUS_CLEAR_UNCERTAIN) {
                    $attributes['clear_may_be_in_flight'] = true;
                }

                $currentTarget->forceFill($attributes)->save();

                return $attributes;
            }, self::TRANSACTION_ATTEMPTS);

            $target->forceFill($attributes);
        } catch (StaleSyncOperationException) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        } catch (Throwable $exception) {
            report($exception);

            return new SyncResult(SkinSyncState::STATUS_FAILED, 'queue_dispatch_failed');
        }

        return new SyncResult($resultStatus, $error);
    }

    /**
     * @param  array<int, SyncResult>  $results
     */
    private function aggregateClearResults(array $results): SyncResult
    {
        if ($results === []) {
            return new SyncResult(SkinSyncState::STATUS_FAILED, 'server_unavailable');
        }

        foreach ([SkinSyncState::STATUS_UNCERTAIN, SkinSyncState::STATUS_FAILED] as $status) {
            foreach ($results as $result) {
                if ($result->status === $status) {
                    return $result;
                }
            }
        }

        return new SyncResult(SkinSyncState::STATUS_SUBMITTED);
    }

    /**
     * Remove a SET queue row only when its exact ID is recorded by SkinSystem.
     */
    private function forgetQueuedSet(SkinSyncState $state, User $user): void
    {
        if ($state->queued_command_id === null) {
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

    private function notifyAzLink(Server $server): void
    {
        if (! ($server->data['azlink-ping'] ?? true)) {
            return;
        }

        try {
            $bridge = $server->bridge();

            if (method_exists($bridge, 'sendServerRequest')) {
                $bridge->sendServerRequest();
            }
        } catch (Throwable $exception) {
            // The durable queue row is committed. A later AzLink poll can still
            // deliver it, so notification failure does not change submission.
            report($exception);
        }
    }

    private function lockUser(User $user): void
    {
        User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
    }

    private function lockCurrentOperation(SkinSyncState $state): ?SkinSyncState
    {
        return $this->operationQuery($state)->lockForUpdate()->first();
    }

    private function lockCurrentClearTarget(
        SkinSyncTarget $target,
        SkinSyncState $state,
    ): ?SkinSyncTarget {
        return SkinSyncTarget::query()
            ->whereKey($target->getKey())
            ->where('user_id', $state->user_id)
            ->where('target_uuid', $target->target_uuid)
            ->where('target_type', $target->target_type)
            ->where('target_value', $target->target_value)
            ->where('target_server_id', $target->target_server_id)
            ->where('clear_revision', $state->skin_revision)
            ->lockForUpdate()
            ->first();
    }

    private function record(
        SkinSyncState $state,
        string $status,
        ?string $error = null,
        bool $attempted = false,
    ): SyncResult {
        $attributes = $this->recordAttributes($status, $error, $attempted);
        $updated = $this->operationQuery($state)->update($attributes);
        $stillCurrent = $updated === 1 || $this->operationIsCurrent($state);

        if (! $stillCurrent) {
            return new SyncResult(SyncResult::STALE, 'stale_revision');
        }

        $state->forceFill($attributes);

        return new SyncResult($status, $error);
    }

    /**
     * @return array<string, mixed>
     */
    private function recordAttributes(
        string $status,
        ?string $error,
        bool $attempted,
    ): array {
        $attributes = [
            'status' => $status,
            'error' => $error,
        ];

        if ($attempted) {
            $attributes['dispatched_at'] = now();
        }

        return $attributes;
    }

    private function validStoredIdentity(
        ?string $uuid,
        ?int $serverId,
        ?string $targetType,
        ?string $targetValue,
    ): bool {
        if ($uuid === null
            || $this->commands->canonicalUuid($uuid) !== $uuid
            || $serverId === null
            || $serverId < 1
            || $serverId > SkinSystemSettings::MAX_DATABASE_ID) {
            return false;
        }

        try {
            return $targetType !== null
                && $targetValue !== null
                && $this->commands->validatedTarget($targetValue, $targetType) === $targetValue;
        } catch (SyncPreconditionException) {
            return false;
        }
    }

    private function operationQuery(SkinSyncState $state)
    {
        return SkinSyncState::query()
            ->whereKey($state->getKey())
            ->where('user_id', $state->user_id)
            ->where('action', $state->action)
            ->where('skin_revision', $state->skin_revision);
    }
}
