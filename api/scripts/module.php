<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Module\ModuleConfig;
use Api\System\Library\Module\ModuleMigrationRunner;
use Api\System\Library\Module\PluginManager;
use Api\System\Library\Module\ServiceProviderRegistry;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$basePath = dirname(__DIR__);
$projectRoot = dirname($basePath);
$autoloader = new Api\System\Library\Support\Autoloader($basePath);
$autoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $projectRoot . '/.env',
        $basePath . '/.env',
        $projectRoot . '/.env.local',
        $basePath . '/.env.local',
    ]);
}

$config = new Config($basePath . '/config');
$config->load($basePath . '/config/database.php', 'database');
$connectionManager = new ConnectionManager($config);
$pdo = $connectionManager->connect();

$dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
$driver = (string)($dbConfig['driver'] ?? 'sqlite');

$container = new Container();
$hookManager = new HookManager();
$container->set('hook.manager', $hookManager);

$pluginManager = new PluginManager($projectRoot);
$container->set('plugin.manager', $pluginManager);

$moduleAutoloader = new ModuleAutoloader($projectRoot);
$moduleAutoloader->register();
$container->set('module.autoloader', $moduleAutoloader);

$moduleConfig = new ModuleConfig($pdo);
$moduleConfig->ensureTable($driver);
$container->set('module.config', $moduleConfig);

$moduleMigrations = new ModuleMigrationRunner($pdo);
$moduleMigrations->ensureTable($driver);
$container->set('module.migrations', $moduleMigrations);

$pluginManager->discover();

$commands = ['list', 'discover', 'install', 'activate', 'deactivate', 'remove', 'info', 'check', 'make', 'config', 'graph', 'verify', 'sign', 'package', 'backup', 'export', 'import', 'publish', 'sync'];

if ($argc < 2) {
    fwrite(STDERR, "Usage: php api/scripts/module.php <command> [options]\n\n");
    fwrite(STDERR, "Commands: " . implode(', ', $commands) . "\n");
    exit(1);
}

$command = $argv[1];
$args = array_slice($argv, 2);

if (!in_array($command, $commands, true)) {
    fwrite(STDERR, "Unknown command: {$command}\n");
    fwrite(STDERR, "Available commands: " . implode(', ', $commands) . "\n");
    exit(1);
}

$isJson = false;
foreach ($args as $arg) {
    if ($arg === '--format=json') {
        $isJson = true;
    }
}

$fn = 'cmd_' . $command;
if (function_exists($fn)) {
    $fn($pluginManager, $moduleConfig, $moduleMigrations, $hookManager, $pdo, $isJson, $args, $projectRoot);
} else {
    fwrite(STDERR, "Command not implemented: {$command}\n");
    exit(1);
}

function out(string $line): void
{
    fwrite(STDOUT, $line . PHP_EOL);
}

function err(string $line): void
{
    fwrite(STDERR, $line . PHP_EOL);
}

