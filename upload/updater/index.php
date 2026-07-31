<?php
declare(strict_types=1);

// Shared hosting PHP-FPM often caps max_execution_time at 30s. Update
// apply/rollback (backup + file copy + migrations) can exceed that on slow
// hosts, so lift the limit for the updater request only.
if (function_exists('set_time_limit') && !@set_time_limit(600)) {
    @ini_set('max_execution_time', '600');
}

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
