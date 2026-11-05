<?php

use Illuminate\Support\Facades\Route;
use lhaamed\ThemeManager\Http\PreviewController;

Route::middleware('web')->get('/_themes/{unit}/{theme}', PreviewController::class)
    ->where(['unit' => '[a-z][a-z0-9_-]*', 'theme' => '[a-z][a-z0-9_-]*'])
    ->name('theme-manager.preview');
