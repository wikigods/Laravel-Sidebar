<?php

namespace Maatwebsite\Sidebar\Tests\Authorization;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Container\Container;
use Maatwebsite\Sidebar\Authorization\NullPermissionResolver;
use Maatwebsite\Sidebar\Authorization\SentinelPermissionResolver;
use Maatwebsite\Sidebar\Authorization\SpatiePermissionResolver;
use Maatwebsite\Sidebar\Exceptions\LogicException;
use Mockery;
use PHPUnit\Framework\TestCase;

class PermissionResolverTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_spatie_resolver_checks_the_authenticated_users_permission(): void
    {
        $user = new class {
            public function can(string $permission): bool
            {
                return $permission === 'users.view';
            }
        };
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->twice()->andReturn($user);

        $resolver = new SpatiePermissionResolver($guard);

        $this->assertTrue($resolver->allows('users.view'));
        $this->assertFalse($resolver->allows('roles.view'));
    }

    public function test_spatie_resolver_denies_guests(): void
    {
        $guard = Mockery::mock(Guard::class);
        $guard->shouldReceive('user')->once()->andReturnNull();

        $this->assertFalse((new SpatiePermissionResolver($guard))->allows('users.view'));
    }

    public function test_sentinel_resolver_checks_access_and_denies_guests(): void
    {
        $user = new class {
            public function hasAccess(string $permission): bool
            {
                return $permission === 'users.view';
            }
        };
        $sentinel = new class($user) {
            public function __construct(private $user)
            {
            }

            public function getUser()
            {
                return $this->user;
            }
        };
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->twice()->with('sentinel')->andReturnTrue();
        $container->shouldReceive('make')->twice()->with('sentinel')->andReturn($sentinel);

        $resolver = new SentinelPermissionResolver($container);

        $this->assertTrue($resolver->allows('users.view'));
        $this->assertFalse($resolver->allows('roles.view'));
    }

    public function test_sentinel_resolver_reports_missing_integration(): void
    {
        $container = Mockery::mock(Container::class);
        $container->shouldReceive('bound')->once()->with('sentinel')->andReturnFalse();

        $this->expectException(LogicException::class);
        (new SentinelPermissionResolver($container))->allows('users.view');
    }

    public function test_null_resolver_denies_permissions(): void
    {
        $this->assertFalse((new NullPermissionResolver())->allows('users.view'));
    }
}
