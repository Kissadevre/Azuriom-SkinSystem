<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;
use Azuriom\Plugin\SkinSystem\Models\Skin;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    /**
     * Show the authenticated user's skin page.
     */
    public function index(Request $request)
    {
        return view('skinsystem::index', [
            'skin' => Skin::query()->where('user_id', $request->user()->getKey())->first(),
        ]);
    }
}
