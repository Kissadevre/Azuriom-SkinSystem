<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Azuriom\Plugin\SkinSystem\Models\SkinSyncState;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the authenticated user's skin page.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->getKey();

        return view('skinsystem::index', [
            'skin' => Skin::query()->where('user_id', $userId)->first(),
            'syncState' => SkinSyncState::query()->where('user_id', $userId)->first(),
        ]);
    }
}
