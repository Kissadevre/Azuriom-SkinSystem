<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\ServerCommand;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncTarget;
use Illuminate\Database\Eloquent\Collection;

/**
 * Conservatively records every command target/server pair that may hold a custom skin.
 *
 * Callers must hold the user's database row lock before invoking mutating
 * methods. This keeps the lock order consistent across lifecycle and bridge
 * operations: user, global sync state, targets, then owned command rows.
 */
class SkinSyncTargetRegistry
{
    public function __construct(
        private readonly SkinsRestorerCommandBuilder $commands,
    ) {}

    public function rememberPotential(
        int $userId,
        ?string $targetUuid,
        ?int $targetServerId,
        ?string $targetType = null,
        ?string $targetValue = null,
    ): ?SkinSyncTarget {
        $targetType ??= SkinSystemSettings::TARGET_UUID;
        $targetValue ??= $targetUuid;

        if (! $this->validTarget($targetUuid, $targetServerId, $targetType, $targetValue)) {
            return null;
        }

        $target = $this->targetQuery($userId, $targetServerId, $targetType, $targetValue)
            ->lockForUpdate()
            ->first();

        if ($target !== null) {
            return $target;
        }

        $legacyTarget = SkinSyncTarget::query()
            ->where('user_id', $userId)
            ->where('target_uuid', $targetUuid)
            ->where('target_server_id', $targetServerId)
            ->whereNull('target_value')
            ->lockForUpdate()
            ->first();

        if ($legacyTarget !== null) {
            $legacyTarget->forceFill([
                'target_type' => $targetType,
                'target_value' => $targetValue,
            ])->save();

            return $legacyTarget;
        }

        return SkinSyncTarget::query()->create([
            'user_id' => $userId,
            'target_uuid' => $targetUuid,
            'target_type' => $targetType,
            'target_value' => $targetValue,
            'target_server_id' => $targetServerId,
            'status' => SkinSyncTarget::STATUS_POSSIBLE_ACTIVE,
        ]);
    }

    /**
     * Make a target current for SET and cancel only its tracked CLEAR row.
     */
    public function activate(
        int $userId,
        ?string $targetUuid,
        ?int $targetServerId,
        ?string $targetType = null,
        ?string $targetValue = null,
    ): ?SkinSyncTarget {
        $target = $this->rememberPotential(
            $userId,
            $targetUuid,
            $targetServerId,
            $targetType,
            $targetValue,
        );

        if ($target === null) {
            return null;
        }

        $clearMayBeInFlight = $target->clear_may_be_in_flight
            || $target->queued_clear_command_id !== null
            || $target->status === SkinSyncTarget::STATUS_CLEAR_UNCERTAIN;

        $this->forgetQueuedClear($target);

        $target->forceFill([
            'status' => SkinSyncTarget::STATUS_POSSIBLE_ACTIVE,
            'clear_revision' => null,
            'queued_clear_command_id' => null,
            'clear_may_be_in_flight' => $clearMayBeInFlight,
            'dispatched_at' => null,
            'error' => null,
        ])->save();

        return $target;
    }

    /**
     * Start a new compensating CLEAR generation for every possible target.
     * Existing queue IDs are retained until dispatch atomically replaces them.
     *
     * @return Collection<int, SkinSyncTarget>
     */
    public function beginClear(
        int $userId,
        int $revision,
        ?string $fallbackUuid = null,
        ?int $fallbackServerId = null,
        ?string $fallbackType = null,
        ?string $fallbackValue = null,
    ): Collection {
        $this->rememberPotential(
            $userId,
            $fallbackUuid,
            $fallbackServerId,
            $fallbackType,
            $fallbackValue,
        );

        $targets = $this->lockedTargets($userId);

        foreach ($targets as $target) {
            $target->forceFill([
                'status' => SkinSyncTarget::STATUS_CLEAR_PENDING,
                'clear_revision' => $revision,
                'dispatched_at' => null,
                'error' => null,
            ])->save();
        }

        return $targets;
    }

    /**
     * Include any legacy or newly discovered target in the current CLEAR.
     * Targets already attempted for this generation retain their result.
     *
     * @return Collection<int, SkinSyncTarget>
     */
    public function ensureClear(
        int $userId,
        int $revision,
        ?string $fallbackUuid = null,
        ?int $fallbackServerId = null,
        ?string $fallbackType = null,
        ?string $fallbackValue = null,
    ): Collection {
        $this->rememberPotential(
            $userId,
            $fallbackUuid,
            $fallbackServerId,
            $fallbackType,
            $fallbackValue,
        );

        $targets = $this->lockedTargets($userId);

        foreach ($targets as $target) {
            if ($target->clear_revision === $revision) {
                continue;
            }

            $target->forceFill([
                'status' => SkinSyncTarget::STATUS_CLEAR_PENDING,
                'clear_revision' => $revision,
                'dispatched_at' => null,
                'error' => null,
            ])->save();
        }

        return $targets;
    }

    public function forgetQueuedClear(SkinSyncTarget $target): void
    {
        if ($target->queued_clear_command_id === null) {
            return;
        }

        ServerCommand::query()
            ->whereKey($target->queued_clear_command_id)
            ->where('user_id', $target->user_id)
            ->where('server_id', $target->target_server_id)
            ->delete();
    }

    private function validTarget(
        ?string $targetUuid,
        ?int $targetServerId,
        string $targetType,
        ?string $targetValue,
    ): bool {
        return $targetUuid !== null
            && $this->commands->canonicalUuid($targetUuid) === $targetUuid
            && $targetValue !== null
            && $this->validCommandTarget($targetValue, $targetType)
            && $targetServerId !== null
            && $targetServerId >= 1
            && $targetServerId <= SkinSystemSettings::MAX_DATABASE_ID;
    }

    private function validCommandTarget(string $targetValue, string $targetType): bool
    {
        try {
            return $this->commands->validatedTarget($targetValue, $targetType) === $targetValue;
        } catch (\Azuriom\Plugin\SkinSystem\Exceptions\SyncPreconditionException) {
            return false;
        }
    }

    private function targetQuery(
        int $userId,
        int $targetServerId,
        string $targetType,
        string $targetValue,
    ) {
        return SkinSyncTarget::query()
            ->where('user_id', $userId)
            ->where('target_type', $targetType)
            ->where('target_value', $targetValue)
            ->where('target_server_id', $targetServerId);
    }

    /**
     * @return Collection<int, SkinSyncTarget>
     */
    private function lockedTargets(int $userId): Collection
    {
        return SkinSyncTarget::query()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
