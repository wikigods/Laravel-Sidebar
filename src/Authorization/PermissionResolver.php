<?php

namespace Maatwebsite\Sidebar\Authorization;

interface PermissionResolver
{
    public function allows(string $permission): bool;
}
