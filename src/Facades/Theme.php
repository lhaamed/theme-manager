<?php

namespace lhaamed\ThemeManager\Facades;

use Illuminate\Support\Facades\Facade;
use lhaamed\ThemeManager\ThemeService;

/** @mixin ThemeService */
class Theme extends Facade
{
    protected static function getFacadeAccessor(): string { return ThemeService::class; }
}