function cmd_list(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $discovered = $pm->getDiscovered();

    if ($json) {
        $items = [];
        foreach ($discovered as $name => $manifest) {
            $registry = $mc->getRegistry($name);
            $items[] = [
                'name' => $name,
                'version' => $manifest->version,
                'title' => $manifest->title,
                'vendor' => $manifest->vendor,
                'is_loaded' => $pm->isLoaded($name),
                'is_active' => isset($registry['is_active']) ? (bool)$registry['is_active'] : false,
                'status' => $registry ? ((bool)$registry['is_active'] ? 'active' : 'installed') : 'not_installed',
            ];
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        return;
    }

    if ($discovered === []) {
        out("No modules found.");
        return;
    }

    foreach ($discovered as $name => $manifest) {
        $registry = $mc->getRegistry($name);
        $status = $registry ? ((bool)$registry['is_active'] ? 'active     ' : 'installed  ') : 'discovered ';
        $version = $manifest->version;
        out("{$name}  v{$version}  {$status}");
    }
}

function cmd_discover(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $modules = $pm->discover();

    if ($json) {
        $items = [];
        foreach ($modules as $name => $manifest) {
            $items[$name] = [
                'name' => $name,
                'version' => $manifest->version,
                'vendor' => $manifest->vendor,
                'title' => $manifest->title,
                'description' => $manifest->description,
            ];
        }
        echo json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        return;
    }

    if ($modules === []) {
        out("No new modules discovered.");
        return;
    }

    foreach ($modules as $name => $manifest) {
        out("\xE2\x9C\x93 Discovered: {$name} v{$manifest->version}");
    }
    out(count($modules) . ' module(s) found.');
}

function cmd_install(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $errors = $pm->validate($manifest);
    if ($errors !== []) {
        foreach ($errors as $e) {
            err("\xE2\x9C\x97 {$e['message']}");
        }
        exit(3);
    }

    $registry = $mc->getRegistry($moduleName);
    if ($registry !== null) {
        err("\xE2\x9C\x97 Module is already installed: {$moduleName}");
        exit(1);
    }

    $mc->register($moduleName, $manifest->vendor, $manifest->version);

    if ($manifest->migrations !== null) {
        $migrationDir = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->migrations;
        $result = $mm->migrate($moduleName, $migrationDir);

        foreach ($result['applied'] as $m) {
            out("\xE2\x9C\x93 Migration applied: {$m}");
        }
        foreach ($result['errors'] as $e) {
            err("\xE2\x9C\x97 Migration error: {$e}");
        }
    }

    $mc->initFromManifest($moduleName, $manifest);

    out("\xE2\x9C\x93 Module {$moduleName} installed successfully (v{$manifest->version})");
}

function cmd_activate(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $registry = $mc->getRegistry($moduleName);
    if ($registry === null) {
        err("\xE2\x9C\x97 Module is not installed: {$moduleName}");
        exit(1);
    }

    if ((bool)($registry['is_active'] ?? false)) {
        err("\xE2\x9C\x97 Module is already active: {$moduleName}");
        exit(1);
    }

    if (!$pm->isLoaded($moduleName)) {
        $manifest = $pm->getManifest($moduleName);
        if ($manifest === null) {
            err("\xE2\x9C\x97 Module manifest not found: {$moduleName}");
            exit(2);
        }
        $pm->load($moduleName);
    }

    $mc->setActive($moduleName);
    out("\xE2\x9C\x93 Module {$moduleName} activated successfully");
}

function cmd_deactivate(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $registry = $mc->getRegistry($moduleName);
    if ($registry === null) {
        err("\xE2\x9C\x97 Module is not installed: {$moduleName}");
        exit(1);
    }

    $mc->setInactive($moduleName);
    out("\xE2\x9C\x93 Module {$moduleName} deactivated successfully");
}

function cmd_remove(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $registry = $mc->getRegistry($moduleName);
    if ($registry === null) {
        err("\xE2\x9C\x97 Module is not installed: {$moduleName}");
        exit(1);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest !== null && $manifest->migrations !== null) {
        $migrationDir = $pm->getModulesDir() . '/' . $manifest->name . '/' . $manifest->migrations;
        $result = $mm->rollbackAll($moduleName, $migrationDir);

        foreach ($result['rolled_back'] as $m) {
            out("\xE2\x9C\x93 Rolled back: {$m}");
        }
        foreach ($result['errors'] as $e) {
            err("\xE2\x9C\x97 Rollback error: {$e}");
        }
    }

    $mc->unregister($moduleName);
    out("\xE2\x9C\x93 Module {$moduleName} removed successfully");
}

function cmd_info(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $registry = $mc->getRegistry($moduleName);

    if ($json) {
        $info = [
            'name' => $manifest->name,
            'version' => $manifest->version,
            'vendor' => $manifest->vendor,
            'title' => $manifest->title,
            'description' => $manifest->description,
            'core_version' => $manifest->coreVersion,
            'dependencies' => $manifest->dependencies,
            'require_permissions' => $manifest->requirePermissions,
            'api_routes' => $manifest->apiRoutes,
            'web_routes' => $manifest->webRoutes,
            'migrations' => $manifest->migrations,
            'service_provider' => $manifest->serviceProvider,
            'is_loaded' => $pm->isLoaded($moduleName),
            'is_active' => $registry ? (bool)($registry['is_active'] ?? false) : false,
            'installed_at' => $registry['installed_at'] ?? null,
            'activated_at' => $registry['activated_at'] ?? null,
        ];
        echo json_encode($info, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        return;
    }

    out("Name:        {$manifest->name}");
    out("Title:       {$manifest->title}");
    out("Vendor:      {$manifest->vendor}");
    out("Version:     {$manifest->version}");
    out("Core:        {$manifest->coreVersion}");
    out("Description: {$manifest->description}");
    out("Provider:    " . ($manifest->serviceProvider ?? 'none'));
    out("API Routes:  " . ($manifest->apiRoutes ?? 'none'));
    out("Web Routes:  " . ($manifest->webRoutes ?? 'none'));
    out("Migrations:  " . ($manifest->migrations ?? 'none'));
    out("Deps:        " . (count($manifest->dependencies) > 0 ? count($manifest->dependencies) . ' modules' : 'none'));
    out("Permissions: " . (count($manifest->requirePermissions) > 0 ? implode(', ', $manifest->requirePermissions) : 'none'));
    out("Loaded:      " . ($pm->isLoaded($moduleName) ? 'yes' : 'no'));
    out("Active:      " . ($registry && (bool)($registry['is_active'] ?? false) ? 'yes' : 'no'));
    out("Installed:   " . ($registry['installed_at'] ?? 'not installed'));
}

function cmd_check(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $cycles = $pm->detectCycles();

    if ($json) {
        $result = ['cycles' => $cycles, 'hasErrors' => $cycles !== []];
        echo json_encode($result, JSON_UNESCAPED_UNICODE) . "\n";
        return;
    }

    if ($cycles !== []) {
        err("Circular dependencies detected:");
        foreach ($cycles as $cycle) {
            err("  " . implode(' → ', $cycle));
        }
    } else {
        out("\xE2\x9C\x93 No circular dependencies found.");
    }

    $allErrors = $pm->getValidationErrors();
    if ($allErrors !== []) {
        foreach ($allErrors as $moduleName => $errors) {
            err("Validation errors in {$moduleName}:");
            foreach ($errors as $e) {
                err("  {$e['code']}: {$e['message']}");
            }
        }
    }
}

function cmd_config(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $config = $mc->getAll($moduleName);

    if ($json) {
        echo json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        return;
    }

    if ($config === []) {
        out("No configuration set for {$moduleName}. Using defaults.");
    } else {
        foreach ($config as $key => $value) {
            $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : (string)$value;
            out("  {$key} = {$displayValue}");
        }
    }
}

function cmd_make(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required. Format: vendor.module-name");
        exit(2);
    }

    if (!preg_match('/^[a-z0-9]+\.[a-z0-9\-]+$/', $moduleName)) {
        err("Invalid module name. Use format: vendor.module-name (e.g. crm.example-hello)");
        exit(3);
    }

    $parts = explode('.', $moduleName, 2);
    $vendor = $parts[0];
    $name = $parts[1];
    $dir = $projectRoot . '/modules/' . $moduleName;

    if (is_dir($dir)) {
        err("\xE2\x9C\x97 Module directory already exists: {$dir}");
        exit(1);
    }

    $vendorStudly = ucfirst($vendor);
    $nameStudly = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

    $paths = [
        '',
        '/api',
        '/api/controller',
        '/api/config',
        '/api/language/en-gb/module',
        '/api/language/ru-ru/module',
        '/api/migrations',
        '/web',
        '/web/controller',
        '/web/template/page',
        '/web/assets/js',
        '/web/assets/css',
        '/web/config',
        '/web/language',
    ];

    foreach ($paths as $p) {
        $fullPath = $dir . $p;
        if (!mkdir($fullPath, 0755, true)) {
            err("\xE2\x9C\x97 Failed to create directory: {$fullPath}");
            exit(1);
        }
    }

    $manifest = json_encode([
        'name' => $moduleName,
        'version' => '1.0.0',
        'vendor' => $vendor,
        'title' => ucfirst($name) . ' Module',
        'description' => '',
        'core_version' => '>=1.0.0',
        'dependencies' => [],
        'require_permissions' => [],
        'api_routes' => 'api/config/routes.php',
        'web_routes' => 'web/config/routes.php',
        'migrations' => 'api/migrations/',
        'assets' => [
            'js' => ["web/assets/js/module-{$name}.js"],
            'css' => ["web/assets/css/module-{$name}.css"],
        ],
        'hooks' => [],
        'menu_items' => [],
        'service_provider' => null,
        'config_defaults' => [],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($dir . '/manifest.json', $manifest);

    file_put_contents($dir . '/api/config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");
    file_put_contents($dir . '/web/config/routes.php', "<?php\n\ndeclare(strict_types=1);\n\nreturn [];\n");

    file_put_contents($dir . '/api/controller/HelloController.php', "<?php\ndeclare(strict_types=1);\n\nnamespace Module\\{$vendorStudly}\\{$nameStudly}\Controller;\n\nfinal class HelloController\n{\n    public function index(): array\n    {\n        return ['message' => 'Hello from {$moduleName}!'];\n    }\n}\n");
    file_put_contents($dir . '/web/controller/HelloController.php', "<?php\ndeclare(strict_types=1);\n\nnamespace Module\\{$vendorStudly}\\{$nameStudly}\Controller;\n\nuse Web\System\Core\Controller;\n\nfinal class HelloController extends Controller\n{\n    public function index(): void\n    {\n        \$this->render(__DIR__ . '/../template/page/module_{$name}.php', [\n            'title' => '{$moduleName}',\n            'route' => 'module-{$name}',\n        ]);\n    }\n}\n");
    file_put_contents($dir . '/web/template/page/module_{$name}.php', "<h1>Hello from {$moduleName}!</h1>\n");
    file_put_contents($dir . '/web/assets/js/module-{$name}-page-bindings.js', "// Module {$moduleName} page bindings\n");
    file_put_contents($dir . '/web/assets/js/module-{$name}.js', "// Module {$moduleName} JS\n");
    file_put_contents($dir . '/web/assets/css/module-{$name}.css', "/* Module {$moduleName} CSS */\n");
    file_put_contents($dir . '/web/language/en-gb.php', "<?php\n\nreturn [];\n");
    file_put_contents($dir . '/web/language/ru-ru.php', "<?php\n\nreturn [];\n");
    file_put_contents($dir . '/api/language/en-gb/module/messages.php', "<?php\n\nreturn [];\n");
    file_put_contents($dir . '/api/language/ru-ru/module/messages.php', "<?php\n\nreturn [];\n");

    out("\xE2\x9C\x93 Module {$moduleName} created at modules/{$moduleName}/");
    out("\xE2\x9C\x93 manifest.json generated");
    out("\xE2\x9C\x93 API controller generated");
    out("\xE2\x9C\x93 Web controller generated");
}

function cmd_graph(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $discovered = $pm->getDiscovered();
    $order = $pm->resolveDependencyOrder();

    out("Module dependency graph:");
    out("");

    foreach ($order as $name) {
        $manifest = $discovered[$name] ?? null;
        if ($manifest === null) {
            continue;
        }

        $deps = $manifest->dependencies;
        if ($deps !== []) {
            $depNames = array_map(fn($d) => $d['name'] ?? '', $deps);
            out("  {$name} -> " . implode(', ', $depNames));
        } else {
            out("  {$name} (no dependencies)");
        }
    }

    out("");
    out("Load order: " . implode(' → ', array_slice($order, 0, 10)) . (count($order) > 10 ? ' ...' : ''));
}

function cmd_verify(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        $pm->discover();
        $discovered = $pm->getDiscovered();

        if ($discovered === []) {
            out("No modules found.");
            return;
        }

        foreach ($discovered as $name => $manifest) {
            $errors = $pm->validate($manifest);
            if ($errors === []) {
                out("\xE2\x9C\x93 {$name}: valid");
            } else {
                err("\xE2\x9C\x97 {$name}: validation errors");
                foreach ($errors as $e) {
                    err("    {$e['code']}: {$e['message']}");
                }
            }
        }
        return;
    }


    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("Module not found: {$moduleName}");
        exit(2);
    }

    $errors = $pm->validate($manifest);
    if ($errors !== []) {
        err("Validation failed for {$moduleName}:");
        foreach ($errors as $e) {
            err("  {$e['code']}: {$e['message']}");
        }
    } else {
        out("{$moduleName}: valid");
    }

    $cycles = $pm->detectCycles();
    if ($cycles !== []) {
        err("Circular dependencies detected.");
    }
}

function cmd_sign(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $moduleDir = $projectRoot . '/modules/' . $manifest->name;
    $manifestPath = $moduleDir . '/manifest.json';
    $manifestContent = file_get_contents($manifestPath);

    if ($manifestContent === false) {
        err("\xE2\x9C\x97 Manifest not readable.");
        exit(1);
    }

    $hash = hash('sha256', $manifestContent);
    out("Signature (SHA256): {$hash}");
}

function cmd_package(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $installer = new \Api\System\Library\Module\ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);

    try {
        $path = $installer->package($moduleName);
        out("\xE2\x9C\x93 Package created: {$path}");
    } catch (\Throwable $e) {
        err("\xE2\x9C\x97 Failed: " . $e->getMessage());
        exit(1);
    }
}

function cmd_backup(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    $storageBase = (string)($projectRoot . '/storage_api');

    if ($moduleName === '') {
        $backups = (new \Api\System\Library\Module\ModuleBackupManager($pdo, $storageBase))->listBackups();
        if ($backups === []) {
            out("No backups found.");
            return;
        }
        foreach ($backups as $b) {
            out("{$b['name']}  " . number_format($b['size']) . " bytes  " . date('Y-m-d H:i', $b['created']));
        }
        return;
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $moduleDir = $projectRoot . '/modules/' . $manifest->name;
    $bm = new \Api\System\Library\Module\ModuleBackupManager($pdo, $storageBase);

    try {
        $path = $bm->backupModule($moduleName, $moduleDir);
        out("\xE2\x9C\x93 Backup created: {$path}");
    } catch (\Throwable $e) {
        err("\xE2\x9C\x97 Backup failed: " . $e->getMessage());
        exit(1);
    }
}

function cmd_export(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    try {
        $exporter = new \Api\System\Library\Module\ModuleDataExporter($pdo);
        $data = $exporter->export($moduleName, 'json');
        echo $data;
    } catch (\Throwable $e) {
        err("\xE2\x9C\x97 Export failed: " . $e->getMessage());
        exit(1);
    }
}

function cmd_import(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $inputFile = $args[1] ?? '';
    if ($inputFile === '') {
        err("Input file is required.");
        exit(1);
    }

    if (!is_file($inputFile)) {
        err("\xE2\x9C\x97 File not found: {$inputFile}");
        exit(1);
    }

    $jsonData = file_get_contents($inputFile);
    if ($jsonData === false) {
        err("\xE2\x9C\x97 Cannot read file: {$inputFile}");
        exit(1);
    }

    try {
        $exporter = new \Api\System\Library\Module\ModuleDataExporter($pdo);
        $result = $exporter->import($moduleName, $jsonData);
        out("\xE2\x9C\x93 Imported {$result['imported']} records");

        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $e) {
                err("  Error: {$e}");
            }
        }
    } catch (\Throwable $e) {
        err("\xE2\x9C\x97 Import failed: " . $e->getMessage());
        exit(1);
    }
}

