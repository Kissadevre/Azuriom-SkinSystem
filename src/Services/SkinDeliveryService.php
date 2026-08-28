<?php

namespace Azuriom\Plugin\SkinSystem\Services;

use Azuriom\Models\User;
use Azuriom\Plugin\SkinSystem\Models\MineSkinGeneration;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Support\SyncResult;

class SkinDeliveryService
{
    public function __construct(
        private readonly SkinSystemSettings $settings,
        private readonly MineSkinGenerationManager $generations,
        private readonly SkinSynchronizer $synchronizer,
    ) {}

    public function apply(Skin $skin, User $user, bool $retryFailed = false): SyncResult
    {
        if ($skin->delivery_strategy !== SkinSystemSettings::DELIVERY_MINESKIN) {
            return $this->synchronizer->apply($skin, $user);
        }

        if (! $this->settings->enabled()) {
            return $this->synchronizer->apply($skin, $user);
        }

        $generation = $this->generations->ensure($skin, $retryFailed);

        return $this->finishOrReport($skin, $user, $generation);
    }

    public function advanceAndApply(Skin $skin, User $user): SyncResult
    {
        if ($skin->delivery_strategy !== SkinSystemSettings::DELIVERY_MINESKIN) {
            return $this->synchronizer->apply($skin, $user);
        }

        $generation = $this->generations->advance($skin);

        return $this->finishOrReport($skin, $user, $generation);
    }

    private function finishOrReport(
        Skin $skin,
        User $user,
        MineSkinGeneration $generation,
    ): SyncResult {
        if ($generation->isComplete()) {
            return $this->synchronizer->apply($skin, $user, $generation->result_url);
        }

        $status = $generation->status === MineSkinGeneration::STATUS_FAILED
            ? SkinSyncState::STATUS_FAILED
            : SkinSyncState::STATUS_PENDING;
        $error = $generation->error ?? 'mineskin_processing';

        SkinSyncState::query()
            ->where('user_id', $skin->user_id)
            ->where('action', SkinSyncState::ACTION_SET)
            ->where('skin_revision', $skin->revision)
            ->update([
                'status' => $status,
                'error' => $error,
                'dispatched_at' => null,
            ]);

        return new SyncResult($status, $error);
    }
}
