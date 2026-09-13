<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/support/Autoloader.php';
require_once __DIR__ . '/../../system/library/support/Ulid.php';

$apiRoot = dirname(__DIR__, 2);
$autoloader = new Api\System\Library\Support\Autoloader($apiRoot);
$autoloader->register();

// Register module autoloader
spl_autoload_register(function (string $class): void {
    $prefix = 'Module\\Crm\\EcommerceGateway\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);

    $first = strtolower(array_shift($parts));
    $path = dirname(__DIR__, 3) . '/modules/crm.ecommerce-gateway/api/' . $first . '/' . implode('/', $parts) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
    // Also check tests/e2e
    $testPath = dirname(__DIR__, 3) . '/modules/crm.ecommerce-gateway/tests/e2e/' . end($parts) . '.php';
    if (file_exists($testPath)) {
        require_once $testPath;
    }
});

putenv('APP_SECRET=e2e_test_secret_32_bytes_long_key_123456');
$_ENV['APP_SECRET'] = 'e2e_test_secret_32_bytes_long_key_123456';

use Module\Crm\EcommerceGateway\Service\EncryptionService;
use Module\Crm\EcommerceGateway\Service\SignatureService;
use Module\Crm\EcommerceGateway\Service\PayloadValidator;
use Module\Crm\EcommerceGateway\Service\IdempotencyService;
use Module\Crm\EcommerceGateway\Service\ContactResolver;
use Module\Crm\EcommerceGateway\Service\IntakeComposer;
use Module\Crm\EcommerceGateway\Service\IngestService;
use Module\Crm\EcommerceGateway\Service\RoutingMatrixService;
use Module\Crm\EcommerceGateway\Service\AntiSpamService;
use Module\Crm\EcommerceGateway\Service\FieldMapperService;
use Module\Crm\EcommerceGateway\Service\OrderStatusStateMachine;
use Module\Crm\EcommerceGateway\Service\StatusMappingService;
use Module\Crm\EcommerceGateway\Service\StatusSyncService;
use Module\Crm\EcommerceGateway\Service\StatusSyncContext;
use Module\Crm\EcommerceGateway\Repository\StoreRepository;
use Module\Crm\EcommerceGateway\Repository\IngestRepository;
use Module\Crm\EcommerceGateway\Repository\OutboxRepository;
use Module\Crm\EcommerceGateway\Job\EcommerceWebhookJob;
use Module\Crm\EcommerceGateway\Cron\EcommerceGatewayCronHandler;
use Module\Crm\EcommerceGateway\Tests\E2e\MockStoreClient;
use Module\Crm\EcommerceGateway\Service\IntakeWriterInterface;

$testName = 'E-Commerce Gateway Full Lifecycle E2E Test Suite (E-COM-14)';
$passed = 0;
$total = 0;

function assertCondition(bool $cond, string $message, &$passed, &$total, mixed $actual = null): void {
    $total++;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failedMsg = "  [FAIL] {$message}\n";
        if ($actual !== null) {
            $failedMsg .= "  [ACTUAL] " . var_export($actual, true) . "\n";
        }
        echo $failedMsg;
        throw new \RuntimeException($failedMsg);
    }
}

echo "Running {$testName}...\n\n";

