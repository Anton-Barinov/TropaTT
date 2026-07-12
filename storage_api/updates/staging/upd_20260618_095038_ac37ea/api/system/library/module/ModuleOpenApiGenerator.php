<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

final class ModuleOpenApiGenerator
{
    /**
     * Generate OpenAPI 3.0 spec for a module's routes.
     * @param array<int, array<string, mixed>> $routes
     * @return array<string, mixed>
     */
    public function generate(string $moduleName, array $routes): array
    {
        $paths = [];

        foreach ($routes as $route) {
            $pattern = str_replace(
                ['{', '}'],
                ['{', '}'],
                $route['pattern'] ?? $route['route'] ?? '/'
            );

            $methods = $route['methods'] ?? ['GET'];
            foreach ($methods as $method) {
                $paths[$pattern][strtolower($method)] = [
                    'operationId' => $moduleName . '.' . ($route['action'] ?? 'index'),
                    'responses' => [
                        '200' => ['description' => 'Success'],
                    ],
                ];

                if (isset($route['auth']) && $route['auth']) {
                    $paths[$pattern][strtolower($method)]['security'] = [['bearer' => []]];
                }
            }
        }

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => "Module: {$moduleName}",
                'version' => '1.0.0',
            ],
            'paths' => $paths,
        ];
    }

    /**
     * Generate aggregated spec from all modules.
     * @param array<string, array<int, array<string, mixed>>> $allModuleRoutes
     * @return array<string, mixed>
     */
    public function generateAggregated(array $allModuleRoutes): array
    {
        $allPaths = [];

        foreach ($allModuleRoutes as $moduleName => $routes) {
            $spec = $this->generate($moduleName, $routes);
            foreach ($spec['paths'] as $path => $methods) {
                $allPaths[$path] = $methods;
            }
        }

        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'CRM Module API',
                'version' => '1.0.0',
            ],
            'paths' => $allPaths,
        ];
    }
}
