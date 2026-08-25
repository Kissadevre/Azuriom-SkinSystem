<?php

use Azuriom\Plugin\SkinSystem\Controllers\Api\SkinImageController;
use Illuminate\Support\Facades\Route;

Route::get('/skins/{user}/{revision}-{hash}.png', [SkinImageController::class, 'show'])
    ->where('user', '[1-9][0-9]{0,9}')
    ->where('revision', '[1-9][0-9]{0,9}')
    ->where('hash', '[a-f0-9]{64}')
    ->name('skins.show');
