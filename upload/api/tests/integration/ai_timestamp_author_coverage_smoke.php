<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';
set_time_limit(120);

use Api\Model\Ai\AiIntentSettingRepository;
use Api\Model\Ai\AiJsonSchemaRepository;
use Api\Model\Ai\AiPromptTemplateRepository;
use Api\Model\Ai\AiProviderRepository;
use Api\Model\Setting\SettingRepository;
use Api\System\Library\Config;
use Api\System\Library\Database\ConnectionManager;
use Api\System\Library\Database\Migration\MigrationManager;
use Api\System\Library\Database\SchemaManager;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiIntentSettingService;
use Api\System\Library\Service\AiJsonSchemaService;
use Api\System\Library\Service\AiPromptTemplateService;
use Api\System\Library\Service\SettingService;

/** @return array<string,bool> */
function tableColumns(PDO $pdo, string $table): array
{
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $map = [];

    if ($driver === 'sqlite') {
        $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() ?: [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = true;
            }
        }

        return $map;
    }

    if ($driver === 'sqlsrv') {
        $stmt = $pdo->prepare('SELECT name FROM sys.columns WHERE object_id = OBJECT_ID(:table_name)');
        if ($stmt !== false) {
            $stmt->execute(['table_name' => $table]);
            $rows = $stmt->fetchAll() ?: [];
            foreach ($rows as $row) {
                $name = trim((string)($row['name'] ?? ''));
                if ($name !== '') {
                    $map[$name] = true;
                }
            }
        }

        return $map;
    }

    $sql = $driver === 'pgsql'
        ? 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = :table_name'
        : 'SELECT column_name AS name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name';

    $stmt = $pdo->prepare($sql);
    if ($stmt !== false) {
        $stmt->execute(['table_name' => $table]);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name !== '') {
                $map[$name] = true;
            }
        }
    }

    return $map;
}

