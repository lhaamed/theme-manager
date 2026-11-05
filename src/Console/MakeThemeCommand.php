<?php

namespace lhaamed\ThemeManager\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use lhaamed\ThemeManager\ThemeRegistry;
use lhaamed\ThemeManager\ViteInstaller;
use Throwable;

class MakeThemeCommand extends Command
{
    protected $signature = 'theme:make {name : Lowercase theme name} {--unit=web : Theme unit}
        {--assets=auto : auto, vite or inline; auto connects standard Laravel Vite configs}';
    protected $description = 'Create a discoverable theme with a manifest, layout, page, components and assets';

    public function handle(ThemeRegistry $registry, Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        $unit = (string) $this->option('unit');
        $assetMode = (string) $this->option('assets');
        try {
            if (! in_array($assetMode, ['auto', 'vite', 'inline'], true)) {
                throw new InvalidArgumentException('The --assets option must be auto, vite or inline.');
            }
            ThemeRegistry::validateName($name);
            ThemeRegistry::validateName($unit);
            $root = $registry->directory().'/'.$name;
            $target = $registry->path($root);
            foreach ($registry->units() as $definition) {
                if (isset($definition['themes'][$name])) {
                    throw new InvalidArgumentException("Theme [{$name}] is already registered.");
                }
            }
            if ($files->exists($target)) {
                throw new InvalidArgumentException("Theme directory [{$target}] already exists; no files were changed.");
            }
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $viewRoot = str_replace('/', '.', $root);
        $manifest = [
            'name' => $name,
            'unit' => $unit,
            'versions' => ['1-0-0'],
            'last_stable_version' => '1-0-0',
            'roots' => ['1-0-0' => [
                'index' => $viewRoot.'.layouts.index',
                'components_local_path' => 'components',
                'livewire_components_path' => 'livewire',
                'pagination' => 'components.pagination.default',
            ]],
        ];
        try {
            foreach (['layouts', 'pages', 'includes', 'components/pagination', 'livewire', 'assets/css', 'assets/js', 'assets/images'] as $directory) {
                $files->makeDirectory($target.'/'.$directory, 0755, true, true);
                $files->put($target.'/'.$directory.'/.gitkeep', '');
            }
            foreach ($files->files(__DIR__.'/../../stubs') as $stub) {
                $destination = [
                    'layout.stub' => 'layouts/index.blade.php',
                    'home.stub' => 'pages/home.blade.php',
                    'pagination.stub' => 'components/pagination/default.blade.php',
                    'header.stub' => 'includes/header.blade.php',
                    'footer.stub' => 'includes/footer.blade.php',
                    'favicon.stub' => 'includes/favicon.blade.php',
                    'assets.stub' => 'includes/assets.blade.php',
                    'styles.stub' => 'assets/css/'.$name.'-styles.scss',
                    'scripts.stub' => 'assets/js/'.$name.'-scripts.js',
                ][$stub->getFilename()] ?? null;
                if ($destination !== null) {
                    $files->put($target.'/'.$destination, str_replace(['{{viewRoot}}', '{{name}}', '{{root}}'], [$viewRoot, $name, $root], $files->get($stub->getPathname())));
                }
            }
            $vite = false;
            if ($assetMode !== 'inline') {
                $vite = (new ViteInstaller($files))->install($this->laravel->basePath(), 'resources/views/'.$registry->directory());
                if ($assetMode === 'vite' && ! $vite) {
                    throw new InvalidArgumentException('No supported Laravel Vite configuration found. Use --assets=inline or configure your custom build manually.');
                }
            }
            $manifest['assets'] = ['mode' => $vite ? 'vite' : 'inline'];
            $files->put($target.'/config.php', "<?php\n\nreturn ".var_export($manifest, true).";\n");
            // Git only needs placeholders in directories that remain empty.
            foreach ($files->allFiles($target, true) as $file) {
                if ($file->getFilename() !== '.gitkeep') {
                    continue;
                }
                $directory = $file->getPath();
                $placeholder = $directory.'/.gitkeep';
                if (count($files->files($directory, true)) > 1 || count($files->directories($directory)) > 0) {
                    $files->delete($placeholder);
                }
            }
        } catch (Throwable $exception) {
            // Only this command's newly created directory is removed on failure.
            $files->deleteDirectory($target);
            $this->error('Theme creation failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Theme [{$name}] created in [{$target}] for unit [{$unit}].");
        $this->line("Use Theme::unit('{$unit}')->theme('{$name}')->view('pages.home').");
        $this->line("Local preview: /_themes/{$unit}/{$name} (APP_ENV=local).");
        if ($vite) {
            $this->line('Vite connected. Run npm install, then npm run dev (or npm run build). Restart an already running Vite server.');
            $this->line('The default local preview also works before the first build.');
        } else {
            $this->line('Inline starter assets enabled; no frontend build is needed.');
        }
        if ($this->laravel->configurationIsCached()) {
            $this->warn('Configuration is cached. Rebuild it after changing published theme configuration.');
        }
        return self::SUCCESS;
    }
}
