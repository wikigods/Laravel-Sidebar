<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    |
    | Choose how sidebars should be cached.
    | The configured Laravel cache store is used.
    |
    | Available methods: null, static, user-based.
    | User-based caching requires a cache store that supports tags,
    | such as Redis or Memcached.
    |
    */
    'cache' => [
        'method' => null,
        'duration' => 1440,
    ],

    /*
    |--------------------------------------------------------------------------
    | View Name
    |--------------------------------------------------------------------------
    |
    | Choose the package template used to render the sidebar.
    | Built-in templates are AdminLTE2 and AdminLTE3.
    |
    | You can customize this value later in this configuration file.
    |
    */
    'view' => 'AdminLTE2',

    /*
    |--------------------------------------------------------------------------
    | Sidebar Integration
    |--------------------------------------------------------------------------
    |
    | Configure the application sidebar class and the view that receives it.
    |
    */
    'sidebar' => [
        'class' => null,
        'view' => 'admin.partials._sidebar',
        'key' => 'sidebar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Register the sidebar middleware using this route alias.
    | Example: Route::middleware(['auth', 'sidebar'])->group(...);
    |
    */
    'middleware' => [
        'alias' => 'sidebar',
        'class' => Maatwebsite\Sidebar\Middleware\ResolveSidebars::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    |
    | Choose the permission system used by sidebar authorization checks.
    | Available drivers: spatie, sentinel, none.
    |
    */
    'permissions' => [
        'driver' => 'spatie',
        'resolver' => null,
    ],
];
