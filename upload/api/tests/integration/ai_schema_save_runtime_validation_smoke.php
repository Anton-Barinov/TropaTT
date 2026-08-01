<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<int,mixed> $items @return array<string,mixed> */
function findIntentOrFail(array $items, string $intentCode): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') !== $intentCode) {
            continue;
        }
        return $item;
    }

    throw new RuntimeException('Intent not found: ' . $intentCode);
}

/** @var array<string,mixed> */
$restore = [
    'root_headers' => [],
    'provider_public_id' => '',
    'previous_active_schema_public_id' => '',
    'intent_snapshot' => null,
];

$failedMessage = '';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);
    $restore['root_headers'] = $rootHeaders;

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flags['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);
    $flagsByCode = [];
    foreach ($flagItems as $row) {
        if (!is_array($row)) {
            continue;
        }
        $flagsByCode[(string)($row['code'] ?? '')] = (string)($row['public_id'] ?? '');
    }
    foreach (['ai.enabled', 'ai.task'] as $requiredFlagCode) {
        $flagPublicId = trim((string)($flagsByCode[$requiredFlagCode] ?? ''));
        assertTrue($flagPublicId !== '', 'Missing required AI feature flag: ' . $requiredFlagCode);
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must return 200 for ' . $requiredFlagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Schema Validation Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-schema-v1',
        'provider_payload' => [
            'mock_models' => ['mock-schema-v1'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');
    $restore['provider_public_id'] = $providerPublicId;

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'schema-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentOrFail($intentItems, 'task_summary');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? 'ai.task'),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? 'ai.use'),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 1200),
    ];
    $restore['intent_snapshot'] = $intentSnapshot;

    $intentPatch = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-schema-v1',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens']),
    ], $rootHeaders);
    assertTrue($intentPatch['status'] === 200, 'Intent patch for task_summary must return 200');

    // 438: JSON schema must be validated on save.
    $invalidSchemaCreate = request('POST', '/api/v1/ai/json-schemas', [
        'intent_code' => 'task_summary',
        'schema_version' => 'v-invalid-' . randomSuffix(),
        'schema_json' => '{"type":"array"}',
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($invalidSchemaCreate['status'] === 422, 'Invalid JSON schema create must return 422');
    assertTrue((string)($invalidSchemaCreate['payload']['code'] ?? '') === 'AI_SCHEMA_VALIDATION_FAILED', 'Invalid JSON schema create code mismatch');

    $schemasBefore = request('GET', '/api/v1/ai/json-schemas', ['intent_code' => 'task_summary'], $rootHeaders);
    assertTrue($schemasBefore['status'] === 200, 'JSON schemas list before runtime check must return 200');
    $schemaItemsBefore = (array)($schemasBefore['payload']['data']['items'] ?? []);
    $previousActiveSchemaPublicId = '';
    foreach ($schemaItemsBefore as $schemaItem) {
        if (!is_array($schemaItem)) {
            continue;
        }
        if ((bool)($schemaItem['is_active'] ?? false)) {
            $previousActiveSchemaPublicId = (string)($schemaItem['public_id'] ?? '');
            break;
        }
    }

    $restore['previous_active_schema_public_id'] = $previousActiveSchemaPublicId;

    $failingSchemaCreate = request('POST', '/api/v1/ai/json-schemas', [
        'intent_code' => 'task_summary',
        'schema_version' => 'v-fail-' . randomSuffix(),
        'schema_json' => [
            'type' => 'object',
            'required' => ['__must_fail_required_key'],
            'properties' => [
                '__must_fail_required_key' => ['type' => 'string'],
            ],
        ],
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($failingSchemaCreate['status'] === 201, 'Failing runtime schema create must return 201');
    $failingSchemaPublicId = (string)($failingSchemaCreate['payload']['data']['schema']['public_id'] ?? '');
    assertTrue($failingSchemaPublicId !== '', 'Failing runtime schema public_id is required');

    // 439: LLM result must be schema-validated before suggestion save.
    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'Schema Runtime Guard Task ' . randomSuffix(),
        'description' => 'Task for schema runtime mismatch guard',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Generate summary for schema validation guard check',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 422, 'Task summary with failing schema must return 422');
    assertTrue((string)($taskSummary['payload']['code'] ?? '') === 'AI_SCHEMA_VALIDATION_FAILED', 'Task summary failing schema code mismatch');

    $taskSuggestions = request('GET', '/api/v1/ai/suggestions', [
        'intent_code' => 'task_summary',
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'limit' => 50,
    ], $rootHeaders);
    assertTrue($taskSuggestions['status'] === 200, 'Task suggestions list must return 200');
    $suggestionItems = (array)($taskSuggestions['payload']['data']['items'] ?? []);
    assertTrue($suggestionItems === [], 'Schema validation failure must prevent suggestion persistence');

    fwrite(STDOUT, "[OK] ai_schema_save_runtime_validation_smoke\n");
} catch (Throwable $e) {
    $failedMessage = $e->getMessage();
} finally {
    $rootHeaders = is_array($restore['root_headers']) ? (array)$restore['root_headers'] : [];
    if ($rootHeaders !== []) {
        $previousActiveSchemaPublicId = trim((string)($restore['previous_active_schema_public_id'] ?? ''));
        if ($previousActiveSchemaPublicId !== '') {
            request('PATCH', '/api/v1/ai/json-schemas/' . $previousActiveSchemaPublicId, [
                'is_active' => 1,
            ], $rootHeaders);
        }

        $intentSnapshot = is_array($restore['intent_snapshot']) ? (array)$restore['intent_snapshot'] : [];
        if ($intentSnapshot !== []) {
            request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
                'provider_public_id' => (string)($intentSnapshot['provider_public_id'] ?? ''),
                'model' => (string)($intentSnapshot['model'] ?? ''),
                'feature_flag' => (string)($intentSnapshot['feature_flag'] ?? 'ai.task'),
                'required_permission' => (string)($intentSnapshot['required_permission'] ?? 'ai.use'),
                'is_enabled' => (bool)($intentSnapshot['is_enabled'] ?? true) ? 1 : 0,
                'max_tokens' => max(1, (int)($intentSnapshot['max_tokens'] ?? 1200)),
            ], $rootHeaders);
        }

        $providerPublicId = trim((string)($restore['provider_public_id'] ?? ''));
        if ($providerPublicId !== '') {
            request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
        }
    }
}

if ($failedMessage !== '') {
    fwrite(STDERR, "[FAIL] ai_schema_save_runtime_validation_smoke: " . $failedMessage . "\n");
    exit(1);
}
