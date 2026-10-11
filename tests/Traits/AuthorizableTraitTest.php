<?php

namespace Maatwebsite\Sidebar\Tests\Traits;

use Illuminate\Container\Container;
use Maatwebsite\Sidebar\Authorization\PermissionResolver;
use Maatwebsite\Sidebar\Traits\AuthorizableTrait;
use Mockery;
use PHPUnit\Framework\TestCase as TestCase;

class AuthorizableTraitTest extends TestCase
{
    /**
     * @var StubItemableClass
     */
    protected $routeable;

    protected function setUp(): void
    {
        $this->routeable = new StubAuthorizableClass();
    }

    public function test_can_authorize()
    {
        $this->routeable->authorize(true);
        $this->assertTrue($this->routeable->isAuthorized());

        $this->routeable->authorize(false);
        $this->assertFalse($this->routeable->isAuthorized());
    }

    public function test_can_authorize_using_the_configured_permission_resolver()
    {
        $previousContainer = Container::getInstance();
        $container = new Container();
        $resolver = Mockery::mock(PermissionResolver::class);
        $resolver->shouldReceive('allows')->with('users.view')->once()->andReturnTrue();
        $container->instance(PermissionResolver::class, $resolver);
        Container::setInstance($container);

        try {
            $this->routeable->authorizePermission('users.view');

            $this->assertTrue($this->routeable->isAuthorized());
        } finally {
            Container::setInstance($previousContainer);
            Mockery::close();
        }
    }

    public function test_denies_when_the_configured_permission_resolver_denies_access()
    {
        $previousContainer = Container::getInstance();
        $container = new Container();
        $resolver = Mockery::mock(PermissionResolver::class);
        $resolver->shouldReceive('allows')->with('roles.view')->once()->andReturnFalse();
        $container->instance(PermissionResolver::class, $resolver);
        Container::setInstance($container);

        try {
            $this->routeable->authorizePermission('roles.view');

            $this->assertFalse($this->routeable->isAuthorized());
        } finally {
            Container::setInstance($previousContainer);
            Mockery::close();
        }
    }
}

class StubAuthorizableClass
{
    use AuthorizableTrait;
}
