<?php
declare(strict_types=1);

require_once __DIR__ . '/../../model/webhook/WebhookRepository.php';
require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../system/library/Config.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/security/UrlSafetyValidator.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';
require_once __DIR__ . '/../../system/library/service/WebhookService.php';

use Api\Model\Webhook\WebhookRepository;
use Api\System\Library\Config;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\WebhookService;

function unitAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE webhook_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        title TEXT,
        endpoint TEXT,
        secret_hash TEXT,
        events TEXT,
        is_active INTEGER,
        created_at TEXT,
        updated_at TEXT
    )');
    $pdo->exec('CREATE TABLE webhook_deliveries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        public_id TEXT UNIQUE,
        webhook_id INTEGER,
        event_code TEXT,
        status TEXT,
        response_code INTEGER,
        created_at TEXT
    )');

    $repo = new WebhookRepository($pdo);
    $logger = new JsonLogger([]);
    $config = new Config();
    $config->merge('security', [
        'webhook' => [
            'retry_attempts' => 1,
            'retry_backoff_ms' => 0,
            'auto_disable_after_failures' => 2,
            'timeout_sec' => 1,
            'allowed_schemes' => ['https'],
            'block_private_networks_in_production' => true,
            'allow_insecure_local_dev_urls' => false,
        ],
    ]);
    $storageBase = sys_get_temp_dir() . '/crm_webhook_unit_' . bin2hex(random_bytes(4));
    $config->merge('default', [
        'app' => ['env' => 'prod'],
        'storage' => [
            'base' => $storageBase,
            'secrets' => $storageBase . '/secrets',
        ],
    ]);

    $service = new WebhookService($repo, $logger, $config);

    $nonRoot = ['id' => 2, 'public_id' => 'usr_non_root', 'is_root' => false];
    $root = ['id' => 1, 'public_id' => 'usr_root', 'is_root' => true];

    $forbiddenCreate = $service->createSubscription([
        'title' => 'Forbidden',
        'endpoint' => 'https://localhost/denied',
    ], $nonRoot);
    unitAssert(($forbiddenCreate['ok'] ?? true) === false, 'Non-root create must fail');
    unitAssert((string)($forbiddenCreate['code'] ?? '') === 'FORBIDDEN', 'Non-root create code mismatch');

    $blockedLocalhost = $service->createSubscription([
        'title' => 'Blocked localhost',
        'endpoint' => 'https://localhost/webhook-test',
    ], $root);
    unitAssert(($blockedLocalhost['ok'] ?? true) === false, 'Localhost endpoint must fail');
    unitAssert((string)($blockedLocalhost['code'] ?? '') === 'WEBHOOK_ENDPOINT_LOCALHOST_FORBIDDEN', 'Localhost endpoint code mismatch');

    $blockedPrivateIp = $service->createSubscription([
        'title' => 'Blocked private',
        'endpoint' => 'https://10.0.0.1/webhook-test',
    ], $root);
    unitAssert(($blockedPrivateIp['ok'] ?? true) === false, 'Private IP endpoint must fail');
    unitAssert((string)($blockedPrivateIp['code'] ?? '') === 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN', 'Private IP endpoint code mismatch');

    $blockedMetadata = $service->createSubscription([
        'title' => 'Blocked metadata',
        'endpoint' => 'http://169.254.169.254/latest/meta-data',
    ], $root);
    unitAssert(($blockedMetadata['ok'] ?? true) === false, 'Metadata endpoint must fail');
    unitAssert(
        in_array((string)($blockedMetadata['code'] ?? ''), ['WEBHOOK_ENDPOINT_SCHEME_NOT_ALLOWED', 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN'], true),
        'Metadata endpoint code mismatch'
    );

    $blockedHttp = $service->createSubscription([
        'title' => 'Blocked HTTP',
        'endpoint' => 'http://example.com/webhook-test',
    ], $root);
    unitAssert(($blockedHttp['ok'] ?? true) === false, 'HTTP endpoint must fail in production');
    unitAssert((string)($blockedHttp['code'] ?? '') === 'WEBHOOK_ENDPOINT_SCHEME_NOT_ALLOWED', 'HTTP endpoint code mismatch');

    $created = $service->createSubscription([
        'title' => 'Unit Webhook',
        'endpoint' => 'https://example.com/webhook-test',
        'secret' => 'unit-secret',
        'events' => ['task.created', ' task.created ', 'project.updated'],
        'is_active' => 1,
    ], $root);
    unitAssert(($created['ok'] ?? false) === true, 'Root create must succeed');
    $webhook = (array)($created['webhook'] ?? []);
    $publicId = (string)($webhook['public_id'] ?? '');
    unitAssert($publicId !== '', 'Created webhook must have public_id');
    unitAssert(($webhook['has_secret'] ?? false) === true, 'Created webhook must have secret');
    unitAssert(($webhook['events'] ?? []) === ['project.updated', 'task.created'], 'Webhook events must be normalized/unique/sorted');

    $missingUpdate = $service->updateSubscription('whs_missing', ['title' => 'X'], $root);
    unitAssert(($missingUpdate['ok'] ?? true) === false, 'Update missing webhook must fail');
    unitAssert((string)($missingUpdate['code'] ?? '') === 'WEBHOOK_NOT_FOUND', 'Update missing webhook code mismatch');

    $blockedUpdate = $service->updateSubscription($publicId, ['endpoint' => 'https://127.0.0.1/hook'], $root);
    unitAssert(($blockedUpdate['ok'] ?? true) === false, 'Private update endpoint must fail');
    unitAssert((string)($blockedUpdate['code'] ?? '') === 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN', 'Private update endpoint code mismatch');

    $repo->createSubscription([
        'public_id' => 'whs_legacy_private',
        'title' => 'Legacy private endpoint',
        'endpoint' => 'https://127.0.0.1/legacy-hook',
        'secret_hash' => '',
        'events' => '["task.created"]',
        'is_active' => 1,
        'created_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
    ]);
    $legacyDelivery = $service->testDelivery('whs_legacy_private', $root);
    unitAssert(($legacyDelivery['ok'] ?? false) === true, 'Legacy unsafe delivery must produce a delivery result');
    unitAssert((string)($legacyDelivery['delivery']['status'] ?? '') === 'error', 'Legacy unsafe delivery must be blocked as error');
    unitAssert((int)($legacyDelivery['delivery']['response_code'] ?? -1) === 0, 'Legacy unsafe delivery response code must be 0');

    $missingDelivery = $service->testDelivery('whs_missing', $root);
    unitAssert(($missingDelivery['ok'] ?? true) === false, 'Test delivery for missing webhook must fail');
    unitAssert((string)($missingDelivery['code'] ?? '') === 'WEBHOOK_NOT_FOUND', 'Missing webhook delivery code mismatch');

    $service->updateSubscription($publicId, ['is_active' => 0], $root);
    $inactiveDelivery = $service->testDelivery($publicId, $root);
    unitAssert(($inactiveDelivery['ok'] ?? true) === false, 'Inactive webhook delivery must fail');
    unitAssert((string)($inactiveDelivery['code'] ?? '') === 'WEBHOOK_INACTIVE', 'Inactive webhook delivery code mismatch');

    $forbiddenDelete = $service->deleteSubscription($publicId, $nonRoot);
    unitAssert(($forbiddenDelete['ok'] ?? true) === false, 'Non-root delete must fail');
    unitAssert((string)($forbiddenDelete['code'] ?? '') === 'FORBIDDEN', 'Non-root delete code mismatch');

    $deleted = $service->deleteSubscription($publicId, $root);
    unitAssert(($deleted['ok'] ?? false) === true, 'Root delete must succeed');

    echo "[OK] webhook_service_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] webhook_service_unit: ' . $e->getMessage() . "\n");
    exit(1);
}