try {
    $config = new Config();
    foreach (['default', 'database', 'install', 'ai'] as $name) {
        $config->load(dirname(__DIR__, 2) . '/config/' . $name . '.php', $name);
    }
    $config->load(dirname(__DIR__, 2) . '/config/database.local.php', 'database');

    $connectionManager = new ConnectionManager($config);
    $pdo = $connectionManager->connect();
    $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    $migrations = new MigrationManager(new SchemaManager());
    $migrations->migrateUp($pdo, $driver);

    $expectedCoverage = [
        'ai_providers' => ['created_at', 'updated_at', 'created_by_user_id', 'updated_by_user_id'],
        'ai_provider_secrets' => ['created_at', 'updated_at', 'created_by_user_id', 'updated_by_user_id'],
        'ai_intent_settings' => ['created_at', 'updated_at', 'created_by_user_id', 'updated_by_user_id'],
        'ai_prompt_templates' => ['created_at', 'updated_at', 'created_by_user_id', 'updated_by_user_id'],
        'ai_json_schemas' => ['created_at', 'updated_at', 'created_by_user_id', 'updated_by_user_id'],
        'ai_suggestions' => ['created_at', 'updated_at', 'created_by_user_id', 'confirmed_by_user_id'],
        'ai_jobs' => ['created_at', 'updated_at', 'requested_by_user_id'],
        'ai_usage_logs' => ['created_at', 'user_id'],
    ];

    foreach ($expectedCoverage as $table => $requiredColumns) {
        $columns = tableColumns($pdo, $table);
        assertTrue($columns !== [], 'Expected AI table to exist: ' . $table);
        foreach ($requiredColumns as $requiredColumn) {
            assertTrue(isset($columns[$requiredColumn]), 'Missing required AI timestamp/author column: ' . $table . '.' . $requiredColumn);
        }
    }

    $logger = new JsonLogger(
        channels: [
            'audit' => dirname(__DIR__, 3) . '/storage/logs/audit.log',
            'security' => dirname(__DIR__, 3) . '/storage/logs/security.log',
            'request' => dirname(__DIR__, 3) . '/storage/logs/request.log',
            'error' => dirname(__DIR__, 3) . '/storage/logs/error.log',
        ],
        maskedKeys: ['password', 'token', 'secret', 'api_key', 'authorization']
    );

    $promptRepo = new AiPromptTemplateRepository($pdo);
    $schemaRepo = new AiJsonSchemaRepository($pdo);
    $settingsService = new SettingService(new SettingRepository($pdo));
    $intentRepo = new AiIntentSettingRepository($pdo);
    $providerRepo = new AiProviderRepository($pdo);

    $promptService = new AiPromptTemplateService($promptRepo, $logger);
    $schemaService = new AiJsonSchemaService($schemaRepo, $logger);
    $intentService = new AiIntentSettingService(
        repo: $intentRepo,
        schemas: $schemaRepo,
        prompts: $promptRepo,
        providers: $providerRepo,
        settings: $settingsService,
        logger: $logger,
        config: $config,
    );

    $actorCreate = ['id' => 777001, 'public_id' => 'usr_ai_cov_create'];
    $actorUpdate = ['id' => 777002, 'public_id' => 'usr_ai_cov_update'];

    $promptCreate = $promptService->create([
        'intent_code' => 'task_summary',
        'locale' => 'ru-ru',
        'version' => 1,
        'template_text' => 'Prompt coverage check ' . randomSuffix(),
        'is_active' => true,
    ], $actorCreate);
    assertTrue((bool)($promptCreate['ok'] ?? false), 'Prompt create must succeed');
    $promptPublicId = (string)($promptCreate['prompt']['public_id'] ?? '');
    assertTrue($promptPublicId !== '', 'Prompt public_id is required');

    $promptUpdate = $promptService->update($promptPublicId, [
        'template_text' => 'Prompt coverage update ' . randomSuffix(),
        'version' => 2,
    ], $actorUpdate);
    assertTrue((bool)($promptUpdate['ok'] ?? false), 'Prompt update must succeed');

    $promptRow = $promptRepo->findByPublicId($promptPublicId);
    assertTrue(is_array($promptRow), 'Prompt row must exist after update');
    assertTrue((int)($promptRow['created_by_user_id'] ?? 0) === 777001, 'Prompt created_by_user_id mismatch');
    assertTrue((int)($promptRow['updated_by_user_id'] ?? 0) === 777002, 'Prompt updated_by_user_id mismatch');
    assertTrue(trim((string)($promptRow['created_at'] ?? '')) !== '', 'Prompt created_at must be present');
    assertTrue(trim((string)($promptRow['updated_at'] ?? '')) !== '', 'Prompt updated_at must be present');

    $schemaCreate = $schemaService->create([
        'intent_code' => 'task_summary',
        'schema_version' => 'v1',
        'schema_json' => [
            'type' => 'object',
            'required' => ['task_public_id'],
            'properties' => [
                'task_public_id' => ['type' => 'string'],
            ],
        ],
        'is_active' => true,
    ], $actorCreate);
    assertTrue((bool)($schemaCreate['ok'] ?? false), 'Schema create must succeed');
    $schemaPublicId = (string)($schemaCreate['schema']['public_id'] ?? '');
    assertTrue($schemaPublicId !== '', 'Schema public_id is required');

    $schemaUpdate = $schemaService->update($schemaPublicId, [
        'schema_version' => 'v2',
    ], $actorUpdate);
    assertTrue((bool)($schemaUpdate['ok'] ?? false), 'Schema update must succeed');

    $schemaRow = $schemaRepo->findByPublicId($schemaPublicId);
    assertTrue(is_array($schemaRow), 'Schema row must exist after update');
    assertTrue((int)($schemaRow['created_by_user_id'] ?? 0) === 777001, 'Schema created_by_user_id mismatch');
    assertTrue((int)($schemaRow['updated_by_user_id'] ?? 0) === 777002, 'Schema updated_by_user_id mismatch');
    assertTrue(trim((string)($schemaRow['created_at'] ?? '')) !== '', 'Schema created_at must be present');
    assertTrue(trim((string)($schemaRow['updated_at'] ?? '')) !== '', 'Schema updated_at must be present');

    $intentService->list([]);
    $intentUpdate = $intentService->update('task_summary', [
        'model' => 'coverage-model-' . randomSuffix(),
    ], $actorUpdate);
    assertTrue((bool)($intentUpdate['ok'] ?? false), 'Intent settings update must succeed');

    $intentRow = $intentRepo->findByIntentCode('task_summary');
    assertTrue(is_array($intentRow), 'Intent settings row must exist');
    assertTrue((int)($intentRow['updated_by_user_id'] ?? 0) === 777002, 'Intent settings updated_by_user_id mismatch');
    assertTrue(trim((string)($intentRow['updated_at'] ?? '')) !== '', 'Intent settings updated_at must be present');

    echo "AI timestamp/author coverage smoke: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "AI timestamp/author coverage smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
