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
        'title' => 'AI Logging Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-logging-default',
        'provider_payload' => [
            'mock_models' => ['mock-logging-default'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'logging-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentOrFail($intentItems, 'task_summary');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 0),
    ];

    $patchIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-logging-default',
        'feature_flag' => 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'],
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'Intent patch status must be 200 for task_summary');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Logging Task ' . randomSuffix(),
        'description' => 'Log coverage check',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Create summary for log checks',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary AI create must be 201');
    $suggestionPublicId = (string)($taskSummary['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Task summary suggestion public_id is required');

    $actionRun = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => $taskPublicId,
        'input_text' => 'Action logging check',
    ], $rootHeaders);
    assertTrue($actionRun['status'] === 200, 'AI action task_summary must return 200');

    $usageList = request('GET', '/api/v1/ai/usage', [
        'action_type' => 'task_summary',
        'limit' => 50,
    ], $rootHeaders);
    assertTrue($usageList['status'] === 200, 'AI usage list must be 200');
    $usageItems = (array)($usageList['payload']['data']['items'] ?? []);
    assertTrue($usageItems !== [], 'AI usage list must contain entries for task_summary');

    $hasSuggestionUsage = false;
    $hasActionUsage = false;
    foreach ($usageItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['action_type'] ?? '') !== 'task_summary') {
            continue;
        }
        $meta = is_array($item['request_meta'] ?? null) ? (array)$item['request_meta'] : [];
        if ((string)($meta['scope_public_id'] ?? '') !== $taskPublicId) {
            continue;
        }
        if ((string)($meta['mode'] ?? '') === 'stage2_safe_mock') {
            $hasSuggestionUsage = true;
        }
        if ((string)($meta['mode'] ?? '') === 'stage1_mock') {
            $hasActionUsage = true;
        }
    }
    assertTrue($hasSuggestionUsage, 'Usage logs must contain AI suggestion entry');
    assertTrue($hasActionUsage, 'Usage logs must contain AI action entry');

    $auditList = request('GET', '/api/v1/ai/audit', ['limit' => 100], $rootHeaders);
    assertTrue($auditList['status'] === 200, 'AI audit list must be 200');
    $auditItems = (array)($auditList['payload']['data']['items'] ?? []);
    assertTrue($auditItems !== [], 'AI audit list must contain entries');

    $hasSuggestionAudit = false;
    $hasActionAudit = false;
    foreach ($auditItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $action = (string)($item['action'] ?? '');
        $entityPublicId = (string)($item['entity_public_id'] ?? '');
        if ($action === 'ai_suggestion_created' && $entityPublicId === $suggestionPublicId) {
            $hasSuggestionAudit = true;
        }
        if ($action === 'ai_action_executed') {
            $hasActionAudit = true;
        }
    }
    assertTrue($hasSuggestionAudit, 'Audit logs must contain ai_suggestion_created entry for created suggestion');
    assertTrue($hasActionAudit, 'Audit logs must contain ai_action_executed entry');

    request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => (string)($intentSnapshot['provider_public_id'] ?? ''),
        'model' => (string)($intentSnapshot['model'] ?? ''),
        'feature_flag' => (string)($intentSnapshot['feature_flag'] ?? ''),
        'required_permission' => (string)($intentSnapshot['required_permission'] ?? ''),
        'is_enabled' => (bool)($intentSnapshot['is_enabled'] ?? true) ? 1 : 0,
        'max_tokens' => max(1, (int)($intentSnapshot['max_tokens'] ?? 0) > 0 ? (int)$intentSnapshot['max_tokens'] : 1200),
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

    fwrite(STDOUT, "[OK] ai_request_logging_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_request_logging_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
