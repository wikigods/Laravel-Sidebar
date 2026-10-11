<?php

namespace Maatwebsite\Sidebar\Tests\Console;

use Maatwebsite\Sidebar\Console\SidebarInstaller;
use Maatwebsite\Sidebar\Console\SidebarInstallChoices;
use Maatwebsite\Sidebar\Exceptions\LogicException;
use PHPUnit\Framework\TestCase;

class SidebarInstallerTest extends TestCase
{
    private SidebarInstaller $installer;

    protected function setUp(): void
    {
        $this->installer = new SidebarInstaller();
    }

    public function test_it_generates_the_sidebar_class_with_a_safe_dashboard_example(): void
    {
        $contents = $this->installer->renderSidebar('App\\Sidebars\\AdminSidebar');

        token_get_all($contents, TOKEN_PARSE);

        $this->assertStringContainsString('namespace App\\Sidebars;', $contents);
        $this->assertStringContainsString('class AdminSidebar implements Sidebar, ShouldCache', $contents);
        $this->assertStringContainsString(
            "\$this->menu->group('Dashboard', function (Group \$group) use (\$isAuthenticated): void {",
            $contents
        );
        $this->assertStringContainsString("// No route is set. The item uses the package's default \"#\" URL.", $contents);
        $this->assertStringNotContainsString("->route('", $contents);
    }

    public function test_it_generates_the_requested_configuration_values(): void
    {
        $contents = $this->installer->renderConfig([
            'cache_method' => 'user-based',
            'cache_duration' => 60,
            'template' => 'AdminLTE3',
            'sidebar_class' => 'App\\Sidebars\\AdminSidebar',
            'target_view' => 'admin.partials._sidebar',
            'view_data_key' => 'sidebarHtml',
            'middleware_alias' => 'sidebar',
            'permission_driver' => 'sentinel',
        ]);

        token_get_all($contents, TOKEN_PARSE);

        $this->assertStringContainsString("'method' => 'user-based'", $contents);
        $this->assertStringContainsString("'view' => 'AdminLTE3'", $contents);
        $this->assertStringContainsString("'class' => \\App\\Sidebars\\AdminSidebar::class", $contents);
        $this->assertStringContainsString("'key' => 'sidebarHtml'", $contents);
        $this->assertStringContainsString("'driver' => 'sentinel'", $contents);
        $this->assertStringContainsString("Route::middleware(['auth', 'sidebar'])", $contents);
    }

    public function test_it_maps_a_sidebar_class_to_the_application_path(): void
    {
        $applicationPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'application';
        $path = $this->installer->getSidebarPath(
            'App\\Sidebars\\AdminSidebar',
            'App\\',
            $applicationPath
        );

        $this->assertSame(
            $applicationPath . DIRECTORY_SEPARATOR . 'Sidebars' . DIRECTORY_SEPARATOR . 'AdminSidebar.php',
            $path
        );
    }

    public function test_it_rejects_sidebar_classes_outside_the_application_namespace(): void
    {
        $this->expectException(LogicException::class);
        $this->installer->getSidebarPath('Vendor\\Sidebars\\AdminSidebar', 'App\\', 'C:\\application\\app');
    }

    public function test_it_rejects_invalid_php_class_names(): void
    {
        $this->expectException(LogicException::class);
        $this->installer->renderSidebar('App\\Sidebars\\not-valid');
    }

    public function test_it_maps_prompt_selection_keys_to_configuration_values(): void
    {
        $this->assertSame('AdminLTE3', SidebarInstallChoices::template('1'));
        $this->assertSame('AdminLTE2', SidebarInstallChoices::template('0'));
        $this->assertSame('static', SidebarInstallChoices::cacheMethod('1'));
        $this->assertSame('user-based', SidebarInstallChoices::cacheMethod('2'));
        $this->assertNull(SidebarInstallChoices::cacheMethod('0'));
        $this->assertSame('sentinel', SidebarInstallChoices::permissionDriver('1'));
        $this->assertSame('none', SidebarInstallChoices::permissionDriver('2'));
    }

    public function test_it_generates_the_blade_output_example_using_the_configured_data_key(): void
    {
        $this->assertSame(
            '{!! $sidebar !!}',
            $this->installer->renderViewOutputExample('sidebar')
        );
        $this->assertSame(
            '{!! $sidebarHtml !!}',
            $this->installer->renderViewOutputExample('sidebarHtml')
        );
    }
}
