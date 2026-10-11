<?php

namespace Maatwebsite\Sidebar\Console;

use Maatwebsite\Sidebar\Exceptions\LogicException;

class SidebarInstaller
{
    public function renderSidebar(string $class): string
    {
        [$namespace, $className] = $this->splitClassName($class);
        $stub = file_get_contents(__DIR__ . '/../../resources/stubs/AdminSidebar.stub');

        return str_replace(
            ['{{NAMESPACE}}', '{{CLASS_NAME}}'],
            [$namespace, $className],
            $stub
        );
    }

    public function renderConfig(array $options): string
    {
        $stub = file_get_contents(__DIR__ . '/../../resources/stubs/sidebar-config.stub');
        $replacements = [
            '{{CACHE_METHOD}}' => var_export($options['cache_method'], true),
            '{{CACHE_DURATION}}' => (string) $options['cache_duration'],
            '{{TEMPLATE}}' => var_export($options['template'], true),
            '{{SIDEBAR_CLASS}}' => '\\' . ltrim($options['sidebar_class'], '\\') . '::class',
            '{{TARGET_VIEW}}' => var_export($options['target_view'], true),
            '{{VIEW_DATA_KEY}}' => var_export($options['view_data_key'], true),
            '{{MIDDLEWARE_ALIAS}}' => var_export($options['middleware_alias'], true),
            '{{PERMISSION_DRIVER}}' => var_export($options['permission_driver'], true),
        ];

        return strtr($stub, $replacements);
    }

    public function renderViewOutputExample(string $viewDataKey): string
    {
        return '{!! $' . $viewDataKey . ' !!}';
    }

    public function getSidebarPath(string $class, string $applicationNamespace, string $applicationPath): string
    {
        $class = ltrim($class, '\\');
        $applicationNamespace = trim($applicationNamespace, '\\') . '\\';

        if (!str_starts_with($class, $applicationNamespace)) {
            throw new LogicException(
                'The generated sidebar class must use the application namespace.'
            );
        }

        [$namespace, $className] = $this->splitClassName($class);
        $relativeNamespace = trim(substr($namespace, strlen(rtrim($applicationNamespace, '\\'))), '\\');
        $relativePath = $relativeNamespace === ''
            ? $className . '.php'
            : str_replace('\\', DIRECTORY_SEPARATOR, $relativeNamespace)
                . DIRECTORY_SEPARATOR . $className . '.php';

        return rtrim($applicationPath, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $relativePath;
    }

    protected function splitClassName(string $class): array
    {
        $class = ltrim($class, '\\');

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/', $class)) {
            throw new LogicException('The sidebar class must be a fully qualified PHP class name.');
        }

        $separator = strrpos($class, '\\');

        return [substr($class, 0, $separator), substr($class, $separator + 1)];
    }
}
