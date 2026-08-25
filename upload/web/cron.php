<?php
declare(strict_types=1);

/**
 * Lightweight cron endpoint for shared hosting.
 *
 * Runs every piece of periodic work over plain HTTP GET, so installations on
 * shared hosting without CLI cron stay fully functional:
 *   - the web-push dispatch queue;
 *   - upcoming calendar reminders;
 *   - module scheduled tasks (knowledge, cycles, finance, push).
 *
 * Configure the hosting panel's cron — or an external scheduler such as
 * cron-job.org or EasyCron — to call it once a minute:
 *   https://your-site.com/web/cron.php?key=<CRON_SECRET_KEY>
 * The X-Cron-Key request header is accepted too and is preferred where the
 * scheduler supports headers, since it keeps the secret out of access logs.
 *
 * The secret key is auto-generated during installation and stored in .env.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

$projectRoot = dirname(__DIR__);
$apiRoot = $projectRoot . '/api';

require_once $apiRoot . '/system/library/support/Autoloader.php';
require_once $apiRoot . '/system/library/support/EnvLoader.php';

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
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'CRON_SECRET_KEY not configured'], JSON_UNESCAPED_SLASHES);
    exit(0);
}

// Rate limit: max 10 attempts per IP per 60 seconds to prevent key brute-force
$rateLimitFile = sys_get_temp_dir() . '/cron_rate_limit_' . hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.tmp';
$rateLimitWindow = 60;
$rateLimitMax = 10;

$now = time();
$attempts = [];
if (is_file($rateLimitFile)) {
    $raw = @file_get_contents($rateLimitFile);
    if ($raw !== false) {
        $attempts = json_decode($raw, true);
        if (!is_array($attempts)) {
            $attempts = [];
        }
    }
}
// Remove expired entries
$attempts = array_values(array_filter($attempts, static fn(int $ts): bool => ($now - $ts) < $rateLimitWindow));

if (count($attempts) >= $rateLimitMax) {
    http_response_code(429);
    error_log('[Cron] Rate limit exceeded for IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    echo json_encode(['ok' => false, 'error' => 'rate limit exceeded'], JSON_UNESCAPED_SLASHES);
    exit(0);
}

$providedKey = trim((string)($_GET['key'] ?? $_SERVER['HTTP_X_CRON_KEY'] ?? ''));
if (!hash_equals($secretKey, $providedKey)) {
    // Record failed attempt for rate limiting
    $attempts[] = $now;
    @file_put_contents($rateLimitFile, json_encode($attempts), LOCK_EX);

    error_log('[Cron] Invalid key attempt from IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'invalid key'], JSON_UNESCAPED_SLASHES);
    exit(0);
}

// Clear rate limit on success
if (is_file($rateLimitFile)) {
    @unlink($rateLimitFile);
}

$autoloader = new Api\System\Library\Support\Autoloader($apiRoot);
$autoloader->register();

$config = new Api\System\Library\Config();
$config->load($apiRoot . '/config/database.php', 'database');
$config->load($apiRoot . '/config/notifications.php', 'notifications');

$connectionManager = new Api\System\Library\Database\ConnectionManager($config);
$pdo = $connectionManager->connect();

$dbConfig = $config->get('database.connections.' . ($config->get('database.default') ?: 'sqlite'));
$driver = (string)($dbConfig['driver'] ?? 'sqlite');

$subscriptions = new Api\Model\Notification\PushSubscriptionRepository($pdo);
$queue = new Api\Model\Notification\PushDispatchQueueRepository($pdo);

$logger = new Api\System\Library\Logger\JsonLogger(['push' => $apiRoot . '/logs/cron_push.log']);

$push = new Api\System\Library\Service\NotificationPushService($subscriptions, $queue, $logger, $config);

$limit = max(1, min(50, (int)($_GET['limit'] ?? 10)));
$result = $push->runQueued($limit);

// Module scheduled tasks (knowledge freshness, cycle snapshots, finance period
// auto-close, push queue). Without this, hosting without CLI cron never runs
// them: api/scripts/scheduler.php refuses to run outside the CLI SAPI.
// Task rows are registered by App::initModuleSystem() on regular API requests.
$schedulerSummary = ['executed' => 0, 'failed' => 0];
try {
    $scheduler = new Api\System\Library\Module\ModuleCronScheduler($pdo);
    $scheduler->ensureTables($driver);
    $schedulerResult = $scheduler->run();
    $schedulerSummary = [
        'executed' => (int)($schedulerResult['executed'] ?? 0),
        'failed' => (int)($schedulerResult['failed'] ?? 0),
    ];
} catch (\Throwable $e) {
    error_log('[Cron] Module scheduler run failed: ' . $e->getMessage());
    $schedulerSummary['failed']++;
}

$userRepo = new Api\Model\Common\UserRepository($pdo);
$calendarRepo = new Api\Model\Calendar\CalendarEventRepository($pdo);
$notificationRepo = new Api\Model\Notification\NotificationRepository($pdo);
$lang = new Api\System\Library\Language\LanguageManager($apiRoot . '/language');

$notifications = new Api\System\Library\Service\NotificationService(
    $notificationRepo,
    $userRepo,
    $logger,
    null,
    $push,
    $lang,
    null,
    $calendarRepo
);

$activeUserIds = $userRepo->findActiveUserIds();
$upcomingCreated = 0;
foreach ($activeUserIds as $uid) {
    $upcomingCreated += $notifications->dispatchUpcomingCalendarReminders($uid, ['id' => $uid]);
}

// A-1: Record web cron heartbeat timestamp after successful auth.
// Stored as a system-scope setting so the admin dashboard can distinguish
// "cron never configured" from "cron configured but tasks are failing".
try {
    $heartbeatNow = gmdate('Y-m-d H:i:s');
    $heartbeatValue = json_encode(['ts' => $heartbeatNow, 'ts_utc' => time()], JSON_UNESCAPED_SLASHES);

    $heartbeatUpdate = $pdo->prepare("UPDATE settings SET value = :value, updated_at = :now WHERE scope = 'system' AND name = 'cron.last_web_run_at'");
    $heartbeatUpdate->execute(['value' => $heartbeatValue, 'now' => $heartbeatNow]);

    if ($heartbeatUpdate->rowCount() === 0) {
        $heartbeatInsert = $pdo->prepare("INSERT INTO settings (public_id, scope, name, value, created_at, updated_at) VALUES (:pid, 'system', 'cron.last_web_run_at', :value, :now, :now)");
        $heartbeatInsert->execute(['pid' => 'set_' . bin2hex(random_bytes(12)), 'value' => $heartbeatValue, 'now' => $heartbeatNow]);
    }
} catch (\Throwable $e) {
    error_log('[Cron] Failed to record heartbeat: ' . $e->getMessage());
}

$response = [
    'ok' => true,
    'processed' => $result['processed'],
    'completed' => $result['completed'],
    'retried' => $result['retried'],
    'dead_lettered' => $result['dead_lettered'],
    'failed' => $result['failed'],
    'errors' => $result['errors'],
    'scheduler' => $schedulerSummary,
    'upcoming_calendar_reminders' => $upcomingCreated,
    'generated_at' => gmdate('c'),
];

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
