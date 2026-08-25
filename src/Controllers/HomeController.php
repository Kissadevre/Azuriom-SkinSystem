<?php

namespace Azuriom\Plugin\SkinSystem\Controllers;

use Azuriom\Http\Controllers\Controller;

class HomeController extends Controller
{
    /**
     * Show the authenticated user's skin page.
     */
    public function index()
    {
        return view('skinsystem::index');
    }
}

