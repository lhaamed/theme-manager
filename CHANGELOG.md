# Changelog

## v0.1.1 — 2026-11-04

- Add Laravel 13 support to all Illuminate dependencies and the development framework constraint.
- Extend CI to test Laravel 13 on PHP 8.3 and 8.4, while retaining the Laravel 11/12 matrix.
- Document Laravel 13's PHP 8.3 minimum; Laravel 11/12 continue to support PHP 8.2.
- Keep legacy Laravel 11 compatibility jobs installable with a framework-only advisory exception in disposable CI configuration.

## v0.1.0 — 2026-11-04

- Initial standalone theme manager with unit selection, versioned manifests, Blade components, optional Livewire integration, and Artisan theme scaffolding.
