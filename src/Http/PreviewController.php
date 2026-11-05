<?php

namespace lhaamed\ThemeManager\Http;

use Illuminate\Contracts\View\View;
use lhaamed\ThemeManager\Facades\Theme;
use lhaamed\ThemeManager\ThemeRegistry;
use InvalidArgumentException;

class PreviewController
{
    public function __invoke(string $unit, string $theme): View
    {
        abort_unless(app()->environment('local') && config('theme.preview', true), 404);
        try {
            ThemeRegistry::validateName($unit);
            ThemeRegistry::validateName($theme);
            Theme::unit($unit)->theme($theme)->setPageTitle(ucfirst($theme).' preview');
        } catch (InvalidArgumentException) {
            abort(404);
        }
        abort_unless(view()->exists(Theme::viewToString('pages.home')), 404);
        return Theme::view('pages.home');
    }
}
