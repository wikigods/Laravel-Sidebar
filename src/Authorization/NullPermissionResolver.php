<?php

namespace Maatwebsite\Sidebar\Authorization;

class NullPermissionResolver implements PermissionResolver
{
    public function allows(string $permission): bool
    {
        return false;
    }
}
