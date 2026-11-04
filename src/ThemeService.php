<?php

namespace lhaamed\ThemeManager;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use RuntimeException;

class ThemeService
{
    private ?string $currentUnit = null;
    private ?string $currentTheme = null;
    private ?string $title = null;
    private ?string $prefix = null;
    private string $separator = '|';

    public function __construct(private ThemeRegistry $registry) {}

    public function unit(string $unit): static
    {
        $definition = $this->registry->units()[$unit] ?? throw new InvalidArgumentException("Unknown theme unit [{$unit}].");
        $theme = $this->currentTheme;
        if (! isset($definition['themes'][$theme])) {
            $theme = $definition['default'] ?? null;
        }
        if (! is_string($theme) || ! isset($definition['themes'][$theme])) {
            throw new InvalidArgumentException("Unit [{$unit}] has no valid default theme.");
        }
        $this->currentUnit = $unit;
        $this->currentTheme = $theme;
        if (array_key_exists('page_title_prefix', $definition)) {
            $this->prefix = $definition['page_title_prefix'];
        }
        return $this;
    }

    public function theme(string $theme): static
    {
        $this->registry->root($theme, $this->currentUnit);
        $this->currentTheme = $theme;
        return $this;
    }

    public function getCurrentThemeName(): ?string { return $this->currentTheme; }

    public function getThemeRoot(?string $theme = null): string
    {
        $theme ??= $this->currentTheme;
        if ($theme === null) {
            throw new RuntimeException('No active theme. Create a theme with theme:make and select its unit.');
        }
        return $this->registry->root($theme, $this->currentUnit);
    }

    public function getThemeRootPath(?string $theme = null): string
    {
        return $this->registry->path($this->getThemeRoot($theme));
    }

    public function getThemeConfig(?string $theme = null): array
    {
        $file = $this->getThemeRootPath($theme).'/config.php';
        if (! is_file($file)) {
            throw new RuntimeException("Missing theme manifest [{$file}].");
        }
        $config = require $file;
        if (! is_array($config)) {
            throw new RuntimeException("Theme manifest [{$file}] must return an array.");
        }
        return $config;
    }

    private function version(?string $theme): array
    {
        $config = $this->getThemeConfig($theme);
        $root = $config['roots'][$config['last_stable_version'] ?? ''] ?? null;
        // Support the original project's shorthand manifest too.
        if (is_string($root)) { $root = ['index' => $root]; }
        if (! is_array($root) || ! isset($root['index'])) {
            throw new RuntimeException('The stable theme version must define an index view.');
        }
        return $root;
    }

    public function getThemeInitiator(?string $theme = null): string { return $this->version($theme)['index']; }
    public function renderActiveTheme(): string { return $this->getThemeInitiator(); }

    private function localPath(string $key, ?string $theme): ?string
    {
        $path = $this->version($theme)[$key] ?? null;
        return $path === null ? null : $this->registry->path($this->getThemeRoot($theme).'/'.$path);
    }

    public function getThemeComponentsPath(?string $theme = null): ?string { return $this->localPath('components_local_path', $theme); }
    public function getThemeLivewirePath(?string $theme = null): ?string { return $this->localPath('livewire_components_path', $theme); }

    public function viewToString(string $directory, ?string $theme = null): string
    {
        return str_replace('/', '.', $this->getThemeRoot($theme).'.'.$directory);
    }

    public function view(string $directory, array $compact = []): View { return view($this->viewToString($directory), $compact); }

    public function paginationPath(?string $theme = null): string
    {
        $path = $this->version($theme)['pagination'] ?? throw new RuntimeException('This theme has no pagination view.');
        return $this->viewToString($path, $theme);
    }

    public function getAllThemes(): array
    {
        $themes = [];
        foreach ($this->registry->units() as $unit) { $themes = array_merge($themes, array_keys($unit['themes'])); }
        return array_values(array_unique($themes));
    }

    public function setPageTitlePrefix(?string $prefix = null): static { $this->prefix = $prefix; return $this; }
    public function setPageTitleSeparator(string $separator): static { $this->separator = $separator; return $this; }
    public function setPageTitle(?string $title = null): static { $this->title = $title; return $this; }
    public function getPageTitle(): string
    {
        return implode(' '.$this->separator.' ', array_values(array_filter([$this->prefix, $this->title], fn ($part) => $part !== null && $part !== '')));
    }
}
