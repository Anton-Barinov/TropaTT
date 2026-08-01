<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/module/Manifest.php';
require_once __DIR__ . '/../../system/library/module/PluginManager.php';

use Api\System\Library\Module\Manifest;
use Api\System\Library\Module\PluginManager;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function createTempModules(string $baseDir, array $modules): void
{
    $modulesDir = $baseDir . '/modules';
    if (!is_dir($modulesDir)) {
        mkdir($modulesDir, 0777, true);
    }
    foreach ($modules as $name => $config) {
        $moduleDir = $modulesDir . '/' . $name;
        if (!mkdir($moduleDir, 0777, true) && !is_dir($moduleDir)) {
            throw new RuntimeException("Cannot create {$moduleDir}");
        }
        file_put_contents($moduleDir . '/manifest.json', json_encode($config, JSON_PRETTY_PRINT));
    }
}

try {
    // Test 1: scanning non-existent directory returns empty array
    $pm1 = new PluginManager('/tmp/nonexistent_' . bin2hex(random_bytes(4)));
    $result1 = $pm1->discover();
    unitAssert($result1 === [], 'Non-existent directory must return empty array');
    echo '[OK] plugin_manager: non-existent directory returns empty' . PHP_EOL;

    // Test 2: scanning with valid manifest returns Manifest objects
    $base2 = sys_get_temp_dir() . '/crm_pm_unit_' . bin2hex(random_bytes(4));
    createTempModules($base2, [
        'crm.example-hello' => [
            'name' => 'crm.example-hello',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'Hello Module',
            'description' => 'Test module',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
        ],
    ]);
    $pm2 = new PluginManager($base2);
    $result2 = $pm2->discover();
    unitAssert(count($result2) === 1, 'Must discover 1 module');
    unitAssert(isset($result2['crm.example-hello']), 'Must discover crm.example-hello');
    $manifest = $result2['crm.example-hello'];
    unitAssert($manifest instanceof Manifest, 'Must return Manifest objects');
    unitAssert($manifest->name === 'crm.example-hello', 'Manifest name must match');
    unitAssert($manifest->version === '1.0.0', 'Manifest version must match');
    unitAssert($manifest->vendor === 'crm', 'Manifest vendor must match');
    unitAssert($manifest->title === 'Hello Module', 'Manifest title must match');
    echo '[OK] plugin_manager: valid manifest discovered' . PHP_EOL;

    // Test 3: scanning with invalid JSON skips the module
    $base3 = sys_get_temp_dir() . '/crm_pm_unit_' . bin2hex(random_bytes(4));
    $modulesDir3 = $base3 . '/modules';
    mkdir($modulesDir3, 0777, true);
    $moduleDir3 = $modulesDir3 . '/crm.broken';
    if (!mkdir($moduleDir3, 0777, true) && !is_dir($moduleDir3)) {
        throw new RuntimeException("Cannot create {$moduleDir3}");
    }
    file_put_contents($moduleDir3 . '/manifest.json', '{invalid json');
    // Also put a valid one
    createTempModules($base3, [
        'crm.valid' => [
            'name' => 'crm.valid',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'Valid',
            'description' => '',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
        ],
    ]);
    $pm3 = new PluginManager($base3);
    $result3 = $pm3->discover();
    unitAssert(count($result3) === 1, 'Must skip invalid JSON module');
    unitAssert(isset($result3['crm.valid']), 'Must still discover valid module');
    unitAssert(!isset($result3['crm.broken']), 'Must skip broken module');
    echo '[OK] plugin_manager: invalid JSON skipped' . PHP_EOL;

    // Test 4: validate() returns errors for missing api_routes
    $base4 = sys_get_temp_dir() . '/crm_pm_unit_' . bin2hex(random_bytes(4));
    createTempModules($base4, [
        'crm.with-routes' => [
            'name' => 'crm.with-routes',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'With Routes',
            'description' => '',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
            'api_routes' => 'api/config/routes.php',
        ],
    ]);
    $pm4 = new PluginManager($base4);
    $result4 = $pm4->discover();
    $manifest4 = $result4['crm.with-routes'];
    $errors4 = $pm4->validate($manifest4);
    unitAssert(count($errors4) > 0, 'Must report error for missing api_routes file');
    echo '[OK] plugin_manager: validate catches missing routes file' . PHP_EOL;

    // Test 5: validate() passes for valid manifest without optional routes
    $base5 = sys_get_temp_dir() . '/crm_pm_unit_' . bin2hex(random_bytes(4));
    createTempModules($base5, [
        'crm.minimal' => [
            'name' => 'crm.minimal',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'Minimal',
            'description' => '',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
        ],
    ]);
    $pm5 = new PluginManager($base5);
    $result5 = $pm5->discover();
    $manifest5 = $result5['crm.minimal'];
    $errors5 = $pm5->validate($manifest5);
    unitAssert($errors5 === [], 'Minimal valid module must pass validation');
    echo '[OK] plugin_manager: minimal module passes validation' . PHP_EOL;

    // Test 6: load() and isLoaded()
    $base6 = sys_get_temp_dir() . '/crm_pm_unit_' . bin2hex(random_bytes(4));
    createTempModules($base6, [
        'crm.loadable' => [
            'name' => 'crm.loadable',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'Loadable',
            'description' => '',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
        ],
    ]);
    $pm6 = new PluginManager($base6);
    $pm6->discover();
    unitAssert($pm6->isLoaded('crm.loadable') === false, 'Module must not be loaded before load()');
    $loaded = $pm6->load('crm.loadable');
    unitAssert($loaded === true, 'load() must return true for valid module');
    unitAssert($pm6->isLoaded('crm.loadable') === true, 'Module must be loaded after load()');
    unitAssert($pm6->isLoaded('crm.nonexistent') === false, 'Non-existent module must not be loaded');
    echo '[OK] plugin_manager: load() and isLoaded() work' . PHP_EOL;

    // Test 7: getActive() returns loaded modules
    $base7 = sys_get_temp_dir() . '/crm_pm_unit_' . bin2hex(random_bytes(4));
    createTempModules($base7, [
        'crm.active1' => [
            'name' => 'crm.active1',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'Active 1',
            'description' => '',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
        ],
        'crm.active2' => [
            'name' => 'crm.active2',
            'version' => '1.0.0',
            'vendor' => 'crm',
            'title' => 'Active 2',
            'description' => '',
            'core_version' => '>=1.0.0',
            'dependencies' => [],
        ],
    ]);
    $pm7 = new PluginManager($base7);
    $pm7->discover();
    $pm7->load('crm.active1');
    $active = $pm7->getActive();
    unitAssert(count($active) === 1, 'Only loaded modules must be active');
    unitAssert(isset($active['crm.active1']), 'Loaded module must be in active list');
    unitAssert(!isset($active['crm.active2']), 'Unloaded module must not be in active list');
    echo '[OK] plugin_manager: getActive() returns loaded modules' . PHP_EOL;

    // Test 8: getManifest() returns manifest or null
    $pm8 = new PluginManager($base2); // Re-use base2 which has crm.example-hello
    $pm8->discover();
    $m = $pm8->getManifest('crm.example-hello');
    unitAssert($m !== null, 'getManifest() must return manifest for discovered module');
    unitAssert($m->name === 'crm.example-hello', 'Manifest name must match');
    $m2 = $pm8->getManifest('crm.nonexistent');
    unitAssert($m2 === null, 'getManifest() must return null for unknown module');
    echo '[OK] plugin_manager: getManifest() works' . PHP_EOL;

    // Cleanup temp dirs
    foreach ([$base2, $base3, $base4, $base5, $base6, $base7] as $dir) {
        if (is_dir($dir)) {
            $mods = glob($dir . '/modules/*/manifest.json');
            if ($mods !== false) { array_map('unlink', $mods); }
            $modDirs = glob($dir . '/modules/*');
            if ($modDirs !== false) { array_map('rmdir', $modDirs); }
            if (is_dir($dir . '/modules')) { rmdir($dir . '/modules'); }
            rmdir($dir);
        }
    }

    echo '[OK] plugin_manager_unit' . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] plugin_manager_unit: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
