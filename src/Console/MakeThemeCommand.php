<?php

namespace lhaamed\ThemeManager\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use lhaamed\ThemeManager\ThemeRegistry;
use Throwable;

class MakeThemeCommand extends Command
{
    protected $signature = 'theme:make {name : Lowercase theme name} {--unit=web : Theme unit}';
    protected $description = 'Create a discoverable theme with a manifest, layout, page, components and assets';

    public function handle(ThemeRegistry $registry, Filesystem $files): int
    {
        $name = (string) $this->argument('name');
        $unit = (string) $this->option('unit');
        try {
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
            $files->put($target.'/config.php', "<?php\n\nreturn ".var_export($manifest, true).";\n");
            foreach ($files->files(__DIR__.'/../../stubs') as $stub) {
                $destination = [
                    'layout.stub' => 'layouts/index.blade.php',
                    'home.stub' => 'pages/home.blade.php',
                    'pagination.stub' => 'components/pagination/default.blade.php',
                ][$stub->getFilename()] ?? null;
                if ($destination !== null) {
                    $files->put($target.'/'.$destination, str_replace(['{{viewRoot}}', '{{name}}'], [$viewRoot, $name], $files->get($stub->getPathname())));
                }
            }
            $files->put($target.'/assets/css/theme.css', "/* {$name} theme styles */\n");
            $files->put($target.'/assets/js/theme.js', "// {$name} theme scripts\n");
        } catch (Throwable $exception) {
            // Only this command's newly created directory is removed on failure.
            $files->deleteDirectory($target);
            $this->error('Theme creation failed: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info("Theme [{$name}] created in [{$target}] for unit [{$unit}].");
        $this->line("Use Theme::unit('{$unit}')->theme('{$name}')->view('pages.home').");
        if ($this->laravel->configurationIsCached()) {
            $this->warn('Configuration is cached. Rebuild it after changing published theme configuration.');
        }
        return self::SUCCESS;
    }
}
