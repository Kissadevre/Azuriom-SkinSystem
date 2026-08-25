<?php

namespace Azuriom\Plugin\SkinSystem\Controllers\Admin;

use Azuriom\Http\Controllers\Controller;

class AdminController extends Controller
{
    /**
     * Show the SkinSystem administration page.
     */
    public function index()
    {
        return view('skinsystem::admin.index');
    }
}