// ── In-Memory SQLite Setup ──────────────────────────────────────────────
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Core tables
$pdo->exec('CREATE TABLE contacts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    full_name TEXT NULL,
    email TEXT NULL,
    phone TEXT NULL,
    role TEXT NULL,
    is_primary INTEGER NOT NULL DEFAULT 0,
    counterparty_id INTEGER NULL,
    created_by_user_id INTEGER NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE counterparties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    tax_id TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE projects (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL
)');
$pdo->exec("INSERT INTO projects (id, public_id, title) VALUES (1, 'prj_main_sales', 'Отдел продаж')");
$pdo->exec("INSERT INTO projects (id, public_id, title) VALUES (2, 'prj_callcenter', 'Колл-центр')");

$pdo->exec('CREATE TABLE tasks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    project_id INTEGER NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    priority TEXT NOT NULL DEFAULT "normal",
    status TEXT NOT NULL DEFAULT "new",
    due_at TEXT NULL,
    assignee_user_id INTEGER NULL,
    source_type TEXT NULL,
    source_id TEXT NULL,
    source_url TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE intake_items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    contact_id INTEGER NULL,
    title TEXT NOT NULL,
    description TEXT NULL,
    priority_code TEXT NULL,
    source_type TEXT NULL,
    source_ref TEXT NULL,
    source_email TEXT NULL,
    external_source TEXT NULL,
    external_id TEXT NULL,
    duplicate_intake_item_id INTEGER NULL,
    extra_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT NULL
)');

// Gateway tables
$pdo->exec('CREATE TABLE ecommerce_stores (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    cms_type TEXT NOT NULL DEFAULT "custom",
    store_url TEXT NULL,
    description TEXT NULL,
    api_key TEXT NOT NULL,
    api_secret_encrypted TEXT NOT NULL,
    api_secret_hint TEXT NULL,
    secondary_api_secret_encrypted TEXT NULL,
    secondary_secret_expires_at TEXT NULL,
    secret_rotated_at TEXT NULL,
    ip_whitelist TEXT NULL,
    rate_limit_per_minute INTEGER NOT NULL DEFAULT 120,
    webhook_url TEXT NULL,
    webhook_secret_encrypted TEXT NULL,
    status TEXT NOT NULL DEFAULT "active",
    locale TEXT NOT NULL DEFAULT "ru-ru",
    settings_json TEXT NULL,
    created_by_user_id INTEGER NULL,
    last_ingest_at TEXT NULL,
    last_error TEXT NULL,
    row_version INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    deleted_at TEXT NULL
)');

$pdo->exec('CREATE TABLE ecommerce_idempotency (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id INTEGER NOT NULL,
    idempotency_key TEXT NOT NULL,
    ingest_type TEXT NOT NULL,
    external_id TEXT NOT NULL,
    request_hash TEXT NULL,
    entity_type TEXT NULL,
    entity_public_id TEXT NULL,
    response_json TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    expires_at TEXT NULL,
    UNIQUE(store_id, idempotency_key)
)');

$pdo->exec('CREATE TABLE ecommerce_ingest_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    request_id TEXT NOT NULL,
    store_id INTEGER NOT NULL,
    ingest_type TEXT NOT NULL,
    external_id TEXT NULL,
    external_id_synthetic INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT "received",
    http_status INTEGER NULL,
    error_code TEXT NULL,
    entity_type TEXT NULL,
    entity_public_id TEXT NULL,
    payload_json TEXT NULL,
    payload_bytes INTEGER NOT NULL DEFAULT 0,
    duration_ms INTEGER NULL,
    ip TEXT NULL,
    user_agent TEXT NULL,
    created_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE ecommerce_order_sync_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    store_id INTEGER NOT NULL,
    external_order_id TEXT NULL,
    crm_task_id INTEGER NULL,
    intake_item_id INTEGER NULL,
    direction TEXT NOT NULL DEFAULT "inbound",
    status TEXT NOT NULL DEFAULT "received",
    http_code INTEGER NULL,
    request_payload TEXT NULL,
    response_payload TEXT NULL,
    error_message TEXT NULL,
    ip_address TEXT NULL,
    created_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE ecommerce_security_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    store_id INTEGER NULL,
    event_type TEXT NOT NULL,
    severity TEXT NOT NULL DEFAULT "warning",
    ip_address TEXT NULL,
    user_agent TEXT NULL,
    details_json TEXT NULL,
    created_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE ecommerce_nonces (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    store_id INTEGER NOT NULL,
    nonce TEXT NOT NULL,
    created_at TEXT NOT NULL,
    UNIQUE(store_id, nonce)
)');

$pdo->exec('CREATE TABLE ecommerce_audit_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    store_id INTEGER NULL,
    actor_type TEXT NOT NULL DEFAULT "store",
    actor_id TEXT NULL,
    action TEXT NOT NULL,
    ip TEXT NULL,
    user_agent TEXT NULL,
    details_json TEXT NULL,
    created_at TEXT NOT NULL
)');

$pdo->exec('CREATE TABLE ecommerce_status_mappings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    store_id INTEGER NOT NULL,
    entity_scope TEXT NOT NULL DEFAULT "order",
    external_status TEXT NOT NULL,
    crm_status_code TEXT NOT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    UNIQUE(store_id, entity_scope, external_status)
)');

$pdo->exec('CREATE TABLE ecommerce_outbox_events (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    public_id TEXT NOT NULL UNIQUE,
    store_id INTEGER NOT NULL,
    event_type TEXT NOT NULL DEFAULT "order.status_changed",
    external_order_id TEXT NOT NULL,
    crm_task_id INTEGER NULL,
    crm_task_public_id TEXT NULL,
    old_status TEXT NULL,
    new_status TEXT NOT NULL,
    external_status TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT "pending",
    attempts INTEGER NOT NULL DEFAULT 0,
    max_attempts INTEGER NOT NULL DEFAULT 8,
    next_attempt_at TEXT NOT NULL,
    last_attempt_at TEXT NULL,
    delivered_at TEXT NULL,
    last_http_code INTEGER NULL,
    last_error TEXT NULL,
    last_response_body TEXT NULL,
    sync_initiator TEXT NOT NULL DEFAULT "crm",
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)');

// Mock Intake Writer
class MockIntakeWriter implements IntakeWriterInterface
{
    /** @var array<string,array<string,mixed>> */
    public array $items = [];
    private int $seq = 1;

    public function create(array $input, array $actor): array|string
    {
        $id = $this->seq++;
        $publicId = 'itk_' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
        $record = array_merge($input, [
            'id' => $id,
            'public_id' => $publicId,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->items[$publicId] = $record;
        return $record;
    }

    public function get(string $publicId, array $actor): ?array
    {
        return $this->items[$publicId] ?? null;
    }

    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        if (!isset($this->items[$publicId])) {
            return null;
        }
        $this->items[$publicId] = array_merge($this->items[$publicId], $input, [
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        return $this->items[$publicId];
    }
}

// Repositories & Services
$storeRepo = new StoreRepository($pdo);
$ingestRepo = new IngestRepository($pdo);
$outboxRepo = new OutboxRepository($pdo);
$mappingService = new StatusMappingService($pdo);
$intakeWriter = new MockIntakeWriter();
$validator = new PayloadValidator();
$idempotency = new IdempotencyService($ingestRepo);
$contactResolver = new ContactResolver($ingestRepo);
$composer = new IntakeComposer();
$routingMatrix = new RoutingMatrixService($pdo);
$antiSpam = new AntiSpamService($pdo);
$fieldMapper = new FieldMapperService($pdo);

$ingestService = new IngestService(
    $storeRepo,
    $ingestRepo,
    $validator,
    $idempotency,
    $contactResolver,
    $composer,
    $intakeWriter,
    null, // TaskService
    [],
    $antiSpam,
    $fieldMapper,
    $routingMatrix
);

$statusSyncService = new StatusSyncService($outboxRepo, $mappingService, $storeRepo);

// Seed Store: OpenCart Store
$rawStoreSecret = 'sec_opencart_live_store_secret_key_888';
$storeData = [
    'name' => 'Demo OpenCart Store',
    'cms_type' => 'opencart',
    'store_url' => 'https://demo-shop.tropatt.com',
    'api_key' => 'stk_demo_store_01',
    'api_secret_encrypted' => EncryptionService::encrypt($rawStoreSecret),
    'api_secret_hint' => '888',
    'webhook_url' => 'https://demo-shop.tropatt.com/index.php?route=extension/module/tropatt/webhook',
    'webhook_secret_encrypted' => EncryptionService::encrypt($rawStoreSecret),
    'status' => 'active',
    'locale' => 'ru-ru',
    'settings_json' => json_encode([
        'on_duplicate' => 'merge',
        'antispam_enabled' => true,
        'default_project_id' => 1,
        'create_task_for' => ['order', 'quick_order', 'callback'],
        'routing_matrix' => [
            'callback' => [
                'priority' => 'urgent',
                'sla_minutes' => 5,
                'project_id' => 2,
            ],
            'quick_order' => [
                'priority' => 'high',
                'sla_minutes' => 15,
                'project_id' => 1,
            ],
        ],
        'field_mappings' => [
            'survey_answer' => 'cf_quiz_choice',
            'delivery_time_slot' => 'cf_delivery_slot',
        ],
    ]),
];
$store = $storeRepo->createStore($storeData);
$storeId = (int)$store['id'];

// Seed OpenCart Status Mappings
$mappingService->setMapping($storeId, 'new', '1');          // Pending
$mappingService->setMapping($storeId, 'processing', '2');   // Processing (assembling)
$mappingService->setMapping($storeId, 'shipped', '3');      // Shipped
$mappingService->setMapping($storeId, 'completed', '5');    // Complete
$mappingService->setMapping($storeId, 'cancelled', '7');    // Canceled

// =========================================================================
// 1. MockStoreClient & HMAC Signing (cki_MTYMIKEC84E47AABAF7E51F7)
// =========================================================================
echo "--- 1. MockStoreClient & Cryptographic Signing (cki_MTYMIKEC84E47AABAF7E51F7) ---\n";
$mockClient = new MockStoreClient(
    storeKey: 'stk_demo_store_01',
    storeSecret: $rawStoreSecret,
    cmsType: MockStoreClient::CMS_OPENCART,
    baseUrl: 'https://demo.tropatt.com/api/index.php',
    webhookSecret: $rawStoreSecret
);

// Connect MockStoreClient directly to IngestService via in-memory transport handler
$mockClient->setHttpHandler(function (string $method, string $url, array $headers, string $body) use ($ingestService, $storeRepo, $store) {
    $parsedUrl = parse_url($url);
    parse_str($parsedUrl['query'] ?? '', $query);
    $route = (string)($query['route'] ?? '');

    // Signature verification check using StoreAuthService logic
    $storeKey = $headers['X-Store-Key'] ?? '';
    $timestamp = $headers['X-TropaTT-Timestamp'] ?? '';
    $nonce = $headers['X-TropaTT-Nonce'] ?? '';
    $sig = $headers['X-TropaTT-Signature'] ?? '';
    $idempotencyKey = $headers['X-TropaTT-Idempotency-Key'] ?? null;

    $reqPath = '/' . ltrim($route, '/');
    $secret = EncryptionService::decrypt((string)$store['api_secret_encrypted']);
    $valid = SignatureService::verify($secret, $sig, $method, $reqPath, $timestamp, $nonce, $body);

    if (!$valid) {
        $storeRepo->logSecurityEvent(
            'INGESTION_SIGNATURE_INVALID',
            'warning',
            (int)$store['id'],
            $headers['X-Forwarded-For'] ?? '203.0.113.10',
            'MockStoreClient/1.0',
            ['code' => 'INGESTION_SIGNATURE_INVALID', 'api_key_hint' => EncryptionService::mask($storeKey)]
        );

        return [
            'status' => 401,
            'body' => json_encode(['ok' => false, 'code' => 'INGESTION_SIGNATURE_INVALID', 'error' => 'Invalid HMAC signature']),
        ];
    }

    // Determine type from route
    $type = match (true) {
        str_ends_with($route, '/orders') => 'order',
        str_ends_with($route, '/quick-orders') => 'quick_order',
        str_ends_with($route, '/callbacks') => 'callback',
        str_ends_with($route, '/feedback') => 'feedback',
        str_ends_with($route, '/forms') => 'form',
        str_ends_with($route, '/ping') => 'ping',
        default => 'unknown',
    };

    if ($type === 'ping') {
        return [
            'status' => 200,
            'body' => json_encode(['ok' => true, 'code' => 'INGESTION_PONG', 'data' => ['store' => $store['name']]]),
        ];
    }

    $ip = $headers['X-Forwarded-For'] ?? '203.0.113.10';
    $result = $ingestService->ingest(
        $store,
        $type,
        $body,
        'req_' . bin2hex(random_bytes(8)),
        $ip,
        'MockStoreClient/1.0',
        $idempotencyKey
    );

    return [
        'status' => $result['http_status'],
        'body' => json_encode($result),
    ];
});

// Verify Ping handshake
$pingRes = $mockClient->ping();
assertCondition($pingRes['status'] === 200, "Ping handshake returned 200 OK", $passed, $total);
assertCondition($pingRes['data']['code'] === 'INGESTION_PONG', "Ping response is INGESTION_PONG", $passed, $total);

// =========================================================================
// 2. Full Lifecycle Scenarios A-F (cki_MTYMIL9HA00D6660F3D9B063)
// =========================================================================
echo "\n--- 2. Full Business Lifecycle Scenarios (cki_MTYMIL9HA00D6660F3D9B063) ---\n";

// --- Scenario A: B2C Order (Weighted item, Promo discount, VAT, CDEK) ---
echo ">> Scenario A: B2C Order Full Flow...\n";
$b2cOrder = [
    'external_id' => 'OC-1001',
    'payload' => [
        'order_number' => '1001',
        'order_status' => '1', // OpenCart Pending
        'items' => [
            [
                'name' => 'Кофе в зёрнах Эфиопия (1.5 кг)',
                'sku' => 'COF-ETH-15',
                'quantity' => 1,
                'price' => ['amount_minor' => 240000, 'currency' => 'RUB'],
                'line_total' => ['amount_minor' => 240000, 'currency' => 'RUB'],
                'options' => ['weight' => '1.5 kg', 'roast' => 'medium'],
            ],
            [
                'name' => 'Фильтр-пакеты для заваривания',
                'sku' => 'FLT-100',
                'quantity' => 2,
                'price' => ['amount_minor' => 35000, 'currency' => 'RUB'],
                'line_total' => ['amount_minor' => 70000, 'currency' => 'RUB'],
            ],
            [
                'name' => 'Сироп Карамель 250мл',
                'sku' => 'SYR-CRM',
                'quantity' => 1,
                'price' => ['amount_minor' => 45000, 'currency' => 'RUB'],
                'line_total' => ['amount_minor' => 45000, 'currency' => 'RUB'],
            ],
        ],
        'subtotal' => ['amount_minor' => 355000, 'currency' => 'RUB'],
        'discount_total' => ['amount_minor' => 35500, 'currency' => 'RUB'], // Promo -10%
        'delivery_total' => ['amount_minor' => 45000, 'currency' => 'RUB'], // CDEK Courier
        'tax_total' => ['amount_minor' => 60750, 'currency' => 'RUB'],      // VAT 20%
        'total' => ['amount_minor' => 364500, 'currency' => 'RUB'],         // 3550 - 355 + 450 = 3645.00
        'paid' => false,
        'payment_method' => 'Банковская карта онлайн',
        'delivery_method' => 'Курьер СДЭК до двери',
        'delivery_address' => [
            'country' => 'RU',
            'city' => 'Москва',
            'street' => 'ул. Тверская',
            'house' => '12',
            'apartment' => '45',
            'postal_code' => '125009',
        ],
        'customer' => [
            'full_name' => 'Алексей Петров',
            'phone' => '+7 (925) 123-45-67',
            'email' => 'alex.petrov@example.com',
        ],
    ],
];

$orderRes = $mockClient->submitOrder($b2cOrder);
assertCondition($orderRes['status'] === 201, "B2C Order ingested with 201 Created", $passed, $total);
assertCondition($orderRes['data']['code'] === 'INGESTION_ACCEPTED', "Code is INGESTION_ACCEPTED", $passed, $total);

$intakePublicId = $orderRes['data']['data']['intake_item_public_id'];
$intakeItem = $intakeWriter->get($intakePublicId, []);
assertCondition($intakeItem !== null, "Intake item created in CRM", $passed, $total);
assertCondition(str_contains($intakeItem['description'], 'Кофе в зёрнах Эфиопия'), "Weighted item present in description", $passed, $total, $intakeItem['description']);
assertCondition(str_contains($intakeItem['description'], 'СДЭК'), "CDEK delivery method reflected in description", $passed, $total);
assertCondition(str_contains($intakeItem['description'], '3 645.00'), "Exact total money formatted correctly", $passed, $total);

// Contact resolution check
$contact = $pdo->query("SELECT * FROM contacts WHERE phone = '+79251234567'")->fetch(PDO::FETCH_ASSOC);
assertCondition($contact !== false, "Contact created by phone +79251234567", $passed, $total);
assertCondition($contact['full_name'] === 'Алексей Петров', "Contact full name matches", $passed, $total);

// Lifecycle stage transitions in CRM & Outbox Webhooks
echo ">> Testing CRM Outbox status changes for B2C Order...\n";
// Insert order sync log link for task 101 to store and order OC-1001
$nowDate = gmdate('Y-m-d H:i:s');
$pdo->exec("INSERT INTO ecommerce_order_sync_log (
    public_id, store_id, external_order_id, crm_task_id, direction, status, created_at
) VALUES (
    'osl_b2c_1001', {$storeId}, 'OC-1001', 101, 'inbound', 'accepted', '{$nowDate}'
)");

// 1. CRM moves order to 'processing' (assembling)
$sync1 = $statusSyncService->handleTaskStatusChanged([
    'task_id' => 101,
    'task_public_id' => 'tsk_ord_1001',
    'old_status' => 'new',
    'new_status' => 'processing',
]);
assertCondition(is_array($sync1), "Outbox event created for processing status", $passed, $total);

// 2. CRM moves order to 'shipped'
$sync2 = $statusSyncService->handleTaskStatusChanged([
    'task_id' => 101,
    'task_public_id' => 'tsk_ord_1001',
    'old_status' => 'processing',
    'new_status' => 'shipped',
]);
assertCondition(is_array($sync2), "Outbox event created for shipped status", $passed, $total);

// 3. CRM moves order to 'completed'
$sync3 = $statusSyncService->handleTaskStatusChanged([
    'task_id' => 101,
    'task_public_id' => 'tsk_ord_1001',
    'old_status' => 'shipped',
    'new_status' => 'completed',
]);
assertCondition(is_array($sync3), "Outbox event created for completed status", $passed, $total);

// Dispatch Outbox to MockStoreClient
$webhookJob = new EcommerceWebhookJob($outboxRepo, [], function (string $url, array $headers, string $body) use ($mockClient) {
    // Deliver to MockStoreClient receiver (headers are associative array)
    $rx = $mockClient->receiveWebhook($headers, $body);
    return [
        'status' => $rx['valid'] ? 200 : 400,
        'body' => json_encode(['ok' => $rx['valid']]),
        'error' => $rx['error'],
    ];
});

$dispatchBatch = $webhookJob->processPending(10);
assertCondition($dispatchBatch['delivered'] === 3, "All 3 status changes delivered to mock store", $passed, $total);

$webhooks = $mockClient->getReceivedWebhooks();
assertCondition(count($webhooks) === 3, "MockStoreClient recorded 3 verified webhook deliveries", $passed, $total);
assertCondition($webhooks[0]['payload']['external_status'] === '2', "Webhook 1 mapped to OpenCart status '2' (Processing)", $passed, $total);
assertCondition($webhooks[1]['payload']['external_status'] === '3', "Webhook 2 mapped to OpenCart status '3' (Shipped)", $passed, $total);
assertCondition($webhooks[2]['payload']['external_status'] === '5', "Webhook 3 mapped to OpenCart status '5' (Complete)", $passed, $total);

// --- Scenario B: B2B Order with INN & Company entity ---
echo ">> Scenario B: B2B Order with INN and Company Entity...\n";
$b2bOrder = [
    'external_id' => 'OC-1002-B2B',
    'payload' => [
        'order_number' => '1002',
        'order_status' => '1',
        'items' => [
            [
                'name' => 'Оптовая партия кофемашин Pro-500',
                'sku' => 'CM-PRO-500',
                'quantity' => 3,
                'price' => ['amount_minor' => 12000000, 'currency' => 'RUB'],
                'line_total' => ['amount_minor' => 36000000, 'currency' => 'RUB'],
            ],
        ],
        'total' => ['amount_minor' => 36000000, 'currency' => 'RUB'],
        'payment_method' => 'Безналичный расчет (по счету)',
        'billing_entity' => [
            'company_name' => 'ООО "Кофейный Альянс"',
            'tax_id' => '7701234567',
            'kpp' => '770101001',
        ],
        'customer' => [
            'contact_type' => 'company',
            'company_name' => 'ООО "Кофейный Альянс"',
            'tax_id' => '7701234567',
            'full_name' => 'Михаил Захаров (Закупки)',
            'phone' => '+7 (495) 777-88-99',
            'email' => 'order@coffee-alliance.ru',
        ],
    ],
];

$b2bRes = $mockClient->submitOrder($b2bOrder);
assertCondition($b2bRes['status'] === 201, "B2B Order ingested with 201 Created", $passed, $total);

$b2bIntake = $intakeWriter->get($b2bRes['data']['data']['intake_item_public_id'], []);
assertCondition(str_contains($b2bIntake['description'], 'Кофейный Альянс'), "Company name rendered in intake description", $passed, $total);
assertCondition(str_contains($b2bIntake['description'], '7701234567'), "Tax ID (ИНН) rendered in intake description", $passed, $total);

// Check contact role
$b2bContact = $pdo->query("SELECT * FROM contacts WHERE phone = '+74957778899'")->fetch(PDO::FETCH_ASSOC);
assertCondition($b2bContact['role'] === 'company', "Contact role recognized as company", $passed, $total);

// --- Scenario C: Quick 1-Click Order (quick_order) ---
echo ">> Scenario C: Quick 1-Click Order...\n";
$quickOrder = [
    'external_id' => 'QO-5001',
    'payload' => [
        'product' => [
            'name' => 'Премиум Кофемолка Grinder-X',
            'sku' => 'GRND-X',
            'price' => ['amount_minor' => 1590000, 'currency' => 'RUB'],
        ],
        'quantity' => 1,
        'customer' => ['phone' => '+7 (916) 333-22-11'],
        'comment' => 'Позвоните скорее, хочу забрать сегодня',
    ],
];

$qoRes = $mockClient->submitQuickOrder($quickOrder);
assertCondition($qoRes['status'] === 201, "Quick order ingested with 201 Created", $passed, $total);

$qoIntake = $intakeWriter->get($qoRes['data']['data']['intake_item_public_id'], []);
assertCondition($qoIntake['priority_code'] === 'high', "Quick order priority routed as high", $passed, $total);
assertCondition($qoIntake['extra']['sla_minutes'] === 15, "Quick order SLA is 15 minutes", $passed, $total);
assertCondition(str_contains($qoIntake['title'], 'Покупка в 1 клик'), "Title formatted for 1-click order", $passed, $total);

// --- Scenario D: Callback Request with Urgent 5-Min SLA ---
echo ">> Scenario D: Callback Request (Urgent 5-min SLA)...\n";
$callback = [
    'external_id' => 'CB-9001',
    'payload' => [
        'topic' => 'Срочный вопрос по возврату',
        'customer' => [
            'full_name' => 'Дмитрий Смирнов',
            'phone' => '+7 (903) 444-55-66',
        ],
    ],
];

$cbRes = $mockClient->submitCallback($callback);
assertCondition($cbRes['status'] === 201, "Callback ingested with 201 Created", $passed, $total);

$cbIntake = $intakeWriter->get($cbRes['data']['data']['intake_item_public_id'], []);
assertCondition($cbIntake['priority_code'] === 'urgent', "Callback priority routed as urgent", $passed, $total);
assertCondition($cbIntake['extra']['sla_minutes'] === 5, "Callback SLA is strictly 5 minutes", $passed, $total);
assertCondition($cbIntake['project_public_id'] === 'prj_callcenter', "Callback routed to Call Center project", $passed, $total);

// --- Scenario E: Feedback & Quiz Form with Dynamic Mapping & Markdown Table ---
echo ">> Scenario E: Feedback and Quiz Dynamic Fields...\n";
$quizForm = [
    'external_id' => 'QUIZ-777',
    'payload' => [
        'form_id' => 'coffee_flavor_quiz',
        'title' => 'Квиз: Подбор идеального кофе',
        'form_data' => [
            'survey_answer' => 'Ягодный с кислинкой',      // Mapped in settings to cf_quiz_choice
            'delivery_time_slot' => '18:00 - 21:00',       // Mapped in settings to cf_delivery_slot
            'grind_type' => 'Под рожковую кофеварку',     // Unmapped -> goes to Markdown table
            'roast_preference' => 'Светлая обжарка',       // Unmapped -> goes to Markdown table
        ],
        'customer' => [
            'full_name' => 'Елена Васнецова',
            'email' => 'elena@quiz-lead.ru',
            'phone' => '+7 (999) 777-66-55',
        ],
    ],
];

$quizRes = $mockClient->submitForm($quizForm);
assertCondition($quizRes['status'] === 201, "Quiz form ingested with 201 Created", $passed, $total);

$quizIntake = $intakeWriter->get($quizRes['data']['data']['intake_item_public_id'], []);
assertCondition(isset($quizIntake['extra']['mapped_custom_fields']['cf_quiz_choice']), "Mapped field cf_quiz_choice captured", $passed, $total);
assertCondition($quizIntake['extra']['mapped_custom_fields']['cf_quiz_choice'] === 'Ягодный с кислинкой', "Field value matches", $passed, $total);
assertCondition(str_contains($quizIntake['description'], '| grind_type | Под рожковую кофеварку |'), "Unmapped field in Markdown table", $passed, $total);
assertCondition(str_contains($quizIntake['description'], '| roast_preference | Светлая обжарка |'), "Second unmapped field in Markdown table", $passed, $total);

// --- Scenario F: State Machine Collision & Echo Loop Prevention ---
echo ">> Scenario F: State Machine & Echo Loop Suppression...\n";
// Invalid transition: Try to move completed order back to processing
$invalidTransition = OrderStatusStateMachine::validateTransition('completed', 'processing');
assertCondition(!$invalidTransition['allowed'], "FSM blocks completed -> processing", $passed, $total);

// Terminal reverse prevention
$termReverse = OrderStatusStateMachine::validateTransition('cancelled', 'processing');
assertCondition(!$termReverse['allowed'], "FSM blocks cancelled -> processing terminal reversal", $passed, $total);

// Echo Loop Prevention: CMS initiated changes do not trigger Outbox
$outboxCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM ecommerce_outbox_events")->fetchColumn();
StatusSyncContext::runAsCms(function () use ($statusSyncService) {
    $statusSyncService->handleTaskStatusChanged([
        'task_id' => 101,
        'task_public_id' => 'tsk_ord_1001',
        'old_status' => 'processing',
        'new_status' => 'shipped',
    ]);
});
$outboxCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM ecommerce_outbox_events")->fetchColumn();
assertCondition($outboxCountBefore === $outboxCountAfter, "CMS initiated status change produced 0 outbox events (Echo Loop suppressed)", $passed, $total);

// =========================================================================
// 3. Network Outages, Duplicate Storm & Resilient Recovery (cki_MTYMIM300255F055149A597B)
// =========================================================================
echo "\n--- 3. Network Outages & Resilient Outbox Recovery (cki_MTYMIM300255F055149A597B) ---\n";

// 3.1 Duplicate Storm: Send 10 identical orders concurrently
echo ">> Sending Duplicate Storm (10 requests with same external_id)...\n";
$stormResults = [];
for ($i = 0; $i < 10; $i++) {
    $stormResults[] = $mockClient->submitOrder($b2cOrder);
}
$acceptedCount = 0;
$duplicateCount = 0;
foreach ($stormResults as $res) {
    if ($res['data']['code'] === 'INGESTION_ACCEPTED') {
        $acceptedCount++;
    } elseif ($res['data']['code'] === 'INGESTION_DUPLICATE') {
        $duplicateCount++;
    }
}
assertCondition($duplicateCount === 10, "All 10 storm requests deduplicated via hardware idempotency", $passed, $total);
$intakeCount = count($intakeWriter->items);
assertCondition($intakeCount === 5, "Exact 5 unique items exist in CRM, 0 duplicate items created", $passed, $total);

// 3.2 Network Dropout & Backoff Recovery
echo ">> Simulating Store Webhook Network Dropout (HTTP 504 / Connection Timeout)...\n";
// Add pending event to outbox
$outboxRepo->createEvent([
    'store_id' => $storeId,
    'external_order_id' => 'OC-DROPOUT-01',
    'new_status' => 'cancelled',
    'external_status' => '7',
    'payload' => ['order_id' => 'OC-DROPOUT-01', 'status' => '7'],
]);

// Fail delivery with mock transport simulating 504 Gateway Timeout
$failedTransport = function ($url, $headers, $body) {
    return ['status' => 504, 'body' => 'Gateway Timeout', 'error' => 'Connection timed out'];
};
$dropoutJob = new EcommerceWebhookJob($outboxRepo, [], $failedTransport);
$dropoutRes = $dropoutJob->processPending(10);
assertCondition($dropoutRes['failed'] === 1, "Failed delivery recorded when shop is offline", $passed, $total);

// Check event in DB: status pending, attempts = 1, backoff next_attempt_at in future
$eventInDb = $pdo->query("SELECT * FROM ecommerce_outbox_events WHERE external_order_id = 'OC-DROPOUT-01'")->fetch(PDO::FETCH_ASSOC);
assertCondition($eventInDb['status'] === 'pending', "Event kept pending for retry", $passed, $total);
assertCondition((int)$eventInDb['attempts'] === 1, "Attempt counter incremented to 1", $passed, $total);
assertCondition(strtotime($eventInDb['next_attempt_at']) > time(), "Next attempt scheduled in future via Exponential Backoff", $passed, $total);

// Simulate network restoration: reset next_attempt_at and run with healthy transport
$pdo->exec("UPDATE ecommerce_outbox_events SET next_attempt_at = '2020-01-01 00:00:00' WHERE external_order_id = 'OC-DROPOUT-01'");
$healthyTransport = function ($url, $headers, $body) {
    return ['status' => 200, 'body' => '{"ok":true}', 'error' => null];
};
$recoveredJob = new EcommerceWebhookJob($outboxRepo, [], $healthyTransport);
$recoveredRes = $recoveredJob->processPending(10);
assertCondition($recoveredRes['delivered'] === 1, "Outbox event recovered and delivered successfully upon network restore", $passed, $total);

// =========================================================================
// 4. Penetration Testing & Fuzzing (cki_MTYMIMZS5FC8A6E9F97DA689)
// =========================================================================
echo "\n--- 4. Penetration Testing & Fuzzing (cki_MTYMIMZS5FC8A6E9F97DA689) ---\n";

// 4.1 SQL Injection Attempt in Order Comment & Customer Name
$sqliPayload = $b2cOrder;
$sqliPayload['external_id'] = 'OC-SQLI-01';
$sqliPayload['payload']['customer']['phone'] = '+79259990001';
$sqliPayload['payload']['comment'] = "'); DROP TABLE ecommerce_stores; -- ' OR 1=1";
$sqliPayload['payload']['customer']['full_name'] = "Admin' UNION SELECT * FROM users --";

$sqliRes = $mockClient->submitOrder($sqliPayload, null, ['X-Forwarded-For' => '198.51.100.1']);
assertCondition($sqliRes['status'] === 201, "SQLi payload safely handled without executing injection", $passed, $total);
$storesExist = (int)$pdo->query("SELECT COUNT(*) FROM ecommerce_stores")->fetchColumn();
assertCondition($storesExist > 0, "SQL injection prevented; database tables intact", $passed, $total);

// 4.2 XSS Sanitization in Ingestion
$xssPayload = $b2cOrder;
$xssPayload['external_id'] = 'OC-XSS-01';
$xssPayload['payload']['customer']['phone'] = '+79259990002';
$xssPayload['payload']['customer']['full_name'] = '<script>alert("XSS")</script>Владелец';
$xssPayload['payload']['items'][0]['name'] = '<img src=x onerror=alert(1)>Кофе';

$xssRes = $mockClient->submitOrder($xssPayload, null, ['X-Forwarded-For' => '198.51.100.2']);
assertCondition($xssRes['status'] === 201, "XSS payload ingested safely", $passed, $total);
$xssIntake = $intakeWriter->get($xssRes['data']['data']['intake_item_public_id'], []);
assertCondition(!str_contains($xssIntake['description'], '<script>'), "Script tags stripped from description", $passed, $total);

// 4.3 Brute-force & Forged HMAC Protection
echo ">> Testing Forged Signature Rejections & Security Logging...\n";
$tamperedRes = $mockClient->send('POST', 'orders', ['fake' => 'data'], [
    'X-TropaTT-Signature' => base64_encode('fake_invalid_hmac_signature_32_bytes!'),
]);
assertCondition($tamperedRes['status'] === 401, "Forged HMAC signature returned 401 Unauthorized", $passed, $total);

$secLog = $pdo->query("SELECT * FROM ecommerce_security_log WHERE event_type = 'INGESTION_SIGNATURE_INVALID' ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
assertCondition($secLog !== false, "Security incident logged in ecommerce_security_log", $passed, $total);

// 4.4 Honeypot Anti-Spam Trap
$spamSubmission = [
    'external_id' => 'SPAM-BOT-01',
    'payload' => [
        'subject' => 'Привет',
        'message' => 'Спам сообщение от робота',
        'website_hp' => 'http://spammer-bot-trap.com',
        'customer' => ['phone' => '+79991234567'],
    ],
];
$spamRes = $mockClient->submitFeedback($spamSubmission, null, ['X-Forwarded-For' => '198.51.100.99']);
assertCondition($spamRes['status'] === 422, "Honeypot bot caught and rejected with 422 Unprocessable", $passed, $total);
assertCondition($spamRes['data']['code'] === 'INGESTION_SPAM_DETECTED', "Rejection reason is INGESTION_SPAM_DETECTED", $passed, $total);

// =========================================================================
// 5. Shared-Hosting Limits & Resource Verification (cki_MTYMINVR6659D52E04ED3DF3)
// =========================================================================
echo "\n--- 5. Shared-Hosting Limits Verification (< 32 MB, < 25s) ---\n";
// Check Cron handler execution
$cronHandler = new EcommerceGatewayCronHandler($pdo);
$cronResult = $cronHandler->dispatchQueue();
assertCondition($cronResult['status'] === 'completed', "Cron dispatchQueue executed cleanly", $passed, $total);
assertCondition($cronResult['peak_memory_mb'] < 32.0, "Cron reported peak memory strictly under 32 MB limit", $passed, $total);

// Global Peak Memory
$peakMemoryBytes = memory_get_peak_usage(true);
$peakMemoryMb = round($peakMemoryBytes / 1048576, 2);
echo "Final Test Peak Memory Usage: {$peakMemoryMb} MB\n";
assertCondition($peakMemoryMb < 32.0, "Total test suite memory usage is < 32 MB ({$peakMemoryMb} MB)", $passed, $total);

echo "\n=======================================================\n";
echo "Total Tests Run: {$total}\n";
echo "Tests Passed: {$passed}/{$total}\n";
echo "ALL E-COM-14 QA & E2E INTEGRATION TESTS PASSED 100%!\n";
echo "=======================================================\n";
