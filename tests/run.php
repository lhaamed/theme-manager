<?php

// Runs independently after composer install, or against the host's dependencies.
$autoload = is_file(__DIR__.'/../vendor/autoload.php')
    ? __DIR__.'/../vendor/autoload.php' : __DIR__.'/../../../vendor/autoload.php';
$loader = require $autoload;
$loader->addPsr4('lhaamed\\ThemeManager\\', __DIR__.'/../src');

use Illuminate\Config\Repository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use lhaamed\ThemeManager\Console\MakeThemeCommand;
use lhaamed\ThemeManager\ThemeRegistry;
use lhaamed\ThemeManager\ThemeService;
use lhaamed\ThemeManager\ThemeServiceProvider;
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
    echo "Passed {$checks} checks.\n";
} finally {
    $files->deleteDirectory($base);
}
