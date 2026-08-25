<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Models\SavedSkin;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Azuriom\Plugin\SkinSystem\Services\SkinSystemSettings;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the authenticated user's skin page.
     */
    public function index(Request $request, SkinSystemSettings $settings)
    {
        $userId = $request->user()->getKey();

        $libraryEnabled = $request->user()->can('skinsystem.library');

        return view('skinsystem::index', [
            'skin' => Skin::query()->where('user_id', $userId)->first(),
            'syncState' => SkinSyncState::query()->where('user_id', $userId)->first(),
            'savedSkins' => $libraryEnabled
                ? SavedSkin::query()->where('user_id', $userId)->latest()->get()
                : collect(),
            'libraryEnabled' => $libraryEnabled,
            'libraryLimit' => $settings->libraryLimit(),
        ]);
    }
}
