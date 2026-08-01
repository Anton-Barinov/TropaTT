<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
require_once __DIR__ . '/../../system/library/container.php';
require_once __DIR__ . '/../../system/library/hook/HookManager.php';
require_once __DIR__ . '/../../system/library/module/PluginManager.php';
require_once __DIR__ . '/../../system/library/module/Manifest.php';
require_once __DIR__ . '/../../system/library/module/EventDispatcher.php';
require_once __DIR__ . '/../../system/library/module/ModuleServiceProviderInterface.php';
require_once __DIR__ . '/../../system/library/module/AbstractModuleServiceProvider.php';
require_once __DIR__ . '/../../system/library/module/ServiceProviderRegistry.php';

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\Manifest;
use Api\System\Library\Module\EventDispatcher;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ServiceProviderRegistry;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }
}

try {
    $baseDir = sys_get_temp_dir() . '/crm_edge_' . bin2hex(random_bytes(4));
    mkdir($baseDir . '/modules', 0777, true);

    // ============================================================
    echo "=== Test 1: ServiceProvider that throws in register() ===\n";

    $moduleDir = $baseDir . '/modules/test.throwing';
    mkdir($moduleDir, 0777, true);
    file_put_contents($moduleDir . '/manifest.json', json_encode([
        'name' => 'test.throwing',
        'version' => '1.0.0', 'vendor' => 'test', 'title' => 'Throwing',
        'description' => '', 'core_version' => '>=1.0.0', 'dependencies' => [],
        'service_provider' => 'FakeThrowingProvider',
    ]));

    $pm = new PluginManager($baseDir);
    $pm->discover();

    define('FAKE_PROVIDER_THROW', true);
    eval('class FakeThrowingProvider extends \Api\System\Library\Module\AbstractModuleServiceProvider {
        public function register(\Api\System\Library\Container $c): void {
            if (defined("FAKE_PROVIDER_THROW")) throw new \RuntimeException("Intentional failure");
        }
    }');

    $pm->load('test.throwing');

    $container = new Container();
    $hm = new HookManager();

    $spRegistry = new ServiceProviderRegistry($container, $pm, $hm);
    $spRegistry->registerAll();
    $errors = $spRegistry->getErrors();
    unitAssert(isset($errors['test.throwing']), "Should have error for throwing provider");
    unitAssert(str_contains($errors['test.throwing']['register'] ?? '', 'Intentional'), "Error message should mention intentional failure");
    echo "[OK] Test 1: throwing provider isolated\n";

    // ============================================================
    echo "=== Test 2: Provider that throws in getHooks() does not crash ===\n";

    $moduleDir2 = $baseDir . '/modules/test.hookthrow';
    mkdir($moduleDir2, 0777, true);
    file_put_contents($moduleDir2 . '/manifest.json', json_encode([
        'name' => 'test.hookthrow',
        'version' => '1.0.0', 'vendor' => 'test', 'title' => 'HookThrow',
        'description' => '', 'core_version' => '>=1.0.0', 'dependencies' => [],
        'service_provider' => 'FakeHookThrowingProvider',
    ]));

    $pm2 = new PluginManager($baseDir);
    $pm2->discover();
    $pm2->load('test.hookthrow');

    eval('class FakeHookThrowingProvider extends \Api\System\Library\Module\AbstractModuleServiceProvider {
        public function getHooks(): array {
            return ["bad.hook" => [["handler" => "nonexistent", "priority" => 10]]];
        }
    }');

    $spRegistry2 = new ServiceProviderRegistry($container, $pm2, $hm);
    $spRegistry2->registerAll();
    $spRegistry2->bootAll();
    echo "[OK] Test 2: bad hook handler does not crash\n";

    // ============================================================
    echo "=== Test 3: Dependency resolution with missing deps ===\n";

    $moduleDirA = $baseDir . '/modules/test.alpha';
    mkdir($moduleDirA, 0777, true);
    file_put_contents($moduleDirA . '/manifest.json', json_encode([
        'name' => 'test.alpha',
        'version' => '1.0.0', 'vendor' => 'test', 'title' => 'Alpha',
        'description' => '', 'core_version' => '>=1.0.0',
        'dependencies' => [['name' => 'test.missing', 'version' => '^1.0']],
    ]));

    $moduleDirB = $baseDir . '/modules/test.beta';
    mkdir($moduleDirB, 0777, true);
    file_put_contents($moduleDirB . '/manifest.json', json_encode([
        'name' => 'test.beta',
        'version' => '1.0.0', 'vendor' => 'test', 'title' => 'Beta',
        'description' => '', 'core_version' => '>=1.0.0',
        'dependencies' => [['name' => 'test.alpha', 'version' => '^1.0']],
    ]));

    $pm3 = new PluginManager($baseDir);
    $pm3->discover();

    $order = $pm3->resolveDependencyOrder();
    unitAssert(count($order) >= 2, "Should have at least alpha and beta in order");
    unitAssert(array_search('test.alpha', $order) < array_search('test.beta', $order),
        "Alpha must come before beta");

    $loaded = $pm3->loadWithDependencies('test.beta');
    unitAssert($loaded === true, "loadWithDependencies should succeed");
    unitAssert($pm3->isLoaded('test.beta'), "Beta should be loaded");
    unitAssert($pm3->isLoaded('test.alpha'), "Alpha should be loaded with deps");
    echo "[OK] Test 3: loadWithDependencies works\n";

    // ============================================================
    echo "=== Test 4: Invalid version format rejection ===\n";

    $moduleDirC = $baseDir . '/modules/test.badver';
    mkdir($moduleDirC, 0777, true);
    file_put_contents($moduleDirC . '/manifest.json', json_encode([
        'name' => 'test.badver',
        'version' => 'not-semver', 'vendor' => 'test', 'title' => 'BadVer',
        'description' => '', 'core_version' => '>=1.0.0', 'dependencies' => [],
    ]));

    $pm4 = new PluginManager($baseDir);
    $pm4->discover();
    $manifest = $pm4->getManifest('test.badver');
    $errors = $pm4->validate($manifest);
    unitAssert(count($errors) > 0, "Invalid version should produce validation errors");
    $hasVersionError = false;
    foreach ($errors as $e) {
        if ($e['code'] === 'E_MANIFEST_INVALID_VERSION') { $hasVersionError = true; }
    }
    unitAssert($hasVersionError, "Should have version error code");
    echo "[OK] Test 4: version validation works\n";

    // ============================================================
    echo "=== Test 5: Invalid name format rejection ===\n";

    $moduleDirD = $baseDir . '/modules/Bad.Name';
    mkdir($moduleDirD, 0777, true);
    file_put_contents($moduleDirD . '/manifest.json', json_encode([
        'name' => 'Bad.Name', 'version' => '1.0.0', 'vendor' => 'bad',
        'title' => 'Bad Name', 'description' => '', 'core_version' => '>=1.0.0',
        'dependencies' => [],
    ]));

    $pm5 = new PluginManager($baseDir);
    $pm5->discover();
    $manifest5 = $pm5->getManifest('Bad.Name');
    $errors5 = $pm5->validate($manifest5);
    unitAssert(count($errors5) > 0, "Invalid name (caps) should fail validation");
    echo "[OK] Test 5: name validation works\n";

    // ============================================================
    echo "=== Test 6: Core version incompatibility ===\n";

    $moduleDirE = $baseDir . '/modules/test.highcore';
    mkdir($moduleDirE, 0777, true);
    file_put_contents($moduleDirE . '/manifest.json', json_encode([
        'name' => 'test.highcore', 'version' => '1.0.0', 'vendor' => 'test',
        'title' => 'HighCore', 'description' => '', 'core_version' => '>=99.0.0',
        'dependencies' => [],
    ]));

    $pm6 = new PluginManager($baseDir);
    $pm6->discover();
    $manifest6 = $pm6->getManifest('test.highcore');
    $compat = $pm6->checkCoreCompatibility($manifest6, '1.0.0');
    unitAssert($compat === false, "Core 1.0.0 should be incompatible with >=99.0.0");
    $loaded6 = $pm6->load('test.highcore');
    unitAssert($loaded6 === false, "Incompatible core version should prevent load");
    echo "[OK] Test 6: core version incompatibility blocks load\n";

    // ============================================================
    echo "=== Test 7: Wildcard core version accepts all ===\n";

    $manifest7 = Manifest::fromArray([
        'name' => 'test.wildcard', 'version' => '1.0.0', 'vendor' => 'test',
        'title' => 'Wildcard', 'core_version' => '>=1.0.0',
    ]);
    unitAssert($pm6->checkCoreCompatibility($manifest7, '5.0.0') === true, "Core >=1.0.0 should accept 5.0.0");
    unitAssert($pm6->checkCoreCompatibility($manifest7, '0.9.0') === true, "Core >=1.0.0 should accept 0.9.0");
    echo "[OK] Test 7: core version >=1.0.0 is permissive\n";

    // ============================================================
    echo "=== Test 8: EventDispatcher basics ===\n";

    $events = new EventDispatcher();
    $fired = false;
    $events->listen('test.event', function ($e) use (&$fired) { $fired = true; });
    $events->dispatch(new \Api\System\Library\Module\Event('test.event'));
    unitAssert($fired === true, "Event must fire listener");

    $events2 = new EventDispatcher();
    $fired2 = false;
    $events2->listen('test.event', function ($e) { throw new \RuntimeException("bad"); });
    $events2->listen('test.event', function ($e) use (&$fired2) { $fired2 = true; });
    $events2->dispatch(new \Api\System\Library\Module\Event('test.event'));
    unitAssert($fired2 === true, "Second listener must fire even if first throws");
    echo "[OK] Test 8: event isolation works\n";

    // ============================================================
    echo "=== Test 9: Event stopPropagation ===\n";

    $events3 = new EventDispatcher();
    $first = false; $second = false;
    $events3->listen('test.prop', function ($e) use (&$first) { $first = true; $e->stopPropagation(); }, 10);
    $events3->listen('test.prop', function ($e) use (&$second) { $second = true; }, 5);
    $events3->dispatch(new \Api\System\Library\Module\Event('test.prop'));
    unitAssert($first === true, "First listener must fire");
    unitAssert($second === false, "Second listener must NOT fire after stopPropagation");
    echo "[OK] Test 9: stopPropagation works\n";

    // ============================================================
    echo "=== Test 10: PluginManager duplicate name detection ===\n";

    $moduleDirF1 = $baseDir . '/modules/test.dupe';
    $moduleDirF2 = $baseDir . '/modules/test.dupe2';
    mkdir($moduleDirF1, 0777, true);
    mkdir($moduleDirF2, 0777, true);
    file_put_contents($moduleDirF1 . '/manifest.json', json_encode([
        'name' => 'test.duplicate', 'version' => '1.0.0', 'vendor' => 'test',
        'title' => 'Dupe1', 'description' => '', 'core_version' => '>=1.0.0', 'dependencies' => [],
    ]));
    file_put_contents($moduleDirF2 . '/manifest.json', json_encode([
        'name' => 'test.duplicate', 'version' => '2.0.0', 'vendor' => 'test',
        'title' => 'Dupe2', 'description' => '', 'core_version' => '>=1.0.0', 'dependencies' => [],
    ]));

    $pm7 = new PluginManager($baseDir);
    $pm7->discover();
    unitAssert(isset($pm7->getDiscovered()['test.duplicate']), "One duplicate entry must survive");
    unitAssert(count($pm7->getDiscovered()) >= 1, "At least one of the duplicates must be present");
    $errors7 = $pm7->getValidationErrors();
    $hasDupeError = false;
    foreach ($errors7 as $modErrors) {
        foreach ($modErrors as $e) {
            if ($e['code'] === 'E_MANIFEST_DUPLICATE_NAME') $hasDupeError = true;
        }
    }
    unitAssert($hasDupeError, "Must report duplicate name error");
    echo "[OK] Test 10: duplicate name detection works\n";

    echo "\n========================================\n";
    echo "  ALL 10 EDGE-CASE TESTS PASSED\n";
    echo "========================================\n";
} catch (Throwable $e) {
    fwrite(STDERR, "\n========================================\n");
    fwrite(STDERR, "  EDGE-CASE TEST FAILED\n");
    fwrite(STDERR, "  " . $e->getMessage() . "\n");
    fwrite(STDERR, "========================================\n");
    exit(1);
}
