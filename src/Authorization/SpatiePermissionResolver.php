<?php

namespace Maatwebsite\Sidebar\Authorization;

use Illuminate\Contracts\Auth\Guard;

class SpatiePermissionResolver implements PermissionResolver
{
    public function __construct(
        protected Guard $guard
    ) {
    }

    public function allows(string $permission): bool
    {
        $user = $this->guard->user();

        return $user !== null
            && method_exists($user, 'can')
            && $user->can($permission);
    }
}
