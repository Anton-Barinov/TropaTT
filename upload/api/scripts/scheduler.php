<?php

declare(strict_types=1);

use Api\System\Library\Support\AppLog;
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
$argv ??= $_SERVER['argv'] ?? [];
$argc ??= count($argv);

use Api\System\Library\Config;
use Api\System\Library\Container;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Module\ModuleAutoloader;
use Api\System\Library\Module\ModuleCronScheduler;
use Api\System\Library\Module\PluginManager;

require_once __DIR__ . '/../system/library/support/Autoloader.php';

$basePath = dirname(__DIR__);
$projectRoot = dirname($basePath);
$autoloader = new Api\System\Library\Support\Autoloader($basePath);
$autoloader->register();

// Module cron handlers live in Module\Vendor\Name\... and are resolved by the
// module autoloader that the web application registers in app.php. This CLI
// worker runs standalone, so without the same registration every module task
// failed with "Handler class not found" — and on shared hosting the scheduler
// is always invoked from cron, never through the web application. Dashed module
// directories (crm.confluence-migration) additionally need registerModule(),
// because the fallback path only matches undashed names.
$moduleAutoloader = new ModuleAutoloader($projectRoot);
$moduleAutoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $projectRoot . '/.env',
        $basePath . '/.env',
        $projectRoot . '/.env.local',
        $basePath . '/.env.local',
    ]);
}

$config = new Config();
$config->load($basePath . '/config/database.php', 'database');
        AppLog::bootFromConfig($config, $basePath . '/config/logging.php');
$connectionManager = new ConnectionManager($config);
$pdo = $connectionManager->connect();

$dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
$driver = (string)($dbConfig['driver'] ?? 'sqlite');

// Register the class paths of every discovered module so handler classes with
// dashed directories resolve from cron (the fallback path only matches undashed
// names). Whether a task may actually run is decided per task below: a task
// belonging to a deactivated module is skipped instead of failing every tick
// with "Handler class not found".
try {
    $pluginManager = new PluginManager($projectRoot);
    $pluginManager->discover();

    foreach ($pluginManager->getDiscovered() as $manifest) {
        $moduleAutoloader->registerModule($manifest->name, $manifest->vendor);
    }
} catch (\Throwable $e) {
    // A broken module or config must never stop cron from running core tasks.
    error_log('[scheduler] module autoloader registration failed: ' . $e->getMessage());
}

$scheduler = new ModuleCronScheduler($pdo);
$scheduler->ensureTables($driver);

$command = $argv[1] ?? 'run';
$args = array_slice($argv, 2);

function out(string $line): void { fwrite(STDOUT, $line . PHP_EOL); }
function err(string $line): void { fwrite(STDERR, $line . PHP_EOL); }

switch ($command) {
    case 'run':
        $result = $scheduler->run();
        out("Scheduler run complete: {$result['executed']} executed, {$result['failed']} failed");
        break;

    case 'list':
        $tasks = $scheduler->getTasks();
        if ($tasks === []) {
            out("No scheduled tasks.");
            break;
        }
        foreach ($tasks as $task) {
            $enabled = (int)($task['enabled'] ?? 1) ? 'enabled' : 'disabled';
            out("{$task['module_name']}.{$task['task_name']}  {$task['schedule']}  {$enabled}  next: {$task['next_run_at']}");
        }
        break;

    case 'run-task':
        $taskKey = $args[0] ?? '';
        if ($taskKey === '') {
            err("Task name required: module_name.task_name");
            exit(1);
        }
        [$moduleName, $taskName] = array_pad(explode('.', $taskKey, 2), 2, '');

        $tasks = $scheduler->getTasks($moduleName);
        foreach ($tasks as $task) {
            if ($task['task_name'] === $taskName) {
                $taskResult = $scheduler->run();
                out("Task executed: status={$taskResult['executed']} executed");
                exit(0);
            }
        }
        err("Task not found: {$taskKey}");
        exit(2);

    case 'history':
        $taskKey = $args[0] ?? '';
        if ($taskKey === '') {
            err("Task name required: module_name.task_name");
            exit(1);
        }
        [$moduleName, $taskName] = array_pad(explode('.', $taskKey, 2), 2, '');
        $limit = (int)($args[1] ?? 50);

        $history = $scheduler->getExecutionHistory($moduleName, $taskName, $limit);
        if ($history === []) {
            out("No execution history.");
            break;
        }
        foreach ($history as $exec) {
            out("{$exec['started_at']}  {$exec['status']}  {$exec['duration_ms']}ms  " . ($exec['error_message'] ?? ''));
        }
        break;

    case 'cleanup':
        $days = (int)($args[0] ?? 30);
        $deleted = $scheduler->cleanupOldExecutions($days);
        out("Cleaned up {$deleted} old execution records.");
        break;

    default:
        err("Unknown command: {$command}");
        err("Available: run, list, run-task, history, cleanup");
        exit(1);
}
