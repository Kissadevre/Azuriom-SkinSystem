<?php

use Azuriom\Plugin\SkinSystem\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

Route::middleware('can:skinsystem.admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::put('/', [AdminController::class, 'update'])->name('update');
});
