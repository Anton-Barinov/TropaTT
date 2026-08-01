<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlagOrFail(array $items, string $code): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Feature flag not found: ' . $code);
}

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findIntentOrFail(array $items, string $intentCode): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === $intentCode) {
            return $item;
        }
    }

    throw new RuntimeException('Intent setting not found: ' . $intentCode);
}

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFirstCronIntentOrFail(array $items): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $featureFlag = trim((string)($item['feature_flag'] ?? ''));
        if ($featureFlag !== '' && str_starts_with($featureFlag, 'ai.cron.')) {
            return $item;
        }
    }

    throw new RuntimeException('Cron intent setting not found');
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $flagCodes = ['ai.enabled', 'ai.task'];
    $flagSnapshots = [];
    foreach ($flagCodes as $flagCode) {
        $flag = findFlagOrFail($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must be 200 for ' . $flagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Trace Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-trace-model',
        'provider_payload' => [
            'mock_models' => ['mock-trace-model'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'trace-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $providerUpdate = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'title' => 'AI Trace Provider Updated ' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerUpdate['status'] === 200, 'Provider update status must be 200');

    $promptsList = request('GET', '/api/v1/ai/prompt-templates?intent_code=task_summary', [], $rootHeaders);
    assertTrue($promptsList['status'] === 200, 'Prompt templates list status must be 200');
    $promptItems = (array)($promptsList['payload']['data']['items'] ?? []);
    assertTrue($promptItems !== [], 'Prompt template for task_summary must exist');
    $prompt = (array)$promptItems[0];
    $promptPublicId = (string)($prompt['public_id'] ?? '');
    assertTrue($promptPublicId !== '', 'Prompt public_id is required');
    $promptVersionOriginal = (int)($prompt['version'] ?? 1);

    $promptUpdate = request('PATCH', '/api/v1/ai/prompt-templates/' . $promptPublicId, [
        'version' => $promptVersionOriginal + 1,
    ], $rootHeaders);
    assertTrue($promptUpdate['status'] === 200, 'Prompt update status must be 200');

    $schemasList = request('GET', '/api/v1/ai/json-schemas?intent_code=task_summary', [], $rootHeaders);
    assertTrue($schemasList['status'] === 200, 'JSON schemas list status must be 200');
    $schemaItems = (array)($schemasList['payload']['data']['items'] ?? []);
    assertTrue($schemaItems !== [], 'JSON schema for task_summary must exist');
    $schema = (array)$schemaItems[0];
    $schemaPublicId = (string)($schema['public_id'] ?? '');
    assertTrue($schemaPublicId !== '', 'Schema public_id is required');
    $schemaVersionOriginal = (string)($schema['schema_version'] ?? 'v1');

    $schemaUpdate = request('PATCH', '/api/v1/ai/json-schemas/' . $schemaPublicId, [
        'schema_version' => substr($schemaVersionOriginal . '-t', 0, 32),
    ], $rootHeaders);
    assertTrue($schemaUpdate['status'] === 200, 'Schema update status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentOrFail($intentItems, 'task_summary');
    $cronIntent = findFirstCronIntentOrFail($intentItems);

    $taskIntentSnapshot = [
        'public_id' => (string)($taskSummaryIntent['public_id'] ?? ''),
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 0),
    ];

    $patchTaskIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-trace-model',
        'feature_flag' => 'ai.task',
        'required_permission' => $taskIntentSnapshot['required_permission'],
        'is_enabled' => 1,
        'max_tokens' => max(1, $taskIntentSnapshot['max_tokens'] > 0 ? $taskIntentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($patchTaskIntent['status'] === 200, 'Task intent update status must be 200');

    $cronIntentCode = (string)($cronIntent['intent_code'] ?? '');
    assertTrue($cronIntentCode !== '', 'Cron intent_code is required');
    $cronIntentSnapshot = [
        'public_id' => (string)($cronIntent['public_id'] ?? ''),
        'provider_public_id' => trim((string)($cronIntent['provider_public_id'] ?? '')),
        'model' => (string)($cronIntent['model'] ?? ''),
        'feature_flag' => (string)($cronIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($cronIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($cronIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($cronIntent['max_tokens'] ?? 0),
    ];

    $cronPatch = request('PATCH', '/api/v1/ai/intent-settings/' . $cronIntentCode, [
        'provider_public_id' => $cronIntentSnapshot['provider_public_id'],
        'model' => $cronIntentSnapshot['model'],
        'feature_flag' => $cronIntentSnapshot['feature_flag'],
        'required_permission' => $cronIntentSnapshot['required_permission'],
        'is_enabled' => $cronIntentSnapshot['is_enabled'] ? 1 : 0,
        'max_tokens' => max(1, ($cronIntentSnapshot['max_tokens'] > 0 ? $cronIntentSnapshot['max_tokens'] : 1200) + 1),
    ], $rootHeaders);
    assertTrue($cronPatch['status'] === 200, 'Cron intent settings update status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Traceability Task ' . randomSuffix(),
        'description' => 'Config traceability check',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Traceability summary request',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary AI create must be 201');
    $jobPublicId = (string)($taskSummary['payload']['data']['job_public_id'] ?? '');
    assertTrue($jobPublicId !== '', 'Task summary job_public_id is required');

    $jobGet = request('GET', '/api/v1/ai/jobs/' . $jobPublicId, [], $rootHeaders);
    assertTrue($jobGet['status'] === 200, 'Job detail must be 200');
    $job = (array)($jobGet['payload']['data']['job'] ?? []);
    $payloadMeta = is_array($job['payload_meta'] ?? null) ? (array)$job['payload_meta'] : [];

    assertTrue((string)($payloadMeta['provider_public_id'] ?? '') === $providerPublicId, 'Job payload_meta must include provider_public_id from active config');
    assertTrue(trim((string)($payloadMeta['prompt_public_id'] ?? '')) !== '', 'Job payload_meta must include prompt_public_id');
    assertTrue((int)($payloadMeta['prompt_version'] ?? 0) > 0, 'Job payload_meta must include prompt_version');
    assertTrue(trim((string)($payloadMeta['intent_setting_public_id'] ?? '')) !== '', 'Job payload_meta must include intent_setting_public_id');
    assertTrue((string)($payloadMeta['model'] ?? '') === 'mock-trace-model', 'Job payload_meta must include resolved model');

    $auditList = request('GET', '/api/v1/ai/audit', ['limit' => 200], $rootHeaders);
    assertTrue($auditList['status'] === 200, 'AI audit list must be 200');
    $auditItems = (array)($auditList['payload']['data']['items'] ?? []);
    assertTrue($auditItems !== [], 'AI audit list must contain items');

    $hasProviderUpdated = false;
    $hasPromptUpdated = false;
    $hasSchemaUpdated = false;
    $hasIntentUpdatedTask = false;
    $hasIntentUpdatedCron = false;
    foreach ($auditItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $action = (string)($item['action'] ?? '');
        $entityPublicId = (string)($item['entity_public_id'] ?? '');
        if ($action === 'ai_provider_updated' && $entityPublicId === $providerPublicId) {
            $hasProviderUpdated = true;
        }
        if ($action === 'ai_prompt_template_updated' && $entityPublicId === $promptPublicId) {
            $hasPromptUpdated = true;
        }
        if ($action === 'ai_json_schema_updated' && $entityPublicId === $schemaPublicId) {
            $hasSchemaUpdated = true;
        }
        if ($action === 'ai_intent_settings_updated' && $entityPublicId === (string)$taskIntentSnapshot['public_id']) {
            $hasIntentUpdatedTask = true;
        }
        if ($action === 'ai_intent_settings_updated' && $entityPublicId === (string)$cronIntentSnapshot['public_id']) {
            $hasIntentUpdatedCron = true;
        }
    }

    assertTrue($hasProviderUpdated, 'Audit must contain ai_provider_updated for updated provider');
    assertTrue($hasPromptUpdated, 'Audit must contain ai_prompt_template_updated for updated prompt');
    assertTrue($hasSchemaUpdated, 'Audit must contain ai_json_schema_updated for updated schema');
    assertTrue($hasIntentUpdatedTask, 'Audit must contain ai_intent_settings_updated for task_summary intent settings');
    assertTrue($hasIntentUpdatedCron, 'Audit must contain ai_intent_settings_updated for cron intent settings');

    // Restore state.
    request('PATCH', '/api/v1/ai/prompt-templates/' . $promptPublicId, [
        'version' => max(1, $promptVersionOriginal),
    ], $rootHeaders);
    request('PATCH', '/api/v1/ai/json-schemas/' . $schemaPublicId, [
        'schema_version' => $schemaVersionOriginal,
    ], $rootHeaders);

    request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => (string)($taskIntentSnapshot['provider_public_id'] ?? ''),
        'model' => (string)($taskIntentSnapshot['model'] ?? ''),
        'feature_flag' => (string)($taskIntentSnapshot['feature_flag'] ?? ''),
        'required_permission' => (string)($taskIntentSnapshot['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskIntentSnapshot['is_enabled'] ?? true) ? 1 : 0,
        'max_tokens' => max(1, (int)($taskIntentSnapshot['max_tokens'] ?? 0) > 0 ? (int)$taskIntentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    request('PATCH', '/api/v1/ai/intent-settings/' . $cronIntentCode, [
        'provider_public_id' => (string)($cronIntentSnapshot['provider_public_id'] ?? ''),
        'model' => (string)($cronIntentSnapshot['model'] ?? ''),
        'feature_flag' => (string)($cronIntentSnapshot['feature_flag'] ?? ''),
        'required_permission' => (string)($cronIntentSnapshot['required_permission'] ?? ''),
        'is_enabled' => (bool)($cronIntentSnapshot['is_enabled'] ?? true) ? 1 : 0,
        'max_tokens' => max(1, (int)($cronIntentSnapshot['max_tokens'] ?? 0) > 0 ? (int)$cronIntentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);

    foreach ($flagCodes as $flagCode) {
        $snapshot = $flagSnapshots[$flagCode] ?? null;
        if (!is_array($snapshot)) {
            continue;
        }
        $flagPublicId = (string)($snapshot['public_id'] ?? '');
        if ($flagPublicId === '') {
            continue;
        }
        request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0], $rootHeaders);
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_config_traceability_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_config_traceability_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}

