<?php

use Azuriom\Plugin\SkinSystem\Controllers\HomeController;
use Azuriom\Plugin\SkinSystem\Controllers\SkinController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:skinsystem.skin'])->group(function () {
    Route::get('/', [HomeController::class, 'index'])->name('index');
    Route::post('/skin', [SkinController::class, 'store'])
        ->middleware('throttle:6,1,skinsystem-upload')
        ->name('skins.store');
    Route::post('/skin/sync', [SkinController::class, 'sync'])
        ->middleware('throttle:3,1,skinsystem-sync')
        ->name('skins.sync');
    Route::delete('/skin', [SkinController::class, 'destroy'])->name('skins.destroy');
});
