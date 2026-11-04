<?php

namespace lhaamed\ThemeManager;

use Illuminate\Contracts\Config\Repository;
use InvalidArgumentException;

class ThemeRegistry
{
    public function __construct(private Repository $config, private string $viewsPath) {}

    public static function validateName(string $name): void
    {
        if (! preg_match('/\A[a-z][a-z0-9_-]*\z/D', $name)) {
            throw new InvalidArgumentException('Names must start with a lowercase letter and contain only lowercase letters, numbers, underscores or hyphens.');
        }
    }

    public function directory(): string
    {
        $directory = str_replace('\\', '/', $this->config->get('theme.directory', 'themes'));
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~D', $directory)) {
            throw new InvalidArgumentException('theme.directory must be a relative directory inside resources/views.');
        }
        return $directory;
    }

    public function path(string $root): string
    {
        $root = str_replace('\\', '/', $root);
        if (! preg_match('~\A[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*\z~D', $root)) {
            throw new InvalidArgumentException('Theme roots must be relative paths inside resources/views.');
        }
        return $this->viewsPath.'/'.$root;
    }

    public function units(): array
    {
        $units = [];
        foreach (glob($this->path($this->directory()).'/*/config.php') ?: [] as $file) {
            $manifest = require $file;
            if (! is_array($manifest) || ! isset($manifest['unit'])) {
                continue;
            }
            $name = basename(dirname($file));
            $unit = $manifest['unit'];
            self::validateName($name);
            self::validateName($unit);
            $units[$unit]['themes'][$name] = $this->directory().'/'.$name;
            $units[$unit]['default'] ??= $name;
        }
        return array_replace_recursive($units, $this->config->get('theme.all_available_units', []));
    }

    public function root(string $theme, ?string $unit = null): string
    {
        $units = $this->units();
        if ($unit !== null) {
            return $units[$unit]['themes'][$theme] ?? throw new InvalidArgumentException("Unknown theme [{$theme}] in unit [{$unit}].");
        }
        $roots = [];
        foreach ($units as $definition) {
            if (isset($definition['themes'][$theme])) {
                $roots[] = $definition['themes'][$theme];
            }
        }
        $roots = array_unique($roots);
        if (count($roots) !== 1) {
            throw new InvalidArgumentException("Theme [{$theme}] is unknown or ambiguous; select a unit first.");
        }
        return reset($roots);
    }
}
