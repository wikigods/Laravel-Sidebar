<?php

namespace Maatwebsite\Sidebar\Console;

class SidebarInstallChoices
{
    public const CACHE = [
        '0' => 'No caching (null)',
        '1' => 'Static cache',
        '2' => 'User-based cache',
    ];

    public const TEMPLATES = [
        '0' => 'AdminLTE2',
        '1' => 'AdminLTE3',
    ];

    public const PERMISSIONS = [
        '0' => 'Spatie',
        '1' => 'Cartalyst Sentinel',
        '2' => 'No permission integration',
    ];

    public static function cacheMethod($selection): ?string
    {
        return match ((string) $selection) {
            '0', 'null' => null,
            '1', 'static' => 'static',
            '2', 'user-based' => 'user-based',
            default => null,
        };
    }

    public static function template($selection): ?string
    {
        return match ((string) $selection) {
            '0', 'AdminLTE2' => 'AdminLTE2',
            '1', 'AdminLTE3' => 'AdminLTE3',
            default => null,
        };
    }

    public static function permissionDriver($selection): ?string
    {
        return match ((string) $selection) {
            '0', 'spatie' => 'spatie',
            '1', 'sentinel' => 'sentinel',
            '2', 'none' => 'none',
            default => null,
        };
    }
}