function cmd_publish(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    if ($moduleName === '') {
        err("Module name is required.");
        exit(2);
    }

    $manifest = $pm->getManifest($moduleName);
    if ($manifest === null) {
        err("\xE2\x9C\x97 Module not found: {$moduleName}");
        exit(2);
    }

    $installer = new \Api\System\Library\Module\ModuleRemoteInstaller($pm, $mc, $mm, $projectRoot);
    $packagePath = $installer->package($moduleName);

    $signature = hash_file('sha256', $packagePath);
    out("\xE2\x9C\x93 Package prepared: {$packagePath}");
    out("  SHA256: {$signature}");
    out("  Ready for upload to module repository.");
}

function cmd_sync(PluginManager $pm, ModuleConfig $mc, ModuleMigrationRunner $mm, HookManager $hm, PDO $pdo, bool $json, array $args, string $projectRoot): void
{
    $moduleName = $args[0] ?? '';
    $pm->discover();

    if ($moduleName !== '') {
        $manifest = $pm->getManifest($moduleName);
        if ($manifest === null) {
            err("Module not found: {$moduleName}");
            exit(2);
        }
        if ($manifest->serviceProvider === null) {
            out("Module {$moduleName} has no service provider. Nothing to sync.");
            return;
        }
        $modules = [$moduleName => $manifest];
    } else {
        $modules = $pm->getDiscovered();
    }

    $synced = 0;
    foreach ($modules as $name => $manifest) {
        if ($manifest->serviceProvider === null) continue;
        $spClass = $manifest->serviceProvider;
        if (!class_exists($spClass)) {
            $spFile = $projectRoot . '/modules/' . $manifest->name . '/api/' . str_replace('\\', '/', substr($spClass, strrpos($spClass, '\\') + 1)) . '.php';
            if (is_file($spFile)) require_once $spFile;
            if (!class_exists($spClass)) { err("{$name}: SP class not found: {$spClass}"); continue; }
        }
        try {
            $provider = new $spClass();
            if (!($provider instanceof \Api\System\Library\Module\ModuleServiceProviderInterface)) { err("{$name}: Not a provider"); continue; }
            foreach ($provider->getPermissions() as $code) {
                try {
                    $pdo->prepare("INSERT OR IGNORE INTO permissions (public_id, code, title, created_at) VALUES (?, ?, ?, ?)")
                        ->execute(['prm_' . strtoupper(bin2hex(random_bytes(8))), $code, str_replace('.', ' ', $code), date('Y-m-d H:i:s')]);
                } catch (\Throwable) {}
            }
            $autoloader = new \Api\System\Library\Module\ModuleAutoloader($projectRoot);
            $autoloader->registerModule($manifest->name, $manifest->vendor);
            $autoloader->register();
            $synced++;
            out("{$name}: synced");
        } catch (\Throwable $e) { err("{$name}: " . $e->getMessage()); }
    }
    out("Synced {$synced} module(s).");
}
