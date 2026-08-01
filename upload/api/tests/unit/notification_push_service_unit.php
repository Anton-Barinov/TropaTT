<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/notification/PushSubscriptionRepository.php';
require_once __DIR__ . '/../../model/notification/PushDispatchQueueRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/Config.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/language/LanguageManager.php';
require_once __DIR__ . '/../../system/library/language/TranslatableTrait.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/NotificationPushService.php';

use Api\Model\Notification\PushSubscriptionRepository;
use Api\Model\Notification\PushDispatchQueueRepository;
use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\NotificationPushService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $repo = new PushSubscriptionRepository($pdo);
    $config = new Config();
    $config->merge('notifications', [
        'push' => [
            'gateway_url' => '',
            'timeout_sec' => 1,
            'max_subscriptions_per_dispatch' => 2,
        ],
    ]);
    $logger = new JsonLogger([]);
    $service = new NotificationPushService($repo, new PushDispatchQueueRepository($pdo), $logger, $config);

    $actor = ['id' => 77, 'public_id' => 'usr_push_unit'];

    $created = $service->upsert([
        'endpoint' => 'https://push.example.local/sub/unit-1',
        'p256dh' => 'unit-p256dh-1',
        'auth' => 'unit-auth-1',
        'device_label' => 'Unit Device',
        'user_agent' => 'crm-unit-test/1.0',
    ], $actor);
    unitAssert(is_array($created), 'Push upsert create must return item');
    $publicId = (string)($created['public_id'] ?? '');
    unitAssert($publicId !== '', 'Created push subscription must have public_id');
    unitAssert((string)($created['last_error'] ?? '') === '', 'New subscription must have empty last_error');

    $updated = $service->upsert([
        'endpoint' => 'https://push.example.local/sub/unit-1',
        'p256dh' => 'unit-p256dh-2',
        'auth' => 'unit-auth-2',
        'device_label' => 'Unit Device Updated',
        'user_agent' => 'crm-unit-test/2.0',
    ], $actor);
    unitAssert(is_array($updated), 'Push upsert update must return item');
    unitAssert((string)($updated['public_id'] ?? '') === $publicId, 'Upsert by endpoint must reuse same subscription');

    $listed = $service->list(['page' => 1, 'limit' => 10], $actor);
    $items = (array)($listed['items'] ?? []);
    unitAssert(count($items) === 1, 'Push list must contain single item');

    $noGatewayDispatch = $service->sendTestToUser((int)$actor['id'], $actor);
    unitAssert(($noGatewayDispatch['gateway_configured'] ?? true) === false, 'Gateway must be reported as not configured');
    unitAssert((int)($noGatewayDispatch['attempted'] ?? -1) === 0, 'Without gateway no dispatch attempts are expected');

    $config->merge('notifications', [
        'push' => [
            'gateway_url' => 'http://127.0.0.1:9/push-gateway-unreachable',
            'timeout_sec' => 1,
            'max_subscriptions_per_dispatch' => 2,
        ],
    ]);
    $withGatewayDispatch = $service->sendTestToUser((int)$actor['id'], $actor);
    unitAssert(($withGatewayDispatch['gateway_configured'] ?? false) === true, 'Gateway must be reported as configured');
    unitAssert((int)($withGatewayDispatch['attempted'] ?? 0) === 1, 'With gateway configured service must attempt delivery');
    unitAssert((int)($withGatewayDispatch['delivered'] ?? 0) === 0, 'Unreachable gateway must produce zero delivered notifications');

    $service->upsert([
        'endpoint' => 'https://push.example.local/sub/unit-2',
        'p256dh' => 'unit-p256dh-3',
        'auth' => 'unit-auth-3',
        'device_label' => 'Unit Device 2',
        'user_agent' => 'crm-unit-test/3.0',
    ], $actor);
    $service->upsert([
        'endpoint' => 'https://push.example.local/sub/unit-3',
        'p256dh' => 'unit-p256dh-4',
        'auth' => 'unit-auth-4',
        'device_label' => 'Unit Device 3',
        'user_agent' => 'crm-unit-test/4.0',
    ], $actor);

    $limitedDispatch = $service->sendTestToUser((int)$actor['id'], $actor);
    unitAssert((int)($limitedDispatch['attempted'] ?? 0) === 2, 'Dispatch attempts must respect max_subscriptions_per_dispatch limit');

    $deleted = $service->delete($publicId, $actor);
    unitAssert($deleted === true, 'Push subscription delete must succeed');

    echo "[OK] notification_push_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] notification_push_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
