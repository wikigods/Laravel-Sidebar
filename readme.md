# Laravel Sidebar

[![Latest Version on Packagist](https://img.shields.io/packagist/v/maatwebsite/laravel-sidebar.svg?style=flat-square)](https://packagist.org/packages/maatwebsite/laravel-sidebar)
[![Tests](https://github.com/wikigods/Laravel-Sidebar/actions/workflows/php.yml/badge.svg)](https://github.com/wikigods/Laravel-Sidebar/actions/workflows/php.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/maatwebsite/laravel-sidebar.svg?style=flat-square)](https://packagist.org/packages/maatwebsite/laravel-sidebar)
[![License](https://img.shields.io/packagist/l/maatwebsite/laravel-sidebar.svg?style=flat-square)](LICENSE)

Laravel Sidebar helps you build, authorize, cache, and render grouped navigation menus in Laravel applications. It includes AdminLTE 2 and AdminLTE 3 view templates, a configurable middleware alias, and an interactive installer.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Building a sidebar](#building-a-sidebar)
- [Permissions](#permissions)
- [Caching](#caching)
- [Views and templates](#views-and-templates)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- PHP 8.1 or later
- Laravel 10, 11, 12, or 13

## Installation

Install the package with Composer:

```bash
composer require maatwebsite/laravel-sidebar
```

Laravel's package discovery registers the service provider. Run the installer to create a sidebar class and `config/sidebar.php`:

```bash
php artisan sidebar:install
```

The installer asks for a cache method, template, permission driver, sidebar class, target Blade view, view data key, and middleware alias. You can accept the defaults and change them later in `config/sidebar.php`.

The generated example class is `app/Sidebars/AdminSidebar.php`. It contains a Dashboard menu item without a route, so it can render before you add application routes. An item without a route uses `#` as its URL.

Add the configured data key to the target Blade view. With the default settings, edit `resources/views/admin/partials/_sidebar.blade.php` and include:

```blade
{!! $sidebar !!}
```

Add the configured middleware alias to the routes that render the sidebar. By default, the alias is `sidebar`:

```php
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'sidebar'])->group(function () {
    // Sidebar-enabled routes...
});
```

The sidebar middleware must run before the target view is rendered. The package registers the alias from `sidebar.middleware.alias`; no manual middleware registration is needed when package discovery is enabled.

### Installer options

The interactive numeric choices are:

| Prompt | Choice | Value |
| --- | ---: | --- |
| Cache method | 0 | No caching (`null`) |
| Cache method | 1 | Static cache |
| Cache method | 2 | User-based cache |
| Template | 0 | AdminLTE2 |
| Template | 1 | AdminLTE3 |
| Permission driver | 0 | Spatie / Laravel authorization |
| Permission driver | 1 | Cartalyst Sentinel |
| Permission driver | 2 | No permission integration |

You can also provide options non-interactively. For example:

```bash
php artisan sidebar:install --cache=0 --template=1 --permissions=0
```

Use `--class`, `--target`, `--key`, and `--alias` to specify the generated class, target view, view data key, and route middleware alias. `--publish-views` publishes the selected template. If generated files already exist, the installer preserves them unless you confirm replacement or pass `--force`; replacement creates a `.bak` copy of each generated PHP file.

## Configuration

The installer creates `config/sidebar.php`. Its main settings are:

```php
'cache' => [
    'method' => null, // null, static, or user-based
    'duration' => 1440,
],

'view' => 'AdminLTE2', // AdminLTE2 or AdminLTE3

'sidebar' => [
    'class' => App\Sidebars\AdminSidebar::class,
    'view' => 'admin.partials._sidebar',
    'key' => 'sidebar',
],

'middleware' => [
    'alias' => 'sidebar',
    'class' => Maatwebsite\Sidebar\Middleware\ResolveSidebars::class,
],

'permissions' => [
    'driver' => 'spatie', // spatie, sentinel, or none
    'resolver' => null,
],
```

The target view receives rendered HTML under `sidebar.sidebar.key`. For example, if the key is `navigation`, print it in Blade as `{!! $navigation !!}`.

## Building a sidebar

A sidebar implements `Maatwebsite\Sidebar\Sidebar` and receives a `Menu` through dependency injection. Add groups and items in `build()`:

```php
<?php

namespace App\Sidebars;

use Maatwebsite\Sidebar\Group;
use Maatwebsite\Sidebar\Item;
use Maatwebsite\Sidebar\Menu;
use Maatwebsite\Sidebar\Sidebar;

class AdminSidebar implements Sidebar
{
    public function __construct(protected Menu $menu)
    {
    }

    public function build(): void
    {
        $isAuthenticated = auth()->check();

        $this->menu->group('Dashboard', function (Group $group) use ($isAuthenticated): void {
            $group->weight(0);
            $group->hideHeading();
            $group->authorize($isAuthenticated);

            $group->item('Dashboard', function (Item $item) use ($isAuthenticated): void {
                $item->weight(100);
                $item->icon('fa fa-dashboard');
                $item->authorize($isAuthenticated);

                // Add a route when one is available, for example:
                // $item->route('admin.index');
            });
        });
    }

    public function getMenu(): Menu
    {
        return $this->menu;
    }
}
```

Groups and items support weights for ordering and authorization checks. Items can also define routes, icons, active URL patterns, badges, nested items, and appended links. A route name passed to `route()` must exist in the application.

## Permissions

You can authorize an item or group with a boolean:

```php
$item->authorize(auth()->user()?->can('viewAny', User::class) ?? false);
```

Or authorize it by permission name using the configured driver:

```php
$group->item('Roles', function (Item $item): void {
    $item->route('admin.roles.index');
    $item->icon('fa fa-lock');
    $item->authorizePermission('roles.view');
});
```

The default `spatie` driver delegates to Laravel's `can()` authorization method, so it works with policies and with Spatie Laravel Permission when that package is installed and configured. The `sentinel` driver checks `hasAccess()` on the authenticated Sentinel user and requires Cartalyst Sentinel to be installed. The `none` driver denies permission-name checks. Direct boolean authorization through `authorize()` remains available with every driver.

For an application-specific permission system, implement `Maatwebsite\Sidebar\Authorization\PermissionResolver` and set its class in `sidebar.permissions.resolver`.

## Caching

Set `sidebar.cache.method` to one of these values:

| Method | Behavior |
| --- | --- |
| `null` | Build the sidebar on each request. This is the default. |
| `static` | Cache one shared sidebar. Use it only when the menu is identical for every visitor. |
| `user-based` | Cache a separate sidebar per authenticated user. |

To make a sidebar cacheable, implement `ShouldCache` and use `CacheableTrait`. The generated sidebar class already includes both:

```php
use Maatwebsite\Sidebar\ShouldCache;
use Maatwebsite\Sidebar\Traits\CacheableTrait;

class AdminSidebar implements Sidebar, ShouldCache
{
    use CacheableTrait;

    // Build the menu...
}
```

User-based caching requires the configured Laravel cache store to support tags. Choose a compatible store, such as Redis or Memcached, before enabling it. Do not cache a sidebar statically if its items or authorization depend on the current user.

To flush the registered sidebar cache when application data that affects the menu changes, register the package handler for events implementing `ShouldFlushCache` in your application's event configuration:

```php
use Maatwebsite\Sidebar\Domain\Events\FlushesSidebarCache;
use Maatwebsite\Sidebar\Domain\Events\ShouldFlushCache;

protected $listen = [
    ShouldFlushCache::class => [
        FlushesSidebarCache::class,
    ],
];
```

Then dispatch an application event that implements `ShouldFlushCache` after the relevant data changes.

## Views and templates

The built-in templates are:

| Template | CSS framework |
| --- | --- |
| `AdminLTE2` | Bootstrap 3 |
| `AdminLTE3` | Bootstrap 4 |

Select the template in `config/sidebar.php`, or publish a copy to `resources/views/vendor/sidebar` so it can be customized:

```bash
# Publish AdminLTE 2
php artisan vendor:publish --tag=sidebar-views-adminlte2

# Publish AdminLTE 3
php artisan vendor:publish --tag=sidebar-views-adminlte3
```

The installer publishes the selected template when requested. Publishing over existing views replaces those files; back up local template edits first. The default view namespace is `sidebar`, and the package renders the selected built-in template from that namespace.

To publish the configuration manually instead of using the installer:

```bash
php artisan vendor:publish --tag=sidebar-config
```

## Troubleshooting

### The sidebar area is blank

- Confirm the configured target view matches the Blade view that is rendered.
- Print the configured data key in that view, for example `{!! $sidebar !!}`.
- Make sure the configured middleware alias is on the route and runs before the view is rendered.
- Check that the authenticated user passes the group's and item's authorization checks.
- If caching is enabled, clear the application cache after changing the menu or its authorization rules.

### User-based caching reports that cache tags are unsupported

Switch to a Laravel cache store that supports tags, such as Redis or Memcached, or use `null` or `static` caching.

### The wrong template appears

Check `sidebar.view` in `config/sidebar.php`. If you published template files, publish the matching template tag and clear compiled views:

```bash
php artisan view:clear
```

## Contributing

Contributions are welcome. Please add or update tests for behavior changes and run the test suite before submitting a pull request:

```bash
composer test
```

The CI workflow checks Laravel component versions 10 through 13.

## License

Laravel Sidebar is open-sourced software licensed under the [MIT license](LICENSE).

## Legacy documentation: Laravel Sidebar 2.x

The instructions below are kept for applications using the older 2.x installation flow. They do not describe the current installer-based setup above. For new installations, follow the current [Installation](#installation) section.

### Installation

For the old 2.x release, require the package in `composer.json` and run `composer update`:

```json
"maatwebsite/laravel-sidebar": "~2.1"
```

Register the service provider manually in `config/app.php`:

```php
'Maatwebsite\Sidebar\SidebarServiceProvider',
```

Register the package middleware in `App\Http\Kernel`:

```php
'Maatwebsite\Sidebar\Middleware\ResolveSidebars',
```

Publish the default views and configuration with the legacy tags:

```bash
php artisan vendor:publish --tag="views"
php artisan vendor:publish --tag="config"
```

### Legacy documentation links

- [Laravel Sidebar 2.x Wiki](https://github.com/Maatwebsite/Laravel-Sidebar/wiki)
- [Legacy upgrade guide](https://github.com/Maatwebsite/Laravel-Sidebar/wiki/Upgrade-guide)

### Legacy contribution notes

The original contribution guidance asked contributors to target the appropriate release branch, follow PSR-1, PSR-2, and PSR-4, and include unit tests for behavior changes. Current contributions should follow the repository's present branch and CI configuration.
