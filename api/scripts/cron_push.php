<?php
declare(strict_types=1);

/**
 * CLI cron worker for push notifications.
 *
 * Usage:
 *   php api/scripts/cron_push.php              — process push queue (limit=10)
 *   php api/scripts/cron_push.php --limit=50   — process up to 50 items
 *   php api/scripts/cron_push.php --all        — process all pending items
 *
 * Add to crontab:
 *   * * * * * cd /path/to/project && php api/scripts/cron_push.php >> storage/logs/cron.log 2>&1
 */

$basePath = __DIR__ . '/../';
$projectRoot = dirname($basePath);

require_once $basePath . '/system/library/support/Autoloader.php';

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $projectRoot . '/.env',
        $basePath . '/.env',
        $projectRoot . '/.env.local',
        $basePath . '/.env.local',
    ]);
}

$autoloader = new Api\System\Library\Support\Autoloader($basePath);
$autoloader->register();

$config = new Api\System\Library\Config($basePath . '/config');
$config->load($basePath . '/config/database.php', 'database');
$config->load($basePath . '/config/notifications.php', 'notifications');

$connectionManager = new Api\System\Library\Database\ConnectionManager($config);
$pdo = $connectionManager->connect();

$dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
$driver = (string)($dbConfig['driver'] ?? 'sqlite');

$subscriptions = new Api\Model\Notification\PushSubscriptionRepository($pdo);
$queue = new Api\Model\Notification\PushDispatchQueueRepository($pdo);
$logger = new Api\System\Library\Logger\JsonLogger($basePath . '/logs');

$push = new Api\System\Library\Service\NotificationPushService($subscriptions, $queue, $logger, $config);

$limit = 10;
$all = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--all') {
        $all = true;
    } elseif (preg_match('/^--limit=(\d+)$/', $arg, $m)) {
        $limit = max(1, min(200, (int)$m[1]));
    }
}

$processed = 0;
if ($all) {
    while (true) {
        $result = $push->runQueued(50);
        $processed += $result['processed'];
        if ($result['processed'] === 0) {
            break;
        }
    }
} else {
    $result = $push->runQueued($limit);
    $processed = $result['processed'];
}

$timestamp = gmdate('Y-m-d H:i:s');
if ($processed === 0) {
    echo "[{$timestamp}] Push cron: no jobs\n";
} else {
    echo "[{$timestamp}] Push cron: {$result['completed']} completed, {$result['retried']} retried, {$result['dead_lettered']} dead-lettered, {$result['failed']} failed\n";
}
