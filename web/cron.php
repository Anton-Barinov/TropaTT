<?php
declare(strict_types=1);

/**
 * Lightweight cron endpoint for shared hosting.
 *
 * Processes push notification queue via HTTP GET.
 * Configure an external cron service (e.g. cron-job.org, EasyCron, setcronjob.com)
 * to call: https://your-site.com/cron.php?key=<CRON_SECRET_KEY>
 *
 * The secret key is auto-generated during installation and stored in .env.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$projectRoot = dirname(__DIR__);
$apiRoot = $projectRoot . '/api';

require_once $apiRoot . '/system/library/support/Autoloader.php';

if (class_exists(Api\System\Library\Support\EnvLoader::class)) {
    Api\System\Library\Support\EnvLoader::loadFiles([
        $projectRoot . '/.env',
        $apiRoot . '/.env',
        $projectRoot . '/.env.local',
        $apiRoot . '/.env.local',
    ]);
}

$secretKey = trim((string)(getenv('CRON_SECRET_KEY') ?: ''));
if ($secretKey === '') {
    echo json_encode(['ok' => false, 'error' => 'CRON_SECRET_KEY not configured'], JSON_UNESCAPED_SLASHES);
    exit(0);
}

$providedKey = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? ''));
if (!hash_equals($secretKey, $providedKey)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid key'], JSON_UNESCAPED_SLASHES);
    exit(0);
}

$autoloader = new Api\System\Library\Support\Autoloader($apiRoot);
$autoloader->register();

$config = new Api\System\Library\Config($apiRoot . '/config');
$config->load($apiRoot . '/config/database.php', 'database');
$config->load($apiRoot . '/config/notifications.php', 'notifications');

$connectionManager = new Api\System\Library\Database\ConnectionManager($config);
$pdo = $connectionManager->connect();

$dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
$driver = (string)($dbConfig['driver'] ?? 'sqlite');

$subscriptions = new Api\Model\Notification\PushSubscriptionRepository($pdo);
$queue = new Api\Model\Notification\PushDispatchQueueRepository($pdo);

$logger = new Api\System\Library\Logger\JsonLogger($apiRoot . '/logs');

$push = new Api\System\Library\Service\NotificationPushService($subscriptions, $queue, $logger, $config);

$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$result = $push->runQueued($limit);

echo json_encode([
    'ok' => true,
    'processed' => $result['processed'],
    'completed' => $result['completed'],
    'retried' => $result['retried'],
    'dead_lettered' => $result['dead_lettered'],
    'failed' => $result['failed'],
    'generated_at' => gmdate('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
