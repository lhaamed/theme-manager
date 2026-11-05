<?php

namespace lhaamed\ThemeManager;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use Illuminate\Foundation\ViteException;
use Illuminate\Foundation\ViteManifestNotFoundException;

class ThemeAssets
{
    public function __construct(private ThemeRegistry $registry) {}

    public function render(string $root, string $name, string $mode): HtmlString
    {
        ThemeRegistry::validateName($name);
        $directory = $this->registry->path($root);
        $styles = $directory.'/assets/css/'.$name.'-styles.scss';
        $scripts = $directory.'/assets/js/'.$name.'-scripts.js';

        if ($mode === 'vite') {
            $base = rtrim(str_replace('\\', '/', base_path()), '/').'/';
            $entries = array_map(fn ($path) => substr(str_replace('\\', '/', $path), strlen($base)), [$styles, $scripts]);
            try {
                return new HtmlString(app(Vite::class)($entries)->toHtml());
            } catch (ViteException|ViteManifestNotFoundException $exception) {
                // The CSS-compatible starter is usable before the first npm build.
                if (! app()->environment('local', 'testing')) {
                    throw $exception;
                }
            }
        }

        // Inline mode intentionally supports plain CSS in the .scss starter file
        // and dependency-free JS. Sass syntax/imports require a bundler.
        $css = file_get_contents($styles);
        $js = file_get_contents($scripts);
        return new HtmlString('<style>'.str_ireplace('</style', '<\\/style', $css).'</style>'
            .'<script defer>'.str_ireplace('</script', '<\\/script', $js).'</script>');
    }
}
