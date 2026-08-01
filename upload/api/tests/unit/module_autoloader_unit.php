<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/module/ModuleAutoloader.php';

use Api\System\Library\Module\ModuleAutoloader;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $base = sys_get_temp_dir() . '/crm_autoloader_unit_' . bin2hex(random_bytes(4));

    // Create a test module file
    $moduleDir = $base . '/modules/acme.reports/api/Controller';
    if (!mkdir($moduleDir, 0777, true) && !is_dir($moduleDir)) {
        throw new RuntimeException("Cannot create {$moduleDir}");
    }
    file_put_contents(
        $moduleDir . '/ReportController.php',
        "<?php\nnamespace Module\\Acme\\Reports\\Controller;\nclass ReportController {}\n"
    );

    $autoloader = new ModuleAutoloader($base);
    $autoloader->registerModule('reports', 'acme');
    $autoloader->register();

    // Test: class loads from module api/ directory
    $loaded = class_exists('Module\Acme\Reports\Controller\ReportController');
    unitAssert($loaded, 'Module class must be loadable via ModuleAutoloader');
    echo '[OK] module_autoloader: class loaded from module api/' . PHP_EOL;

    // Test: non-module class is not affected
    unitAssert(
        class_exists('stdClass'),
        'Existing classes must still be loadable'
    );
    echo '[OK] module_autoloader: existing classes unaffected' . PHP_EOL;

    // Cleanup
    array_map('unlink', glob($base . '/modules/acme.reports/api/Controller/ReportController.php'));
    array_map('rmdir', glob($base . '/modules/acme.reports/api/Controller'));
    array_map('rmdir', glob($base . '/modules/acme.reports/api'));
    array_map('rmdir', glob($base . '/modules/acme.reports'));
    rmdir($base . '/modules');
    rmdir($base);

    echo '[OK] module_autoloader_unit' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] module_autoloader_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
