<?php

namespace Maatwebsite\Sidebar\Console;

use Illuminate\Console\Command;
use Maatwebsite\Sidebar\Exceptions\LogicException;

class SidebarInstallCommand extends Command
{
    protected $signature = 'sidebar:install
        {--cache= : Cache selection: 0, 1, or 2}
        {--duration= : Cache duration in minutes}
        {--template= : Template selection: 0 or 1}
        {--class= : Fully qualified sidebar class to generate}
        {--target= : Application view that receives the sidebar}
        {--key= : View data key for the rendered sidebar}
        {--alias= : Route middleware alias to register}
        {--permissions= : Permission driver selection: 0, 1, or 2}
        {--publish-views : Publish the package sidebar templates}
        {--force : Back up and replace existing generated files}';

    protected $description = 'Install and configure Laravel Sidebar interactively.';

    public function handle(SidebarInstaller $installer): int
    {
        try {
            $options = $this->collectOptions();
        } catch (LogicException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($options['cache_method'] === 'user-based' && !$this->defaultCacheStoreSupportsTags()) {
            $this->error(
                'The default cache store does not support tags. Choose Redis or Memcached, or select another cache method.'
            );

            return self::FAILURE;
        }

        $classPath = $installer->getSidebarPath(
            $options['sidebar_class'],
            $this->laravel->getNamespace(),
            app_path()
        );

        $classWritten = $this->writeGeneratedFile(
            $classPath,
            $installer->renderSidebar($options['sidebar_class']),
            'The sidebar class already exists. Replace it?'
        );

        $configPath = config_path('sidebar.php');
        $configWritten = $this->writeGeneratedFile(
            $configPath,
            $installer->renderConfig($options),
            'The sidebar configuration already exists. Replace it?'
        );

        if (!$classWritten && !file_exists($classPath)) {
            $this->error('The sidebar class was not created, so the package cannot use the selected class.');

            return self::FAILURE;
        }

        if (!$configWritten) {
            $this->warn(
                'The existing sidebar configuration was preserved. Update its sidebar settings to use the generated class and selected options.'
            );
        }

        if ($this->shouldPublishViews()) {
            $this->publishViews($options['template']);
        }

        $this->newLine();
        $this->info('Laravel Sidebar installation is ready.');
        if ($options['target_view'] === 'admin.partials._sidebar') {
            $this->line('Add this to resources/views/admin/partials/_sidebar.blade.php:');
        } else {
            $this->line("Add this to the Blade view '{$options['target_view']}':");
        }
        $this->line($installer->renderViewOutputExample($options['view_data_key']));
        $this->line('The sidebar middleware must run before this view is rendered so the menu gets built.');
        $this->line("Add it to the route group: Route::middleware(['auth', '{$options['middleware_alias']}'])->group(...);");
        $this->line('Customize the generated sidebar at ' . $classPath . '.');

        return self::SUCCESS;
    }

    protected function collectOptions(): array
    {
        $cacheMethod = $this->resolveCacheMethod($this->option('cache'));
        $template = $this->resolveTemplate($this->option('template'));
        $permissionDriver = $this->resolvePermissionDriver($this->option('permissions'));

        foreach ([
            'cache' => [$this->option('cache'), $cacheMethod, ['0', '1', '2', 'null', 'static', 'user-based']],
            'template' => [$this->option('template'), $template, ['0', '1', 'AdminLTE2', 'AdminLTE3']],
            'permissions' => [$this->option('permissions'), $permissionDriver, ['0', '1', '2', 'spatie', 'sentinel', 'none']],
        ] as $name => [$value, $resolved, $allowed]) {
            if ($value !== null && !in_array((string) $value, $allowed, true)) {
                throw new LogicException("Invalid {$name} selection: {$value}.");
            }
        }

        if ($this->input->isInteractive()) {
            if ($this->option('cache') === null) {
                $cacheMethod = $this->askForCacheMethod();
            }

            if ($this->option('template') === null) {
                $template = $this->askForTemplate();
            }

            if ($this->option('permissions') === null) {
                $permissionDriver = $this->askForPermissionDriver();
            }
        }

        $cacheMethod ??= null;
        $template ??= 'AdminLTE2';
        $permissionDriver ??= 'spatie';

        $duration = $this->option('duration');

        if ($duration === null && $this->input->isInteractive() && $cacheMethod !== null) {
            $duration = $this->askForText(
                'How many minutes should the sidebar remain cached?',
                '1440'
            );
        }

        $duration = $duration === null ? 1440 : filter_var($duration, FILTER_VALIDATE_INT);

        if ($duration === false || $duration < 1) {
            throw new LogicException('The cache duration must be a positive integer.');
        }

        $applicationNamespace = trim($this->laravel->getNamespace(), '\\');
        $defaultClass = $applicationNamespace . '\\Sidebars\\AdminSidebar';
        $interactive = $this->input->isInteractive();
        $sidebarClass = $this->option('class');
        $targetView = $this->option('target');
        $viewDataKey = $this->option('key');
        $middlewareAlias = $this->option('alias');

        if ($interactive) {
            $sidebarClass ??= $this->askForText('Which sidebar class should be created?', $defaultClass);
            $targetView ??= $this->askForText(
                'Which application view should receive the sidebar?',
                'admin.partials._sidebar'
            );
            $viewDataKey ??= $this->askForText(
                'Which view data key should contain the rendered sidebar?',
                'sidebar'
            );
            $middlewareAlias ??= $this->askForText(
                'Which route middleware alias should be registered?',
                'sidebar'
            );
        }

        $sidebarClass = $sidebarClass ?: $defaultClass;
        $targetView = $targetView ?: 'admin.partials._sidebar';
        $viewDataKey = $viewDataKey ?: 'sidebar';
        $middlewareAlias = $middlewareAlias ?: 'sidebar';

        if ($targetView === '' || $viewDataKey === '' || $middlewareAlias === '') {
            throw new LogicException('The target view, view data key, and middleware alias cannot be empty.');
        }

        return [
            'cache_method' => $cacheMethod,
            'cache_duration' => $duration,
            'template' => $template,
            'sidebar_class' => $sidebarClass,
            'target_view' => $targetView,
            'view_data_key' => $viewDataKey,
            'middleware_alias' => $middlewareAlias,
            'permission_driver' => $permissionDriver,
        ];
    }

    protected function askForCacheMethod(): ?string
    {
        return SidebarInstallChoices::cacheMethod(
            $this->selectOption('Which cache method would you like to use?', SidebarInstallChoices::CACHE, '0')
        );
    }

    protected function askForTemplate(): string
    {
        return SidebarInstallChoices::template(
            $this->selectOption('Which sidebar template would you like to use?', SidebarInstallChoices::TEMPLATES, '0')
        ) ?? 'AdminLTE2';
    }

    protected function askForPermissionDriver(): string
    {
        return SidebarInstallChoices::permissionDriver(
            $this->selectOption('Which permission driver would you like to use?', SidebarInstallChoices::PERMISSIONS, '0')
        ) ?? 'spatie';
    }

    protected function resolveCacheMethod($value): ?string
    {
        return SidebarInstallChoices::cacheMethod($value);
    }

    protected function resolveTemplate($value): ?string
    {
        return SidebarInstallChoices::template($value);
    }

    protected function resolvePermissionDriver($value): ?string
    {
        return SidebarInstallChoices::permissionDriver($value);
    }

    protected function selectOption(string $label, array $options, string $default): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->line($label);

            foreach ($options as $key => $option) {
                $this->line("  [{$key}] {$option}");
            }

            do {
                $selection = trim((string) $this->ask('Select an option', $default));
                $selectedKey = array_key_exists($selection, $options)
                    ? $selection
                    : array_search($selection, $options, true);

                if ($selectedKey === false) {
                    $this->error('Select one of the listed option numbers.');
                }
            } while ($selectedKey === false);

            return (string) $selectedKey;
        }

