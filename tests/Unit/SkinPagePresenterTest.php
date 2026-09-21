<?php

namespace Azuriom\Plugin\SkinSystem\Tests\Unit;

use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Support\SkinPagePresenter;
use PHPUnit\Framework\TestCase;

class SkinPagePresenterTest extends TestCase
{
    public function test_sync_states_map_to_stable_bootstrap_tokens(): void
    {
        $presenter = new SkinPagePresenter;
        $expected = [
            SkinSyncState::STATUS_SUBMITTED => ['success', 'success', 'warning'],
            SkinSyncState::STATUS_FAILED => ['danger', 'danger', 'danger'],
            SkinSyncState::STATUS_UNCERTAIN => ['warning', 'warning', 'warning'],
            SkinSyncState::STATUS_PENDING => ['info', 'warning', 'warning'],
            SkinSyncState::STATUS_NOT_CONFIGURED => ['secondary', 'warning', 'warning'],
        ];

        foreach ($expected as $status => [$badgeClass, $clearAlertClass, $errorAlertClass]) {
            $presentation = $presenter->sync(new SkinSyncState(['status' => $status]));

            $this->assertSame($status, $presentation['status']);
            $this->assertSame($badgeClass, $presentation['badge_class']);
            $this->assertSame($clearAlertClass, $presentation['clear_alert_class']);
            $this->assertSame($errorAlertClass, $presentation['error_alert_class']);
        }
    }

    public function test_missing_and_unknown_states_use_safe_fallbacks(): void
    {
        $presenter = new SkinPagePresenter;

        $this->assertSame([
            'status' => SkinSyncState::STATUS_PENDING,
            'badge_class' => 'info',
            'clear_alert_class' => 'warning',
            'error_alert_class' => 'warning',
        ], $presenter->sync(null));

        $this->assertSame([
            'status' => 'unexpected',
            'badge_class' => 'secondary',
            'clear_alert_class' => 'warning',
            'error_alert_class' => 'warning',
        ], $presenter->sync(new SkinSyncState(['status' => 'unexpected'])));
    }

    public function test_supported_variants_have_centralized_icons(): void
    {
        $this->assertSame([
            Skin::VARIANT_AUTO => 'bi-stars',
            Skin::VARIANT_CLASSIC => 'bi-person-standing',
            Skin::VARIANT_SLIM => 'bi-person',
        ], (new SkinPagePresenter)->variantIcons());
    }
}
