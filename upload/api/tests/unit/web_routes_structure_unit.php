<?php
declare(strict_types=1);

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $webRoot = dirname(__DIR__, 3) . '/web';

    spl_autoload_register(static function (string $class) use ($webRoot): void {
        $prefixes = [
            'Web\\System\\' => $webRoot . '/system/',
            'Web\\Controller\\' => $webRoot . '/controller/',
        ];
        foreach ($prefixes as $prefix => $pathBase) {
            if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $relativePath = str_replace('\\', '/', $relative) . '.php';
            $fullPath = $pathBase . $relativePath;
            if (is_file($fullPath)) {
                require_once $fullPath;
            }
        }
    });

    $routes = require $webRoot . '/config/routes.php';
    unitAssert(is_array($routes), 'web/config/routes.php must return array');
    unitAssert(count($routes) > 0, 'web/config/routes.php must contain at least one route');

    $errors = [];
    $routeKeys = [];

    foreach ($routes as $key => $spec) {
        $routeKeys[] = $key;

        unitAssert(
            is_array($spec) && count($spec) >= 2,
            "Route '{$key}': spec must be [class, method]"
        );

        [$class, $method] = $spec;

        if (!class_exists($class)) {
            $errors[] = "Route '{$key}': class {$class} not found";
            continue;
        }

        if (!method_exists($class, $method)) {
            $errors[] = "Route '{$key}': method {$class}::{$method}() not found";
            continue;
        }
    }

    $duplicates = array_diff_assoc($routeKeys, array_unique($routeKeys));
    if ($duplicates !== []) {
        $errors[] = 'Duplicate route keys: ' . implode(', ', array_keys($duplicates));
    }

    unitAssert(
        $errors === [],
        'Web route structure errors: ' . PHP_EOL . implode(PHP_EOL, $errors)
    );

    echo '[OK] web_routes_structure_unit: ' . count($routes) . ' routes, all valid' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_routes_structure_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
