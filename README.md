# Laravel Theme Manager

An independent extraction of this application's ThemeService. Composer package: `lhaamed/theme-manager`; namespace: `lhaamed\ThemeManager`.

Supports Laravel 11/12 on PHP 8.2+ and Laravel 13 on PHP 8.3+. No database, frontend framework, application models, or Livewire dependency is required. Livewire 4 theme namespaces are registered when its namespace API is available.

## Installation

This directory is the package repository root. Upload its contents to your GitHub repository, for example `lhaamed/theme-manager`, and tag a release such as `v0.1.0`. The URL below assumes you created that repository; this extraction does not publish it.

In a fresh Laravel application, before the package is listed on Packagist:

```sh
composer config repositories.theme-manager vcs https://github.com/lhaamed/theme-manager
composer require lhaamed/theme-manager:^0.1
```

For local development, use a Composer path repository instead:

```sh
composer config repositories.theme-manager path /absolute/path/to/ThemeManager
composer require lhaamed/theme-manager:@dev
```

Laravel discovers the provider and `Theme` facade automatically. Publishing configuration is optional:

```sh
php artisan vendor:publish --tag=theme-config
php artisan theme:make ocean
php artisan theme:make dashboard --unit=admin
```

Names must start with a lowercase letter and contain only lowercase letters, digits, `_` or `-`. Existing directories and registered names are rejected; there is deliberately no destructive `--force` option.

## Generated theme

```text
resources/views/themes/ocean/
├── config.php
├── layouts/index.blade.php
├── pages/home.blade.php
├── components/pagination/default.blade.php
├── includes/
├── livewire/
└── assets/
    ├── css/theme.css
    ├── js/theme.js
    └── images/
```

`config.php` contains the theme name, unit, versions, stable version, layout view, relative component paths and pagination view. The generated layout has content, style and script slots. It renders immediately without a Vite build. Assets are source files: add them to your application's Vite inputs and reference them with `@vite` when needed. They are not automatically copied to `public`.

Themes are discovered from `theme.directory/*/config.php` on each registry lookup, including when Laravel configuration is cached. No PHP configuration file is rewritten by the command. Without an explicit default, the alphabetically first discovered theme in a unit is its default. Set an explicit default for predictable production behavior.

## Usage

In `routes/web.php`:

```php
use Illuminate\Support\Facades\Route;
use lhaamed\ThemeManager\Facades\Theme;

Route::get('/theme-demo', function () {
    return Theme::unit('web')
        ->theme('ocean')
        ->setPageTitle('Home')
        ->view('pages.home');
});
```

Use `Theme::getThemeInitiator()` with `@extends`, `Theme::viewToString('includes.header')` with `@include`, and `$items->links(Theme::paginationPath())` for pagination. `renderActiveTheme()` returns the layout's view name, matching the original API. It does not return rendered HTML.

An anonymous component at `components/card.blade.php` is available as `<x-ocean::card />`. Livewire 4 files in `livewire/` are available under the theme namespace using Livewire's namespace conventions. Restart long-running application workers after adding themes so their Blade/Livewire namespaces are registered.

The service is scoped, with instance state for active unit/theme and page title. Unknown units/themes throw descriptive exceptions. Selecting a unit keeps the current theme if valid in that unit, otherwise selects its default. Selecting a theme stores its logical name, not its filesystem path.

## Configuration

```php
return [
    'directory' => 'themes', // relative to resources/views
    'default_unit' => 'web',
    'page_title_separator' => '|',
    'all_available_units' => [
        'web' => [
            'default' => 'ocean',
            'page_title_prefix' => 'My website',
            'themes' => ['ocean' => 'themes/ocean'],
        ],
    ],
];
```

Explicit entries override discovery. Theme roots are relative to `resources/views`; a custom nested directory such as `site/themes` is supported. Theme manifests are trusted PHP source maintained by the application developer.

## Extraction map and migration

| Original location | Package replacement |
| --- | --- |
| `app/Services/ThemeService.php` | `src/ThemeService.php` and `src/ThemeRegistry.php` |
| `app/Facades/Theme.php` | `src/Facades/Theme.php` |
| Theme singleton in `AppServiceProvider::register()` | Scoped binding in `ThemeServiceProvider::register()` |
| `registerThemeViewComponents()` | Provider's Blade namespace registration |
| `registerThemeLivewireComponents()` | Optional Livewire namespace registration |
| `config/theme.php` | Published configuration plus manifest discovery |
| Existing theme manifests | Same `versions`, `last_stable_version`, `roots` schema |

The public methods are retained: `renderActiveTheme`, `getThemeInitiator`, `getThemeConfig`, `getThemeRoot`, `getThemeRootPath`, `getThemeComponentsPath`, `getThemeLivewirePath`, `getCurrentThemeName`, `view`, `viewToString`, `paginationPath`, `unit`, `theme`, the title setters, `getPageTitle`, and `getAllThemes`. Call them through the package facade or injected service. Direct static service methods/properties are intentionally replaced by scoped instance state.

The host application's existing service, provider, Composer configuration, and theme files have not been changed. To migrate it:

1. Install this package through Composer.
2. Keep the existing `all_available_units` map and add `'default_unit' => 'admin'` to the host's `config/theme.php` to preserve its initial unit.
3. Put project-specific prefixes in each unit's `page_title_prefix` (`admin`, `landing`, `auth`), replacing the old hardcoded branding.
4. Replace imports and fully qualified references to `App\Facades\Theme` with `lhaamed\ThemeManager\Facades\Theme`; replace service type hints similarly.
5. Remove the old ThemeService binding and the two theme registration methods/calls from `AppServiceProvider`. Remove the old facade/service once no references remain.
6. Run `php artisan optimize:clear`, rebuild configuration if used, and restart workers.

Existing manifests without a `unit` field continue to work through the explicit configuration map. Both array version roots and the `lhaamed2026` string layout shorthand are supported. Theme names shared by units should use the same root; different roots require selecting a unit, and component namespaces should remain globally unique.

Project-specific controllers, pages, branded assets and integrations such as BladeBundler are application content and are not bundled with this reusable package. The new skeleton is intentionally framework-only. Import/export is outside this MVP; the self-contained directory and versioned manifest provide a foundation for it.

## Validation

```sh
composer install
composer validate --strict
composer test
```

Tests create an isolated Laravel application in a temporary directory, generate multiple themes, render their Blade views, verify component registration, test invalid input and collisions, and verify scoped state and legacy manifests. No host database or `.env` is needed. GitHub Actions runs the suite on Laravel 11/12 with PHP 8.2–8.4 and Laravel 13 with PHP 8.3–8.4. From this parent project, run `php lhaamed/ThemeManager/tests/run.php` to use its installed dependencies.

Laravel 11 compatibility jobs allow framework advisories only in their disposable CI configuration so that legacy behavior can still be tested. This exception is not part of the package's Composer configuration and is not applied to Laravel 12/13 jobs or consuming applications.
