<?php
declare(strict_types=1);

namespace Api\System\Library\Router;

use Api\System\Library\Http\Request;

final class Router
{
    /** @var array<int,array<string,mixed>> */
    private array $routes = [];

    /** @param array<string,mixed> $spec */
    public function add(array $spec): void
    {
        $this->routes[] = $spec;
    }

    /** @param array<int,array<string,mixed>> $routes */
    public function addMany(array $routes): void
    {
        foreach ($routes as $route) {
            $this->add($route);
        }
    }

    /**
     * Add routes from a module with automatic prefix.
     * Module routes are prefixed with /_module/{vendor}.{name}/ to avoid collisions.
     *
     * @param array<int,array<string,mixed>> $routes Raw module routes
     * @param string $modulePrefix Module prefix in format vendor.name
     */
    public function addManyFromModule(array $routes, string $modulePrefix): void
    {
        $prefix = '/_module/' . $modulePrefix . '/';

        foreach ($routes as $route) {
            $route = $this->normalizeModuleRoute($route, $prefix);
            $this->add($route);
        }
    }

    /**
     * @param array<string,mixed> $route
     * @return array<string,mixed>
     */
    private function normalizeModuleRoute(array $route, string $prefix): array
    {
        $pattern = $route['route'] ?? '';
        if ($pattern !== '' && !str_starts_with($pattern, '/')) {
            $pattern = '/' . $pattern;
        }
        $route['pattern'] = rtrim($prefix, '/') . $pattern;
        unset($route['route']);

        if (!isset($route['sse'])) {
            $route['sse'] = false;
        }
        if (!isset($route['binary'])) {
            $route['binary'] = false;
        }

        return $route;
    }

    /** @return array<string,mixed>|null */
    public function match(Request $request): ?array
    {
        $routePath = $this->extractRoutePath($request);

        foreach ($this->routes as $route) {
            $methods = $route['methods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'];
            if (!in_array($request->method, $methods, true)) {
                continue;
            }

            $pattern = $route['pattern'];

            $pattern = $route['pattern'];
            $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $pattern);
            $regex = '#^' . $regex . '$#';

            if (!preg_match($regex, $routePath, $matches)) {
                continue;
            }

            $params = [];
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $params[$key] = $value;
                }
            }

            return [
                'controller' => $route['controller'],
                'action' => $route['action'],
                'auth' => (bool)($route['auth'] ?? true),
                'required_permissions' => $route['required_permissions'] ?? [],
                'params' => $params,
                'route_path' => $routePath,
                'route_name' => $route['name'] ?? $pattern,
                'sse' => (bool)($route['sse'] ?? false),
                'binary' => (bool)($route['binary'] ?? false),
            ];
        }

        return null;
    }

    private function extractRoutePath(Request $request): string
    {
        if (!empty($request->query['route'])) {
            $r = '/' . ltrim((string)$request->query['route'], '/');
            return preg_replace('#/+#', '/', $r) ?: '/';
        }

        $path = $request->path;
        $path = preg_replace('#^/api/index\.php#', '', $path);
        $path = preg_replace('#^/index\.php#', '', (string)$path);

        if (str_starts_with((string)$path, '/v1/')) {
            $path = '/api' . $path;
        }

        if ($path === '' || $path === false) {
            $path = '/';
        }

        return preg_replace('#/+#', '/', $path) ?: '/';
    }
}
