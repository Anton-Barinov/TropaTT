<?php
declare(strict_types=1);

namespace Web\System\Core;

use Web\Controller\Common\NotFoundController;

final class Router
{
    /** @var array<string, array{0: class-string, 1: string}> */
    private array $routes;

    public function __construct(array $routes, private readonly string $baseDir)
    {
        $this->routes = $routes;
    }

    /**
     * Add multiple routes from a module.
     * Keys are prefixed with module-{name}- to avoid collisions with core routes.
     *
     * @param array<string, array{0: class-string, 1: string}> $routes
     */
    public function addRoutes(array $routes): void
    {
        foreach ($routes as $key => $value) {
            if (isset($this->routes[$key])) {
                error_log("[Router] Duplicate route key ignored: {$key}");
                continue;
            }
            $this->routes[$key] = $value;
        }
    }

    public function dispatch(string $route): void
    {
        if (!isset($this->routes[$route])) {
            $controller = new NotFoundController($this->baseDir);
            $controller->index($route);
            return;
        }

        [$class, $method] = $this->routes[$route];
        $instance = new $class($this->baseDir);
        $instance->{$method}();
    }

    /** @return array<string, array{0: class-string, 1: string}> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
