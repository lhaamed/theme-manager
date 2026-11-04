<?php

namespace lhaamed\ThemeManager;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use lhaamed\ThemeManager\Console\MakeThemeCommand;

class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/theme.php', 'theme');
        $this->app->singleton(ThemeRegistry::class, fn ($app) => new ThemeRegistry($app['config'], $app->resourcePath('views')));
        $this->app->scoped(ThemeService::class, function ($app) {
            $service = new ThemeService($app->make(ThemeRegistry::class));
            $service->setPageTitlePrefix($app['config']->get('app.name'))
                ->setPageTitleSeparator($app['config']->get('theme.page_title_separator', '|'));
            $unit = $app['config']->get('theme.default_unit', 'web');
            if (isset($app->make(ThemeRegistry::class)->units()[$unit])) {
                $service->unit($unit);
            }
            return $service;
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakeThemeCommand::class]);
            $this->publishes([__DIR__.'/../config/theme.php' => $this->app->configPath('theme.php')], 'theme-config');
        }

        $registry = $this->app->make(ThemeRegistry::class);
        foreach ($registry->units() as $unit => $definition) {
            // A separate instance avoids initializing request state while booting.
            $service = (new ThemeService($registry))->unit($unit);
            foreach ($definition['themes'] as $name => $root) {
                $components = $service->getThemeComponentsPath($name);
                if ($components !== null && is_dir($components)) {
                    Blade::anonymousComponentPath($components, $name);
                }
                $livewire = $service->getThemeLivewirePath($name);
                if ($livewire !== null && is_dir($livewire) && $this->app->bound('livewire')) {
                    $manager = $this->app->make('livewire');
                    if (method_exists($manager, 'addNamespace')) {
                        $manager->addNamespace($name, viewPath: $livewire);
                    }
                }
            }
        }
    }
}
