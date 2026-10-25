<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

/** Resolves module menu entries only to routes declared by that module. */
final class ModuleMenuRouteResolver
{
    /** @return array<int,string> */
    public static function registeredRoutes(string $modulesDir, string $moduleName, ?string $webRoutes): array
    {
        if ($webRoutes === null || $webRoutes === ''
            || preg_match('/^[a-z0-9]+\.[a-z0-9][a-z0-9-]*$/D', $moduleName) !== 1) {
            return [];
        }

        $realModulesDir = realpath($modulesDir);
        if ($realModulesDir === false || !is_dir($realModulesDir)) {
            return [];
        }
        $moduleDir = realpath($realModulesDir . DIRECTORY_SEPARATOR . $moduleName);
        if ($moduleDir === false || !is_dir($moduleDir)
            || !str_starts_with($moduleDir, $realModulesDir . DIRECTORY_SEPARATOR)) {
            return [];
        }

        $routeFile = realpath($moduleDir . DIRECTORY_SEPARATOR . $webRoutes);
        if ($routeFile === false || !is_file($routeFile)
            || !str_starts_with($routeFile, $moduleDir . DIRECTORY_SEPARATOR)) {
            return [];
        }

        try {
            $definitions = require $routeFile;
        } catch (\Throwable) {
            return [];
        }
        if (!is_array($definitions)) {
            return [];
        }

        $routes = [];
        foreach (array_keys($definitions) as $route) {
            if (is_string($route) && preg_match('/^[a-z][a-z0-9-]*$/D', $route) === 1) {
                $routes[] = $route;
            }
        }
        return array_values(array_unique($routes));
    }

    /** @param array<int,string> $registeredRoutes */
    public static function resolve(string $requestedRoute, array $registeredRoutes): ?string
    {
        $requestedRoute = trim($requestedRoute);
        if ($registeredRoutes === []) {
            return null;
        }
        if (preg_match('/^[a-z][a-z0-9-]*$/D', $requestedRoute) !== 1) {
            // Older manifests may store a same-site href instead of the route
            // key. Accept only the expected relative CRM page form.
            if (str_starts_with($requestedRoute, 'index.php?')) {
                $query = parse_url($requestedRoute, PHP_URL_QUERY);
                $queryValues = [];
                if (is_string($query)) {
                    parse_str($query, $queryValues);
                }
                $requestedRoute = is_string($queryValues['route'] ?? null)
                    ? $queryValues['route']
                    : '';
            }
            if (preg_match('/^[a-z][a-z0-9-]*$/D', $requestedRoute) !== 1) {
                return null;
            }
        }
        if (in_array($requestedRoute, $registeredRoutes, true)) {
            return $requestedRoute;
        }
        return count($registeredRoutes) === 1 ? $registeredRoutes[0] : null;
    }
}
