<?php

namespace Azuriom\Plugin\SkinSystem\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Models\Setting;
use Azuriom\Plugin\SkinSystem\Exceptions\MineSkinApiException;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Requests\UpdateSettingsRequest;
use Azuriom\Plugin\SkinSystem\Services\MineSkinClient;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemDebugLogger;
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
            'debugEnabled' => $settings->debugEnabled(),
            'serverId' => $settings->serverId(),
            'libraryLimit' => $settings->libraryLimit(),
            'showInUserMenu' => $settings->showInUserMenu(),
            'userMenuIcon' => $settings->userMenuIcon(),
            'deliveryMode' => $settings->deliveryMode(),
            'applicationTarget' => $settings->applicationTarget(),
            'hasMineSkinApiKey' => $settings->hasMineSkinApiKey(),
            'mineSkinCapesGranted' => $settings->mineSkinCapesGranted(),
            'totalSkins' => Skin::query()->count(),
            'submittedSkins' => (int) $statusCounts->get(SkinSyncState::STATUS_SUBMITTED, 0),
            'attentionSkins' => (int) $statusCounts->get(SkinSyncState::STATUS_FAILED, 0)
                + (int) $statusCounts->get(SkinSyncState::STATUS_UNCERTAIN, 0),
            'publicEndpoint' => $baseUrl.'/api/skinsystem/skins/{user}/{revision}-{sha256}.png',
            'httpsReady' => str_starts_with(strtolower($baseUrl), 'https://'),
        ]);
    }

    /**
     * Show the requirements and operational guidance for synchronization.
     */
    public function information()
    {
        return view('skinsystem::admin.information');
    }

    /**
     * Save the authoritative server synchronization settings.
     */
    public function update(
        UpdateSettingsRequest $request,
        SkinSystemSettings $settings,
        MineSkinClient $mineSkin,
        SkinSystemDebugLogger $logger,
    ): RedirectResponse {
        $data = $request->validated();
        $debugWasEnabled = $settings->debugEnabled();
        $debugWillBeEnabled = $request->boolean('debug_enabled');

        if ($debugWasEnabled) {
            $logger->info('Administrator requested a settings update.', [
                'administrator_id' => $request->user()?->getAuthIdentifier(),
                'debug_enabled' => $debugWillBeEnabled,
            ]);
        }

        $newApiKey = trim((string) ($data['mineskin_api_key'] ?? ''));
        $removeApiKey = $request->boolean('remove_mineskin_api_key');
        $mineSkinSettings = [];

        if ($newApiKey !== '') {
            try {
                $capabilities = $mineSkin->verifyApiKey($newApiKey);
            } catch (MineSkinApiException $exception) {
                return back()
                    ->withInput($request->except('mineskin_api_key'))
                    ->withErrors([
                        'mineskin_api_key' => trans('skinsystem::admin.validation.mineskin_'.$exception->reason),
                    ]);
            }

            $mineSkinSettings = [
                SkinSystemSettings::MINESKIN_API_KEY_KEY => $newApiKey,
                SkinSystemSettings::MINESKIN_VERIFIED_AT_KEY => now()->toIso8601String(),
                SkinSystemSettings::MINESKIN_CAPES_GRANTED_KEY => $capabilities['capes'],
            ];
        } elseif ($removeApiKey) {
            $mineSkinSettings = [
                SkinSystemSettings::MINESKIN_API_KEY_KEY => null,
                SkinSystemSettings::MINESKIN_VERIFIED_AT_KEY => null,
                SkinSystemSettings::MINESKIN_CAPES_GRANTED_KEY => null,
            ];
        }

        Setting::updateSettings(array_merge([
            SkinSystemSettings::ENABLED_KEY => $request->boolean('sync_enabled'),
            SkinSystemSettings::DEBUG_ENABLED_KEY => $debugWillBeEnabled,
            SkinSystemSettings::SERVER_KEY => isset($data['server_id']) ? (int) $data['server_id'] : null,
            SkinSystemSettings::LIBRARY_LIMIT_KEY => (int) $data['library_limit'],
            SkinSystemSettings::USER_MENU_ENABLED_KEY => $request->boolean('user_menu_enabled'),
            SkinSystemSettings::USER_MENU_ICON_KEY => $data['user_menu_icon'],
            SkinSystemSettings::DELIVERY_MODE_KEY => $data['delivery_mode'],
            SkinSystemSettings::APPLICATION_TARGET_KEY => $data['application_target'],
        ], $mineSkinSettings));

        if ($debugWillBeEnabled) {
            $logger->info('Settings updated.', [
                'administrator_id' => $request->user()?->getAuthIdentifier(),
                'sync_enabled' => $request->boolean('sync_enabled'),
                'server_id' => isset($data['server_id']) ? (int) $data['server_id'] : null,
                'delivery_mode' => $data['delivery_mode'],
                'application_target' => $data['application_target'],
                'api_key_changed' => $newApiKey !== '' || $removeApiKey,
            ]);
        }

        return back()->with('success', trans('skinsystem::admin.updated'));
    }
}
