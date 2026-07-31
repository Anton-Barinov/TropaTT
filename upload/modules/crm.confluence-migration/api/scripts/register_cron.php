<?php
declare(strict_types=1);

/**
 * Register Confluence Migration cron task in the database.
 *
 * Usage: php modules/crm.confluence-migration/api/scripts/register_cron.php
 *
 * This registers the "process_confluence_imports" task with the ModuleCronScheduler
 * so it can be picked up by: php api/scripts/scheduler.php run
 */

$basePath = dirname(__DIR__, 4);
require_once $basePath . '/api/system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader($basePath . '/api');
$autoloader->register();

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $basePath . '/.env',
        $basePath . '/.env.local',
        $basePath . '/api/.env',
        $basePath . '/api/.env.local',
    ]);
}

$config = new Api\System\Library\Config($basePath . '/api/config');
$config->load($basePath . '/api/config/database.php', 'database');
$connectionManager = new Api\System\Library\Database\ConnectionManager($config);
$pdo = $connectionManager->connect();

$scheduler = new Api\System\Library\Module\ModuleCronScheduler($pdo);

$dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
$driver = (string)($dbConfig['driver'] ?? 'sqlite');
$scheduler->ensureTables($driver);

use Api\System\Library\Module\ScheduledTask;
use Module\Crm\ConfluenceMigration\Cron\ConfluenceWorkerHandler;

$task = new ScheduledTask(
    name: 'process_confluence_imports',
    description: 'Process queued Confluence import jobs',
    schedule: '*/5 * * * *',
    handler: [ConfluenceWorkerHandler::class, 'run'],
    enabled: true,
    timeout: 300,
    overlapAllowed: false,
    notifyOnError: true,
);

$scheduler->registerTask('crm.confluence-migration', $task);
fwrite(STDOUT, "[OK] Scheduled task 'crm.confluence-migration.process_confluence_imports' registered.\n");
fwrite(STDOUT, "Run 'php api/scripts/scheduler.php list' to verify.\n");
