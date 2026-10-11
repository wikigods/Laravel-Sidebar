<?php

namespace Maatwebsite\Sidebar;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Maatwebsite\Sidebar\Authorization\NullPermissionResolver;
use Maatwebsite\Sidebar\Authorization\PermissionResolver;
use Maatwebsite\Sidebar\Authorization\SentinelPermissionResolver;
use Maatwebsite\Sidebar\Authorization\SpatiePermissionResolver;
use Maatwebsite\Sidebar\Console\SidebarInstallCommand;
use Maatwebsite\Sidebar\Exceptions\LogicException;
use Maatwebsite\Sidebar\Infrastructure\SidebarFlusherFactory;
use Maatwebsite\Sidebar\Infrastructure\SidebarResolverFactory;
use Maatwebsite\Sidebar\Presentation\SidebarRenderer;

class SidebarServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     * @var bool
     */
    protected $defer = false;

    /**
     * @var string
     */
    protected $shortName = 'sidebar';

    /**
     * Boot the service provider.
     * @return void
     */
    public function boot()
    {
        $this->registerViews();
        $this->registerMiddlewareAlias();
        $this->registerSidebar();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SidebarInstallCommand::class,
            ]);
        }
    }

    /**
     * Register the service provider.
     * @return void
     */
    public function register()
    {
        // Register config
        $this->registerConfig();

        // Bind SidebarResolver
        $this->app->bind('Maatwebsite\Sidebar\Infrastructure\SidebarResolver', function (Application $app) {

            $resolver = SidebarResolverFactory::getClassName(
                $app['config']->get('sidebar.cache.method')
            );

            return $app->make($resolver);
        });

        // Bind SidebarFlusher
        $this->app->bind('Maatwebsite\Sidebar\Infrastructure\SidebarFlusher', function (Application $app) {

            $resolver = SidebarFlusherFactory::getClassName(
                $app['config']->get('sidebar.cache.method')
            );

            return $app->make($resolver);
        });

        // Bind manager
        $this->app->singleton('Maatwebsite\Sidebar\SidebarManager');

        $this->app->bind(PermissionResolver::class, function (Application $app) {
            $resolver = $app['config']->get('sidebar.permissions.resolver');

            if ($resolver) {
                return $app->make($resolver);
            }

            return match ($app['config']->get('sidebar.permissions.driver', 'spatie')) {
                'spatie' => new SpatiePermissionResolver($app['auth']->guard()),
                'sentinel' => new SentinelPermissionResolver($app),
                'none' => new NullPermissionResolver(),
                default => throw new LogicException(
                    'The configured sidebar permission driver is not supported.'
                ),
            };
        });

        // Bind Menu
        $this->app->bind(
            'Maatwebsite\Sidebar\Menu',
            'Maatwebsite\Sidebar\Domain\DefaultMenu'
        );

        // Bind Group
        $this->app->bind(
            'Maatwebsite\Sidebar\Group',
            'Maatwebsite\Sidebar\Domain\DefaultGroup'
        );

        // Bind Item
        $this->app->bind(
            'Maatwebsite\Sidebar\Item',
            'Maatwebsite\Sidebar\Domain\DefaultItem'
        );

        // Bind Badge
        $this->app->bind(
            'Maatwebsite\Sidebar\Badge',
            'Maatwebsite\Sidebar\Domain\DefaultBadge'
        );

        // Bind Append
        $this->app->bind(
            'Maatwebsite\Sidebar\Append',
            'Maatwebsite\Sidebar\Domain\DefaultAppend'
        );

        // Bind Renderer
        $this->app->bind(
            'Maatwebsite\Sidebar\Presentation\SidebarRenderer',
            'Maatwebsite\Sidebar\Presentation\Illuminate\IlluminateSidebarRenderer'
        );
    }

    /**
     * Register views.
     * @return void
     */
    protected function registerViews()
    {
        $templates = [
            'AdminLTE2' => 'adminlte-2',
            'AdminLTE3' => 'adminlte-3',
        ];
        $selectedTemplate = config('sidebar.view') === 'AdminLTE3' ? 'AdminLTE3' : 'AdminLTE2';
        $destination = base_path('resources/views/vendor/' . $this->shortName);

        foreach ($templates as $template => $directory) {
            $location = __DIR__ . "/../resources/views/{$directory}";

            $this->publishes([
                $location => $destination,
            ], 'sidebar-views-' . strtolower($template));

            if ($template === $selectedTemplate) {
                $this->loadViewsFrom($location, $this->shortName);
                $this->publishes([
                    $location => $destination,
                ], 'sidebar-views');
            }
        }
    }

    /**
     * Register config
     * @return void
     */
    protected function registerConfig()
    {
        $location = __DIR__ . '/../config/' . $this->shortName . '.php';

        $this->mergeConfigFrom(
            $location, $this->shortName
        );

        $this->publishes([
            $location => config_path($this->shortName . '.php'),
        ], 'sidebar-config');
    }

    protected function registerMiddlewareAlias(): void
    {
        $alias = config('sidebar.middleware.alias', 'sidebar');
        $middleware = config(
            'sidebar.middleware.class',
            \Maatwebsite\Sidebar\Middleware\ResolveSidebars::class
        );

        $this->app['router']->aliasMiddleware($alias, $middleware);
    }

    protected function registerSidebar(): void
    {
        $sidebar = config('sidebar.sidebar.class');

        if (!$sidebar) {
            return;
        }

        if (!class_exists($sidebar) || !is_subclass_of($sidebar, Sidebar::class)) {
            throw new LogicException(
                'The configured sidebar class must exist and implement Maatwebsite\\Sidebar\\Sidebar.'
            );
        }

        $this->app->make(SidebarManager::class)->register($sidebar);

        $targetView = config('sidebar.sidebar.view');
        $viewDataKey = config('sidebar.sidebar.key', 'sidebar');

        if (!$targetView) {
            return;
        }

        View::creator($targetView, function ($view) use ($sidebar, $viewDataKey): void {
            $sidebarInstance = app($sidebar);
            $renderer = app(SidebarRenderer::class);

            $view->with(
                $viewDataKey,
                $renderer->render($sidebarInstance)
            );
        });
    }

    /**
     * Get the services provided by the provider.
     * @return array
     */
    public function provides()
    {
        return [
            'Maatwebsite\Sidebar\Menu',
            'Maatwebsite\Sidebar\Item',
            'Maatwebsite\Sidebar\Group',
            'Maatwebsite\Sidebar\Badge',
            'Maatwebsite\Sidebar\Append',
            'Maatwebsite\Sidebar\SidebarManager',
            'Maatwebsite\Sidebar\Presentation\SidebarRenderer',
            'Maatwebsite\Sidebar\Infrastructure\SidebarResolver'
        ];
    }
}
