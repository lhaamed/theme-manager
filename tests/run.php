<?php

// Runs independently after composer install, or against the host's dependencies.
$autoload = is_file(__DIR__.'/../vendor/autoload.php')
    ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';
$loader = require $autoload;
// A separate loader also overrides an installed host package's optimized classmap.
$sourceLoader = new Composer\Autoload\ClassLoader;
$sourceLoader->addPsr4('lhaamed\\ThemeManager\\', __DIR__.'/../src');
$sourceLoader->register(true);

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use lhaamed\ThemeManager\Console\MakeThemeCommand;
use lhaamed\ThemeManager\ThemeRegistry;
use lhaamed\ThemeManager\ThemeService;
use lhaamed\ThemeManager\ThemeServiceProvider;
use lhaamed\ThemeManager\Http\PreviewController;
use Illuminate\Foundation\Vite;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

$files = new Filesystem;
$base = sys_get_temp_dir().'/theme-manager-'.bin2hex(random_bytes(6));
$files->makeDirectory($base.'/resources/views', 0755, true);
$files->makeDirectory($base.'/storage/views', 0755, true);
$checks = 0;
function check(bool $condition, string $message): void
{
    global $checks;
    if (! $condition) { throw new RuntimeException($message); }
    $checks++;
}

try {
    $app = new Application($base);
    $app->instance('env', 'testing');
    $app->instance('config', new Repository([
        'app' => ['name' => 'Test Application', 'locale' => 'en'],
        'view' => ['paths' => [$base.'/resources/views'], 'compiled' => $base.'/storage/views'],
    ]));
    Facade::setFacadeApplication($app);
    $app->register(Illuminate\Events\EventServiceProvider::class);
    $app->register(Illuminate\Filesystem\FilesystemServiceProvider::class);
    $app->register(Illuminate\View\ViewServiceProvider::class);
    $app->register(ThemeServiceProvider::class);
    $app->boot();
    $run = function (array $arguments) use ($app): int {
        $command = new MakeThemeCommand;
        $command->setLaravel($app);
        return $command->run(new ArrayInput($arguments), new BufferedOutput);
    };
    check($run(['name' => 'ocean']) === 0, 'Generate first theme');
    check($run(['name' => 'forest']) === 0, 'Generate second theme');
    check($run(['name' => 'dashboard', '--unit' => 'admin']) === 0, 'Generate another unit');
    $manifest = $files->get($base.'/resources/views/themes/ocean/config.php');
    check($run(['name' => 'ocean']) === 1, 'Reject duplicate theme');
    check($manifest === $files->get($base.'/resources/views/themes/ocean/config.php'), 'Preserve existing theme');
    check($run(['name' => '../escape']) === 1, 'Reject traversal');
    check($run(['name' => 'safe', '--unit' => '../escape']) === 1, 'Reject invalid unit');
    $theme = $app->make(ThemeService::class);
    // Resolve after generation to test default selection in a fresh scope.
    check($theme->getCurrentThemeName() === 'forest', 'Deterministic alphabetical default');
    $theme->unit('web')->theme('ocean');
    check($theme->getThemeRoot() === 'themes/ocean', 'Theme names remain names, not paths');
    check($theme->viewToString('pages/home') === 'themes.ocean.pages.home', 'Resolve view');
    check($theme->paginationPath('forest') === 'themes.forest.components.pagination.default', 'Resolve explicit pagination theme');
    check(str_contains($theme->view('pages.home')->render(), 'Welcome to ocean'), 'Render generated Blade page');
    $html = $theme->view('pages.home')->render();
    check(str_contains($html, '<style>') && str_contains($html, '.theme-hero'), 'Render styled starter without a frontend build');
    check(str_contains($html, 'JavaScript is working. Welcome to ocean!'), 'Render interactive starter script');
    check(str_contains($html, 'data:image/svg+xml'), 'Render favicon include');
    check($files->isFile($base.'/resources/views/themes/ocean/assets/css/ocean-styles.scss'), 'Styles use theme name and SCSS extension');
    check($files->isFile($base.'/resources/views/themes/ocean/assets/js/ocean-scripts.js'), 'Scripts use theme name');
    check(! $files->exists($base.'/resources/views/themes/ocean/pages/.gitkeep'), 'No redundant placeholders');
    check($files->exists($base.'/resources/views/themes/ocean/livewire/.gitkeep'), 'Keep empty component directory');
    check($theme->setPageTitle('Home')->getPageTitle() === 'Test Application | Home', 'Compose title');
    $theme->unit('admin');
    check($theme->getCurrentThemeName() === 'dashboard', 'Switch unit default');
    try { $theme->theme('ocean'); check(false, 'Invalid selection must fail'); }
    catch (InvalidArgumentException) { check(true, 'Reject theme outside selected unit'); }
    $app->forgetScopedInstances();
    check($app->make(ThemeService::class)->getPageTitle() === 'Test Application', 'Reset state between scopes');
    $app['config']->set('theme.all_available_units.web.default', 'ocean');
    check((new ThemeService($app->make(ThemeRegistry::class)))->unit('web')->getCurrentThemeName() === 'ocean', 'Explicit config overrides discovery');
    // Existing project manifests need no unit metadata when explicitly registered.
    $files->makeDirectory($base.'/resources/views/legacy', 0755, true);
    $files->put($base.'/resources/views/legacy/config.php', "<?php return ['last_stable_version'=>'1-0-0','roots'=>['1-0-0'=>'legacy.layout']];");
    $app['config']->set('theme.all_available_units.legacy', ['default' => 'old', 'themes' => ['old' => 'legacy']]);
    check($theme->unit('legacy')->getThemeInitiator() === 'legacy.layout', 'Support original shorthand manifest');
    // Boot registration after themes exist, without Livewire installed.
    (new ThemeServiceProvider($app))->boot();
    check(count($app['blade.compiler']->getAnonymousComponentPaths()) === 3, 'Register anonymous Blade components');
    $app->instance('env', 'local');
    check(str_contains((new PreviewController)('web', 'ocean')->render(), 'Welcome to ocean'), 'Local preview renders immediately');
    $app['config']->set('theme.preview', false);
    try { (new PreviewController)('web', 'ocean'); check(false, 'Disabled preview must fail'); }
    catch (HttpException $exception) { check($exception->getStatusCode() === 404, 'Preview switch enforced'); }
    $app['config']->set('theme.preview', true);
    $app->instance('env', 'production');
    try { (new PreviewController)('web', 'ocean'); check(false, 'Production preview must fail'); }
    catch (HttpException $exception) { check($exception->getStatusCode() === 404, 'Preview denied in production even if route is cached'); }
    $app->instance('env', 'testing');
    check($run(['name' => 'invalid', '--assets' => 'other']) === 1, 'Reject invalid asset mode');
    check($run(['name' => 'novite', '--assets' => 'vite']) === 1, 'Explicit Vite mode fails without supported config');
    check(! $files->exists($base.'/resources/views/themes/novite'), 'Failed scaffolding rolls back theme directory');
    $packageJson = json_encode(['type' => 'module', 'devDependencies' => ['laravel-vite-plugin' => '^1.0']]);
    $files->put($base.'/package.json', $packageJson);
    $viteConfig = "import { defineConfig } from 'vite';\nexport default defineConfig({\n    plugins: [\n        laravel({input: ['resources/js/app.js']}),\n    ],\n});\n";
    $files->put($base.'/vite.config.js', $viteConfig);
    check($run(['name' => 'sunset']) === 0, 'Auto-connect standard Laravel Vite project');
    $connectedConfig = $files->get($base.'/vite.config.js');
    check(str_contains($connectedConfig, "themeManagerAssets({ directory: \"resources/views/themes\" })"), 'Install discoverable theme Vite plugin');
    check(str_contains($connectedConfig, "laravel({input: ['resources/js/app.js']})"), 'Preserve existing Vite inputs and plugins');
    check(isset(json_decode($files->get($base.'/package.json'), true)['devDependencies']['sass']), 'Declare required Sass compiler');
    check((require $base.'/resources/views/themes/sunset/config.php')['assets']['mode'] === 'vite', 'Generated manifest selects Vite');
    // Preview uses starter inline assets until Vite's first build.
    $app->instance(Vite::class, new Vite);
    check(str_contains($theme->unit('web')->theme('sunset')->view('pages.home')->render(), '<style>'), 'Local Vite preview works before build');
    $app->instance('env', 'production');
    try { $theme->view('pages.home')->render(); check(false, 'Production must require built Vite assets'); }
    catch (Illuminate\View\ViewException $exception) {
        check(str_contains($exception->getMessage(), 'Vite manifest not found'), 'Production does not silently hide missing builds');
    }
    $files->makeDirectory($base.'/public/build', 0755, true);
    $files->put($base.'/public/build/manifest.json', json_encode([
        'resources/views/themes/sunset/assets/css/sunset-styles.scss' => ['src' => 'resources/views/themes/sunset/assets/css/sunset-styles.scss', 'file' => 'assets/sunset-styles.css', 'isEntry' => true],
        'resources/views/themes/sunset/assets/js/sunset-scripts.js' => ['src' => 'resources/views/themes/sunset/assets/js/sunset-scripts.js', 'file' => 'assets/sunset-scripts.js', 'isEntry' => true],
    ]));
    $app->instance(Vite::class, (new Vite)->createAssetPathsUsing(fn ($path) => 'https://example.test/'.$path));
    $builtHtml = $theme->view('pages.home')->render();
    check(str_contains($builtHtml, 'sunset-styles.css') && str_contains($builtHtml, 'type="module"'), 'Use real Vite manifest after build');
    check(! str_contains($builtHtml, '<style>'), 'Built mode does not inject inline starter assets');
    $app->instance('env', 'testing');
    check($run(['name' => 'coral']) === 0, 'Generate subsequent Vite theme');
    check($files->get($base.'/vite.config.js') === $connectedConfig, 'Repeated scaffolding does not duplicate Vite configuration');
    check($run(['name' => 'plain', '--assets' => 'inline']) === 0, 'Inline mode opts out of Vite');
    check((require $base.'/resources/views/themes/plain/config.php')['assets']['mode'] === 'inline', 'Inline choice is persisted');
    $files->put($base.'/vite.config.js', "export default async () => ({plugins: []});\n");
    $customConfig = $files->get($base.'/vite.config.js');
    check($run(['name' => 'custom']) === 0, 'Custom config uses safe inline fallback');
    check($files->get($base.'/vite.config.js') === $customConfig, 'Custom Vite config left intact');
    check((require $base.'/resources/views/themes/custom/config.php')['assets']['mode'] === 'inline', 'Unsupported Vite config fallback is persisted');
    echo "Passed {$checks} checks.\n";
} finally {
    $files->deleteDirectory($base);
}
