<?php

namespace lhaamed\ThemeManager;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Throwable;

class ViteInstaller
{
    public function __construct(private Filesystem $files) {}

    /** Only standard static plugin arrays are patched; custom configs are left intact. */
    public function install(string $base, string $directory): bool
    {
        $files = $this->files;
        $packagePath = $base.'/package.json';
        if (! $files->isFile($packagePath)) {
            return false;
        }
        $package = json_decode($files->get($packagePath), true, flags: JSON_THROW_ON_ERROR);
        if (! isset($package['devDependencies']['laravel-vite-plugin']) && ! isset($package['dependencies']['laravel-vite-plugin'])) {
            return false;
        }
        $configPath = null;
        foreach (['vite.config.js', 'vite.config.ts', 'vite.config.mjs', 'vite.config.mts'] as $candidate) {
            if ($files->isFile($base.'/'.$candidate)) {
                $configPath = $base.'/'.$candidate;
                break;
            }
        }
        if ($configPath === null) {
            return false;
        }
        $config = $files->get($configPath);
        $marker = '// theme-manager: automatic theme assets';
        $helper = $base.'/theme-manager.vite.mjs';
        if (! str_contains($config, $marker)) {
            // Be conservative: do not guess how dynamic or custom configurations work.
            if (! preg_match('/^\s*export default defineConfig\(\s*\{/m', $config)
                || preg_match_all('/^\h*plugins\h*:\h*\[/m', $config) !== 1
                || str_contains($config, 'themeManagerAssets')
                || $files->exists($helper)) {
                return false;
            }
            $call = 'themeManagerAssets({ directory: '.json_encode($directory, JSON_UNESCAPED_SLASHES).' }),';
            $config = preg_replace('/^(\h*plugins\h*:\h*\[)/m', '$1'."\n            ".$call, $config, 1);
            $config = $marker."\nimport themeManagerAssets from './theme-manager.vite.mjs';\n".$config;
        } elseif (! $files->isFile($helper) || ! str_contains($files->get($helper), '@lhaamed/theme-manager')) {
            throw new RuntimeException('The theme-manager Vite helper is missing or replaced; restore it before generating Vite themes.');
        }

        $changes = [
            $configPath => $config,
            $helper => $files->get(__DIR__.'/../resources/vite-plugin.mjs'),
        ];
        if (! isset($package['dependencies']['sass']) && ! isset($package['dependencies']['sass-embedded'])
            && ! isset($package['devDependencies']['sass']) && ! isset($package['devDependencies']['sass-embedded'])) {
            $package['devDependencies']['sass'] = '^1.77.0';
            $changes[$packagePath] = json_encode($package, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
        }
        $backups = [];
        try {
            foreach ($changes as $path => $contents) {
                $backups[$path] = $files->isFile($path) ? $files->get($path) : null;
                if ($files->put($path, $contents) === false) {
                    throw new RuntimeException("Cannot write [{$path}].");
                }
            }
        } catch (Throwable $exception) {
            foreach ($backups as $path => $contents) {
                $contents === null ? $files->delete($path) : $files->put($path, $contents);
            }
            throw $exception;
        }
        return true;
    }
}
