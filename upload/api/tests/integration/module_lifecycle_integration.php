<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
use Api\System\Library\Support\Autoloader;

$autoloader = new Autoloader(__DIR__ . '/../..');
$autoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        dirname(__DIR__, 2) . '/.env',
        __DIR__ . '/../../.env',
        dirname(__DIR__, 2) . '/.env.local',
    ]);
}

use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\ServiceProviderRegistry;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

try {
    $projectRoot = dirname(__DIR__, 2);
    $baseDir = sys_get_temp_dir() . '/crm_mod_int_' . bin2hex(random_bytes(4));
    mkdir($baseDir . '/modules', 0777, true);

    // Create test module
    $moduleName = 'test.integration';
    $moduleDir = $baseDir . '/modules/' . $moduleName;
    mkdir($moduleDir, 0777, true);
    mkdir($moduleDir . '/api', 0777, true);
    mkdir($moduleDir . '/web', 0777, true);
    mkdir($moduleDir . '/web/config', 0777, true);
    mkdir($moduleDir . '/api/migrations', 0777, true);

    file_put_contents($moduleDir . '/manifest.json', json_encode([
        'name' => $moduleName,
        'version' => '2.0.0',
        'vendor' => 'test',
        'title' => 'Integration Test Module',
        'description' => 'Auto-generated test module',
        'core_version' => '>=1.0.0',
        'dependencies' => [],
        'require_permissions' => [],
        'migrations' => 'api/migrations/',
        'hooks' => [],
        'menu_items' => [],
        'service_provider' => null,
        'config_defaults' => ['test_key' => 'test_value'],
    ], JSON_PRETTY_PRINT));

    file_put_contents($moduleDir . '/api/migrations/001_test.sql', "CREATE TABLE IF NOT EXISTS test_integration_test (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT);");
    file_put_contents($moduleDir . '/api/migrations/001_test_rollback.sql', "DROP TABLE IF EXISTS test_integration_test;");

    file_put_contents($moduleDir . '/web/config/routes.php', "<?php\nreturn [];\n");

    echo "=== Test 1: PluginManager discovery ===\n";
    $pm = new PluginManager($baseDir);
    $discovered = $pm->discover();
    unitAssert(count($discovered) === 1, "Must discover 1 module");
    unitAssert(isset($discovered[$moduleName]), "Must discover {$moduleName}");
    $manifest = $discovered[$moduleName];
    unitAssert($manifest->version === '2.0.0', "Version must match");
    unitAssert($manifest->configDefaults === ['test_key' => 'test_value'], "configDefaults must match");
    echo "[OK] Test 1: discovery\n";

    echo "=== Test 2: PluginManager validation ===\n";
    $errors = $pm->validate($manifest);
    unitAssert($errors === [], "Valid module must have no errors, got: " . json_encode($errors));
    echo "[OK] Test 2: validation\n";

    echo "=== Test 3: Module load ===\n";
    unitAssert($pm->isLoaded($moduleName) === false, "Not loaded before load()");
    $loaded = $pm->load($moduleName);
    unitAssert($loaded === true, "load() must succeed");
    unitAssert($pm->isLoaded($moduleName) === true, "Loaded after load()");
    echo "[OK] Test 3: load\n";

    echo "=== Test 4: Dependency order ===\n";
    $order = $pm->resolveDependencyOrder();
    unitAssert(in_array($moduleName, $order, true), "Must be in dependency order");
    echo "[OK] Test 4: dependency order\n";

    echo "=== Test 5: Cycle detection ===\n";
    $cycles = $pm->detectCycles();
    unitAssert($cycles === [], "No cycles expected");
    echo "[OK] Test 5: no cycles\n";

    echo "=== Test 6: Core compatibility ===\n";
    $compat = $pm->checkCoreCompatibility($manifest, '2.0.0');
    unitAssert($compat === true, "Must be compatible with core >=1.0.0");
    echo "[OK] Test 6: core compat\n";

    echo "=== Test 7: Config integration ===\n";
    $cfg = new Config();
    $cfg->load($projectRoot . '/api/config/database.php', 'database');
    // Use SQLite for tests to avoid cross-driver SQL issues
    $testDb = sys_get_temp_dir() . '/crm_test_' . bin2hex(random_bytes(4)) . '.sqlite';
    $cfg->merge('database', ['default' => 'sqlite', 'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $testDb]]]);
    $cm = new ConnectionManager($cfg);
    $pdo = $cm->connect();
    $dbConfig = $cfg->get('database.connections.sqlite');
    $driver = 'sqlite';

    $mc = new ModuleConfig($pdo);
    $mc->ensureTable($driver);

    $registry = $mc->getRegistry($moduleName);
    unitAssert($registry === null, "Module must not be registered yet");
    echo "[OK] Test 7: config pre-registry\n";

    echo "=== Test 8: Module registration ===\n";
    $mc->register($moduleName, $manifest->vendor, $manifest->version);
    $registry = $mc->getRegistry($moduleName);
    unitAssert($registry !== null, "Module must be registered");
    unitAssert((string)$registry['version'] === '2.0.0', "Version in registry must match");
    unitAssert((int)$registry['is_active'] === 0, "Must not be active initially");
    echo "[OK] Test 8: registration\n";

    echo "=== Test 9: Config init ===\n";
    $mc->initFromManifest($moduleName, $manifest);
    $config = $mc->getAll($moduleName);
    unitAssert($config === ['test_key' => 'test_value'], "Config must match defaults");
    echo "[OK] Test 9: config init\n";

    echo "=== Test 10: Config get/set ===\n";
    $mc->set($moduleName, 'extra_key', 42);
    unitAssert($mc->get($moduleName, 'extra_key') === 42, "Set/get must work");
    $mc->setMultiple($moduleName, ['a' => 1, 'b' => 2]);
    unitAssert($mc->get($moduleName, 'a') === 1, "setMultiple must work");
    $mc->delete($moduleName, 'a');
    unitAssert($mc->get($moduleName, 'a', 'default') === 'default', "delete must work");
    echo "[OK] Test 10: config CRUD\n";

    echo "=== Test 11: Module activation ===\n";
    $mc->setActive($moduleName);
    $registry = $mc->getRegistry($moduleName);
    unitAssert((int)$registry['is_active'] === 1, "Must be active");
    unitAssert($registry['activated_at'] !== null, "activated_at must be set");
    echo "[OK] Test 11: activation\n";

    echo "=== Test 12: Migration runner ===\n";
    $mm = new ModuleMigrationRunner($pdo);
    $mm->ensureTable($driver);
    $migResult = $mm->migrate($moduleName, $moduleDir . '/api/migrations/');
    unitAssert(count($migResult['applied']) === 1, "Must apply 1 migration");
    unitAssert($migResult['errors'] === [], "No migration errors");
    $status = $mm->getStatus($moduleName, $moduleDir . '/api/migrations/');
    unitAssert(($status['migrations'][0]['applied'] ?? false) === true, "Migration must show as applied");
    echo "[OK] Test 12: migrations\n";

    echo "=== Test 13: Migration rollback ===\n";
    $rollback = $mm->rollback($moduleName, $moduleDir . '/api/migrations/', 1);
    unitAssert(count($rollback['rolled_back']) === 1, "Must rollback 1 migration");
    $statusAfter = $mm->getStatus($moduleName, $moduleDir . '/api/migrations/');
    unitAssert(($statusAfter['migrations'][0]['applied'] ?? true) === false, "Migration must show as not applied");
    $mm->migrate($moduleName, $moduleDir . '/api/migrations/');
    echo "[OK] Test 13: rollback\n";

    echo "=== Test 14: getActiveModules ===\n";
    $active = $mc->getActiveModules();
    $found = false;
    foreach ($active as $a) {
        if ((string)$a['module_name'] === $moduleName) { $found = true; break; }
    }
    unitAssert($found, "Must be in active modules list");
    echo "[OK] Test 14: active modules list\n";

    echo "=== Test 15: Module deactivation ===\n";
    $mc->setInactive($moduleName);
    $registry = $mc->getRegistry($moduleName);
    unitAssert((int)$registry['is_active'] === 0, "Must be inactive after deactivation");
    echo "[OK] Test 15: deactivation\n";

    echo "=== Test 16: Module removal ===\n";
    $mc->unregister($moduleName);
    $registry = $mc->getRegistry($moduleName);
    unitAssert($registry === null, "Must be removed from registry");
    echo "[OK] Test 16: removal\n";

    // Cleanup
    foreach (glob($moduleDir . '/api/migrations/*') as $f) { unlink($f); }
    foreach (glob($moduleDir . '/web/config/*') as $f) { unlink($f); }
    rmdir($moduleDir . '/api/migrations');
    rmdir($moduleDir . '/api');
    rmdir($moduleDir . '/web/config');
    rmdir($moduleDir . '/web');
    unlink($moduleDir . '/manifest.json');
    rmdir($moduleDir);
    rmdir($baseDir . '/modules');
    rmdir($baseDir);
    unlink($testDb);

    echo "\n========================================\n";
    echo "  ALL 16 MODULE INTEGRATION TESTS PASSED\n";
    echo "========================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n========================================\n");
    fwrite(STDERR, "  TEST FAILED\n");
    fwrite(STDERR, "  " . $e->getMessage() . "\n");
    fwrite(STDERR, "========================================\n");
    exit(1);
}
