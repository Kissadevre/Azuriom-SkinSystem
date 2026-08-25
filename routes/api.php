<?php

use Azuriom\Plugin\SkinSystem\Controllers\Api\SkinImageController;
use Illuminate\Support\Facades\Route;

Route::get('/skins/{user}/{revision}-{hash}.png', [SkinImageController::class, 'show'])
    ->whereNumber('user')
    ->whereNumber('revision')
    ->where('hash', '[a-f0-9]{64}')
    ->name('skins.show');
