<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findIntentOrFailActionDisabled(array $items, string $intentCode): array
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

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Disabled Action Type Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-disabled-action-type',
        'provider_payload' => [
            'mock_models' => ['mock-disabled-action-type'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'disabled-action-type-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentsResponse = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentsResponse['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentsResponse['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentOrFailActionDisabled($intentItems, 'task_summary');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 0),
    ];

    $bindIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-disabled-action-type',
        'feature_flag' => 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($bindIntent['status'] === 200, 'Intent bind patch must be 200');

    $actionTypesEnabled = request('GET', '/api/v1/ai/action-types', [], $rootHeaders);
    assertTrue($actionTypesEnabled['status'] === 200, 'Enabled action types list status must be 200');
    $enabledItems = (array)($actionTypesEnabled['payload']['data']['items'] ?? []);
    assertTrue(in_array('task_summary', $enabledItems, true), 'Enabled task_summary must be visible in action types list');

    $disableIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-disabled-action-type',
        'feature_flag' => 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 0,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($disableIntent['status'] === 200, 'Intent disable patch must be 200');

    $actionTypesDisabled = request('GET', '/api/v1/ai/action-types', [], $rootHeaders);
    assertTrue($actionTypesDisabled['status'] === 200, 'Disabled action types list status must be 200');
    $disabledItems = (array)($actionTypesDisabled['payload']['data']['items'] ?? []);
    assertTrue(!in_array('task_summary', $disabledItems, true), 'Disabled task_summary must not be visible in action types list');

    $disabledActionCall = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_disabled_action_type_' . randomSuffix(),
        'input_text' => 'Disabled action type must not execute',
    ], $rootHeaders);
    assertTrue($disabledActionCall['status'] === 409, 'Disabled action type execution must return 409');
    assertTrue((string)($disabledActionCall['payload']['code'] ?? '') === 'AI_INTENT_DISABLED', 'Disabled action type execution must return AI_INTENT_DISABLED');

    $restoreIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $intentSnapshot['provider_public_id'],
        'model' => $intentSnapshot['model'],
        'feature_flag' => $intentSnapshot['feature_flag'],
        'required_permission' => $intentSnapshot['required_permission'],
        'is_enabled' => $intentSnapshot['is_enabled'] ? 1 : 0,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($restoreIntent['status'] === 200, 'Intent restore patch must be 200');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_disabled_action_type_visibility_apply_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_disabled_action_type_visibility_apply_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
