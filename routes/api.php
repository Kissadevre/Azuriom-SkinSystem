<?php

use Illuminate\Support\Facades\Route;

Route::get('/', static fn () => response()->noContent())->name('index');

