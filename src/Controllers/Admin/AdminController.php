<?php

namespace Azuriom\Plugin\SkinSystem\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Requests\UpdateSettingsRequest;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Illuminate\Http\RedirectResponse;

class AdminController extends Controller
{
    /**
     * Show the SkinSystem administration page.
     */
    public function index(SkinSystemSettings $settings)
    {
        $statusCounts = SkinSyncState::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $baseUrl = rtrim((string) config('app.url'), '/');

        return view('skinsystem::admin.index', [
            'servers' => $settings->availableServers(),
            'syncEnabled' => $settings->enabled(),
            'serverId' => $settings->serverId(),
            'totalSkins' => Skin::query()->count(),
            'submittedSkins' => (int) $statusCounts->get(SkinSyncState::STATUS_SUBMITTED, 0),
            'attentionSkins' => (int) $statusCounts->get(SkinSyncState::STATUS_FAILED, 0)
                + (int) $statusCounts->get(SkinSyncState::STATUS_UNCERTAIN, 0),
            'publicEndpoint' => $baseUrl.'/api/skinsystem/skins/{user}/{revision}-{sha256}.png',
            'httpsReady' => str_starts_with(strtolower($baseUrl), 'https://'),
        ]);
    }

    /**
     * Save the authoritative server synchronization settings.
     */
    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Setting::updateSettings([
            SkinSystemSettings::ENABLED_KEY => $request->boolean('sync_enabled'),
            SkinSystemSettings::SERVER_KEY => isset($data['server_id']) ? (int) $data['server_id'] : null,
        ]);

        return back()->with('success', trans('skinsystem::admin.updated'));
    }
}
