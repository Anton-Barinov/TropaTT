<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $apiRoot = dirname(__DIR__, 2);

    $autoloader = new Api\System\Library\Support\Autoloader($apiRoot);
    $autoloader->register();

    $routes = require $apiRoot . '/config/routes.php';
    unitAssert(is_array($routes), 'api/config/routes.php must return array');
    unitAssert(count($routes) > 0, 'api/config/routes.php must contain at least one route');

    $errors = [];
    $warnings = [];
    $seenPatterns = [];
    $mutatingMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];

    foreach ($routes as $i => $route) {
        if (!isset($route['controller'])) {
            $errors[] = "Index {$i}: missing 'controller'";
            continue;
        }
        if (!isset($route['action'])) {
            $errors[] = "Index {$i}: missing 'action'";
            continue;
        }
        if (!isset($route['methods'])) {
            $errors[] = "Index {$i} ({$route['pattern']}): missing 'methods'";
            continue;
        }

        $class = $route['controller'];
        $action = $route['action'];
        $methods = (array)$route['methods'];
        $pattern = $route['pattern'] ?? '';

        if (!class_exists($class)) {
            $errors[] = "Index {$i} ({$pattern}): class {$class} not found";
            continue;
        }
        if (!method_exists($class, $action)) {
            $errors[] = "Index {$i} ({$pattern}): method {$class}::{$action}() not found";
            continue;
        }

        if (!isset($route['auth'])) {
            $warnings[] = "Index {$i} ({$pattern}): missing 'auth' field";
        }

        // SEC-004: Contract test — mutating routes with auth=true must declare authz_note or required_permissions
        $authRequired = (bool)($route['auth'] ?? true);
        if ($authRequired) {
            $routeMethods = array_map('strtoupper', $methods);
            $hasMutating = (bool)array_intersect($routeMethods, $mutatingMethods);
            $hasPermsOrNote = !empty($route['required_permissions'] ?? []) || !empty($route['authz_note'] ?? '');
            if ($hasMutating && !$hasPermsOrNote) {
                $errors[] = "Index {$i} ({$pattern}): mutating route [" . implode(',', $routeMethods) . "] requires 'required_permissions' or 'authz_note' (SEC-004)";
            }
        }

        $sortedMethods = $methods;
        sort($sortedMethods);
        $key = $pattern . '|' . implode(',', $sortedMethods);

        if (isset($seenPatterns[$key])) {
            $warnings[] = "Index {$i} ({$pattern}): duplicate route pattern+methods (also at index {$seenPatterns[$key]})";
        }
        $seenPatterns[$key] = $i;
    }

    if ($warnings !== []) {
        fwrite(STDERR, 'WARNINGS:' . PHP_EOL . implode(PHP_EOL, $warnings) . PHP_EOL);
    }

    unitAssert(
        $errors === [],
        'API route structure errors:' . PHP_EOL . implode(PHP_EOL, $errors)
    );

    echo '[OK] api_routes_structure_unit: ' . count($routes) . ' routes, all valid' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] api_routes_structure_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
