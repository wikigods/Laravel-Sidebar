<?php

namespace Maatwebsite\Sidebar\Traits;

use Illuminate\Container\Container;
use Maatwebsite\Sidebar\Authorization\PermissionResolver;

trait AuthorizableTrait
{
    /**
     * @var bool
     */
    protected $authorized = true;

    /**
     * Check if we are authorized to see this item/group
     * @return bool
     */
    public function isAuthorized()
    {
        return $this->authorized;
    }

    /**
     * Authorize the group/item
     *
     * @param bool $state
     *
     * @return $this
     */
    public function authorize($state = true)
    {
        $this->authorized = $state;

        return $this;
    }

    /**
     * Authorize this group or item using the configured permission driver.
     *
     * @param string $permission
     *
     * @return $this
     */
    public function authorizePermission(string $permission)
    {
        return $this->authorize(
            Container::getInstance()->make(PermissionResolver::class)->allows($permission)
        );
    }
}
