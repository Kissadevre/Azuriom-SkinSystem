<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Exceptions\MineSkinApiException;
use Azuriom\Plugin\SkinSystem\Models\MineSkinGeneration;
use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Services\MineSkinCapeCatalog;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the authenticated user's skin page.
     */
    public function index(
        Request $request,
        SkinSystemSettings $settings,
        MineSkinCapeCatalog $capeCatalog,
    ) {
        $userId = $request->user()->getKey();

        $libraryEnabled = $request->user()->can('skinsystem.library');
        $capeSelectionEnabled = $settings->capeSelectionEnabled()
            && $request->user()->can('skinsystem.cape');
        $capes = [];
        $capeCatalogUnavailable = false;

        if ($capeSelectionEnabled) {
            try {
                $capes = $capeCatalog->all();
            } catch (MineSkinApiException) {
                $capeCatalogUnavailable = true;
            }
        }

        $skin = Skin::query()->where('user_id', $userId)->first();
        $capeMap = collect($capes)->keyBy('uuid');

        return view('skinsystem::index', [
            'skin' => $skin,
            'syncState' => SkinSyncState::query()->where('user_id', $userId)->first(),
            'savedSkins' => $libraryEnabled
                ? SavedSkin::query()->where('user_id', $userId)->latest()->get()
                : collect(),
            'libraryEnabled' => $libraryEnabled,
            'libraryLimit' => $settings->libraryLimit(),
            'capeSelectionEnabled' => $capeSelectionEnabled,
            'capeCatalogUnavailable' => $capeCatalogUnavailable,
            'capes' => $capes,
            'capeMap' => $capeMap,
            'activeCape' => $skin?->cape_id !== null ? $capeMap->get($skin->cape_id) : null,
            'mineSkinGeneration' => $skin === null
                ? null
                : MineSkinGeneration::query()
                    ->where('user_id', $userId)
                    ->where('skin_revision', $skin->revision)
                    ->first(),
        ]);
    }
}
