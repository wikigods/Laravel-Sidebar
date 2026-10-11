<?php

namespace Maatwebsite\Sidebar\Authorization;

use Illuminate\Contracts\Container\Container;
use Maatwebsite\Sidebar\Exceptions\LogicException;

class SentinelPermissionResolver implements PermissionResolver
{
    public function __construct(
        protected Container $container
    ) {
    }

    public function allows(string $permission): bool
    {
        $sentinel = $this->resolveSentinel();
        $user = $sentinel->getUser();

        return $user !== null
            && method_exists($user, 'hasAccess')
            && $user->hasAccess($permission);
    }

    protected function resolveSentinel(): object
    {
        if ($this->container->bound('sentinel')) {
            return $this->container->make('sentinel');
        }

        $facade = 'Cartalyst\\Sentinel\\Laravel\\Facades\\Sentinel';

        if (class_exists($facade) && method_exists($facade, 'getFacadeRoot')) {
            $sentinel = $facade::getFacadeRoot();

            if (is_object($sentinel)) {
                return $sentinel;
            }
        }

        throw new LogicException(
            'The Sentinel permission driver is selected, but Cartalyst Sentinel is not available.'
        );
    }
}