        if (PHP_OS_FAMILY !== 'Windows' && function_exists('Laravel\\Prompts\\select')) {
            return (string) \Laravel\Prompts\select(
                label: $label,
                options: $options,
                default: $default
            );
        }

        $selectedLabel = $this->choice(
            $label,
            array_values($options),
            array_search($default, array_keys($options), true)
        );
        $selectedKey = array_search($selectedLabel, $options, true);

        return $selectedKey === false ? $default : (string) $selectedKey;
    }

    protected function askForText(string $label, string $default): string
    {
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('Laravel\\Prompts\\text')) {
            return \Laravel\Prompts\text(
                label: $label,
                default: $default
            );
        }

        return $this->ask($label, $default);
    }

    protected function askForConfirmation(string $label, bool $default): bool
    {
        if (PHP_OS_FAMILY !== 'Windows' && function_exists('Laravel\\Prompts\\confirm')) {
            return \Laravel\Prompts\confirm(
                label: $label,
                default: $default
            );
        }

        return $this->confirm($label, $default);
    }

    protected function writeGeneratedFile(string $path, string $contents, string $question): bool
    {
        if (file_exists($path)) {
            $replace = (bool) $this->option('force');

            if (!$replace && $this->input->isInteractive()) {
                $replace = $this->askForConfirmation($question, false);
            }

            if (!$replace) {
                $this->line('Preserved ' . $path . '.');

                return false;
            }

            if (!copy($path, $path . '.bak')) {
                throw new LogicException('Unable to back up the existing file at ' . $path . '.');
            }
        }

        $directory = dirname($path);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new LogicException('Unable to create directory ' . $directory . '.');
        }

        if (file_put_contents($path, $contents) === false) {
            throw new LogicException('Unable to write file ' . $path . '.');
        }

        $this->line('Created ' . $path . '.');

        return true;
    }

    protected function shouldPublishViews(): bool
    {
        if ($this->option('publish-views')) {
            return true;
        }

        return $this->input->isInteractive()
            && $this->askForConfirmation(
                'Would you like to publish the package sidebar templates now?',
                false
            );
    }

    protected function publishViews(string $template): void
    {
        $force = (bool) $this->option('force');
        $destination = resource_path('views/vendor/sidebar');

        if (is_dir($destination) && !$force) {
            $force = $this->input->isInteractive()
                && $this->askForConfirmation(
                    'Published sidebar views already exist. Replace them with the selected template? This overwrites local edits.',
                    false
                );

            if (!$force) {
                $this->warn('Existing published sidebar views were preserved. Use --force to replace them.');

                return;
            }
        }

        $this->call('vendor:publish', [
            '--provider' => 'Maatwebsite\\Sidebar\\SidebarServiceProvider',
            '--tag' => 'sidebar-views-' . strtolower($template),
            '--force' => $force,
        ]);
    }

    protected function defaultCacheStoreSupportsTags(): bool
    {
        try {
            return method_exists(app('cache')->getStore(), 'tags');
        } catch (\Throwable $exception) {
            return false;
        }
    }
}
