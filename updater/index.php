<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'Updater\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/src/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

try {
    $kernel = new Updater\UpdaterKernel(dirname(__DIR__));
    $kernel->handle();
} catch (Throwable $e) {
    require __DIR__ . '/rescue.php';
}
