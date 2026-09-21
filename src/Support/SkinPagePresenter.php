<?php

namespace Azuriom\Plugin\SkinSystem\Support;

use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;

final class SkinPagePresenter
{
    /**
     * Translate synchronization state into theme-compatible Bootstrap tokens.
     *
     * @return array{
     *     status: string,
     *     badge_class: string,
     *     clear_alert_class: string,
     *     error_alert_class: string
     * }
     */
    public function sync(?SkinSyncState $state): array
    {
        $status = $state?->status ?? SkinSyncState::STATUS_PENDING;

        return [
            'status' => $status,
            'badge_class' => match ($status) {
                SkinSyncState::STATUS_SUBMITTED => 'success',
                SkinSyncState::STATUS_FAILED => 'danger',
                SkinSyncState::STATUS_UNCERTAIN => 'warning',
                SkinSyncState::STATUS_PENDING => 'info',
                default => 'secondary',
            },
            'clear_alert_class' => match ($status) {
                SkinSyncState::STATUS_SUBMITTED => 'success',
                SkinSyncState::STATUS_FAILED => 'danger',
                default => 'warning',
            },
            'error_alert_class' => $status === SkinSyncState::STATUS_FAILED
                ? 'danger'
                : 'warning',
        ];
    }

    /**
     * Return the Bootstrap Icon assigned to each supported arm variant.
     *
     * @return array<string, string>
     */
    public function variantIcons(): array
    {
        return [
            Skin::VARIANT_AUTO => 'bi-stars',
            Skin::VARIANT_CLASSIC => 'bi-person-standing',
            Skin::VARIANT_SLIM => 'bi-person',
        ];
    }
}
