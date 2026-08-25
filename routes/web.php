<?php

use Azuriom\Plugin\SkinSystem\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:skinsystem.skin'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('index');
});

