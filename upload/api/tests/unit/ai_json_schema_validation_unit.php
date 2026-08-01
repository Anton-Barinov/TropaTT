<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/database/builder/QueryBuilder.php';
require_once __DIR__ . '/../../model/ai/AiJsonSchemaRepository.php';
require_once __DIR__ . '/../../system/library/logger/JsonLogger.php';
require_once __DIR__ . '/../../system/library/service/AiJsonSchemaService.php';

use Api\Model\Ai\AiJsonSchemaRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Service\AiJsonSchemaService;

function unitAssertSchema(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('
        CREATE TABLE ai_json_schemas (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            public_id TEXT NOT NULL,
            intent_code TEXT NOT NULL,
            schema_version TEXT NOT NULL,
            schema_json TEXT NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_by_user_id INTEGER NULL,
            updated_by_user_id INTEGER NULL,
            created_at TEXT NULL,
            updated_at TEXT NULL
        )
    ');

    $repo = new AiJsonSchemaRepository($pdo);
    $logger = new JsonLogger([]);
    $service = new AiJsonSchemaService($repo, $logger);

    $insert = $pdo->prepare('
        INSERT INTO ai_json_schemas
            (public_id, intent_code, schema_version, schema_json, is_active, created_at, updated_at)
        VALUES
            (:public_id, :intent_code, :schema_version, :schema_json, :is_active, :created_at, :updated_at)
    ');

    $now = gmdate('Y-m-d H:i:s');

    $validSchema = [
        'type' => 'object',
        'required' => ['summary', 'risks'],
        'properties' => [
            'summary' => ['type' => 'string'],
            'risks' => ['type' => 'array'],
            'score' => ['type' => 'number'],
        ],
    ];

    $insert->execute([
        'public_id' => 'aisc_unit_valid',
        'intent_code' => 'task_summary',
        'schema_version' => 'v1',
        'schema_json' => json_encode($validSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'is_active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $okPayload = $service->validatePayloadBySchema('task_summary', [
        'summary' => 'Ready',
        'risks' => ['none'],
        'score' => 0.42,
    ]);
    unitAssertSchema((bool)($okPayload['ok'] ?? false) === true, 'Valid payload must pass active schema validation');

    $missingRequired = $service->validatePayloadBySchema('task_summary', [
        'summary' => 'Missing risks',
    ]);
    unitAssertSchema((bool)($missingRequired['ok'] ?? true) === false, 'Missing required field must fail schema validation');
    unitAssertSchema((string)($missingRequired['code'] ?? '') === 'AI_SCHEMA_VALIDATION_FAILED', 'Missing required field must return AI_SCHEMA_VALIDATION_FAILED');

    $typeMismatch = $service->validatePayloadBySchema('task_summary', [
        'summary' => 'Wrong type',
        'risks' => 'should-be-array',
    ]);
    unitAssertSchema((bool)($typeMismatch['ok'] ?? true) === false, 'Type mismatch must fail schema validation');
    unitAssertSchema((string)($typeMismatch['code'] ?? '') === 'AI_SCHEMA_VALIDATION_FAILED', 'Type mismatch must return AI_SCHEMA_VALIDATION_FAILED');

    $insert->execute([
        'public_id' => 'aisc_unit_invalid',
        'intent_code' => 'task_quality',
        'schema_version' => 'v1',
        'schema_json' => json_encode([
            'type' => 'array',
            'properties' => ['summary' => ['type' => 'string']],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'is_active' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $invalidDefinition = $service->validatePayloadBySchema('task_quality', ['summary' => 'text']);
    unitAssertSchema((bool)($invalidDefinition['ok'] ?? true) === false, 'Invalid schema definition must fail validation');
    unitAssertSchema((string)($invalidDefinition['code'] ?? '') === 'AI_SCHEMA_VALIDATION_FAILED', 'Invalid schema definition must return AI_SCHEMA_VALIDATION_FAILED');

    $noSchema = $service->validatePayloadBySchema('unknown_intent', ['anything' => 'goes']);
    unitAssertSchema((bool)($noSchema['ok'] ?? false) === true, 'Intent without active schema must be allowed');

    echo "[OK] ai_json_schema_validation_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_json_schema_validation_unit: ' . $e->getMessage() . "\n");
    exit(1);
}

