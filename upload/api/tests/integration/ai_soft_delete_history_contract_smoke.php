<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
set_time_limit(90);

use Api\Model\Ai\AiProviderRepository;
use Api\Model\Setting\SettingRepository;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;
use Api\System\Library\Database\SchemaManager;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiProviderClientFactory;
use Api\System\Library\Service\AiProviderService;
use Api\System\Library\Service\CustomHttpProviderClient;
use Api\System\Library\Service\MockAiProviderClient;
use Api\System\Library\Service\OpenAiCompatibleProviderClient;
use Api\System\Library\Service\SettingService;
use Api\System\Library\Support\Ulid;

try {
    $config = new Config();
    foreach (['default', 'database', 'install', 'ai'] as $name) {
        $config->load(dirname(__DIR__, 2) . '/config/' . $name . '.php', $name);
    }
    $config->load(dirname(__DIR__, 2) . '/config/database.local.php', 'database');
    $config->merge('default', ['app' => ['env' => 'dev']]);

    $connectionManager = new ConnectionManager($config);
    $pdo = $connectionManager->connect();
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    $migrations = new MigrationManager(new SchemaManager());
    $migrations->migrateUp($pdo, $driver);

    putenv('AI_ENCRYPTION_KEY=smoke-soft-delete-history-key-2026');

    $logger = new JsonLogger(
        channels: [
            'audit' => dirname(__DIR__, 3) . '/storage/logs/audit.log',
            'security' => dirname(__DIR__, 3) . '/storage/logs/security.log',
            'request' => dirname(__DIR__, 3) . '/storage/logs/request.log',
            'error' => dirname(__DIR__, 3) . '/storage/logs/error.log',
        ],
        maskedKeys: ['password', 'token', 'secret', 'api_key', 'authorization'],
        dbWriter: static function (string $channel, array $context) use ($pdo): void {
            if ($channel !== 'audit') {
                return;
            }

            $stmt = $pdo->prepare('INSERT INTO audit_logs (public_id, actor_public_id, entity_type, entity_public_id, action, details, created_at) VALUES (:public_id, :actor_public_id, :entity_type, :entity_public_id, :action, :details, :created_at)');
            if ($stmt === false) {
                return;
            }

            $details = json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!is_string($details)) {
                $details = '{}';
            }

            $stmt->execute([
                'public_id' => Ulid::generate('aud'),
                'actor_public_id' => (string)($context['actor_public_id'] ?? ''),
                'entity_type' => (string)($context['entity_type'] ?? ''),
                'entity_public_id' => (string)($context['entity_public_id'] ?? ''),
                'action' => (string)($context['action'] ?? ''),
                'details' => $details,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    );

    $requestMock = new \Api\System\Library\Http\Request(
        method: 'GET',
        uri: '/',
        path: '/',
        query: [],
        post: [],
        cookies: [],
        files: [],
        server: [],
        headers: [],
        rawBody: '',
        requestId: 'test-request-id',
        correlationId: 'test-correlation-id',
        locale: 'en-gb',
    );

    $providerService = new AiProviderService(
        providers: new AiProviderRepository($pdo),
        settings: new SettingService(new SettingRepository($pdo)),
        logger: $logger,
        config: $config,
        providerClientFactory: new AiProviderClientFactory(
            new OpenAiCompatibleProviderClient(),
            new MockAiProviderClient(),
            new CustomHttpProviderClient()
        ),
        request: $requestMock
    );

    $actor = ['id' => 1, 'public_id' => 'usr_soft_delete_history_smoke'];

    $create = $providerService->create([
        'provider_code' => 'mock',
        'title' => 'Soft Delete History Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-gpt-4.1-mini',
        'provider_payload' => ['mock_models' => ['mock-gpt-4.1-mini']],
        'is_active' => 1,
        'is_default' => 0,
    ], $actor);
    assertTrue((bool)($create['ok'] ?? false) === true, 'Provider create via service must succeed');

    $providerPublicId = (string)($create['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Created provider must have public_id');

    $delete = $providerService->delete($providerPublicId, $actor);
    assertTrue((bool)($delete['ok'] ?? false) === true, 'Provider soft-delete via service must succeed');

    $afterDeleteGet = $providerService->get($providerPublicId);
    assertTrue((bool)($afterDeleteGet['ok'] ?? false) === false, 'Deleted provider must not be readable via active get');
    assertTrue((string)($afterDeleteGet['code'] ?? '') === 'AI_PROVIDER_NOT_FOUND', 'Deleted provider get must return AI_PROVIDER_NOT_FOUND');

    $providerRowStmt = $pdo->prepare('SELECT deleted_at, is_active, is_default FROM ai_providers WHERE public_id = :public_id LIMIT 1');
    assertTrue($providerRowStmt !== false, 'Provider row query must prepare');
    $providerRowStmt->execute(['public_id' => $providerPublicId]);
    $providerRow = $providerRowStmt->fetch(PDO::FETCH_ASSOC);

    assertTrue(is_array($providerRow), 'Soft-deleted provider row must remain for history');
    assertTrue(trim((string)($providerRow['deleted_at'] ?? '')) !== '', 'Soft-deleted provider must have deleted_at');
    assertTrue((int)($providerRow['is_active'] ?? 1) === 0, 'Soft-deleted provider must be inactive');
    assertTrue((int)($providerRow['is_default'] ?? 1) === 0, 'Soft-deleted provider must not remain default');

    $auditStmt = $pdo->prepare('SELECT action FROM audit_logs WHERE entity_type = :entity_type AND entity_public_id = :entity_public_id ORDER BY created_at DESC');
    assertTrue($auditStmt !== false, 'Audit query must prepare');
    $auditStmt->execute([
        'entity_type' => 'ai_provider',
        'entity_public_id' => $providerPublicId,
    ]);
    $auditActions = $auditStmt->fetchAll(PDO::FETCH_COLUMN);
    $auditActions = is_array($auditActions) ? array_map(static fn($v): string => (string)$v, $auditActions) : [];

    assertTrue(in_array('ai_provider_created', $auditActions, true), 'Audit/history must keep ai_provider_created');
    assertTrue(in_array('ai_provider_deleted', $auditActions, true), 'Audit/history must include ai_provider_deleted');

    echo "AI soft-delete history contract smoke: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "AI soft-delete history contract smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
