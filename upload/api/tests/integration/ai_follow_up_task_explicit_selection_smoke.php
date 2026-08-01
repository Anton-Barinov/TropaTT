<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Follow-up Selection Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-follow-up-selection',
        'provider_payload' => [
            'mock_models' => ['mock-follow-up-selection'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'follow-up-selection-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentsResponse = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentsResponse['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentsResponse['payload']['data']['items'] ?? []);
    $taskNextActionIntent = null;
    foreach ($intentItems as $item) {
        if ((string)($item['intent_code'] ?? '') === 'task_next_action') {
            $taskNextActionIntent = is_array($item) ? $item : null;
            break;
        }
    }
    assertTrue(is_array($taskNextActionIntent), 'task_next_action intent setting must exist');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskNextActionIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskNextActionIntent['model'] ?? ''),
        'feature_flag' => (string)($taskNextActionIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($taskNextActionIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskNextActionIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskNextActionIntent['max_tokens'] ?? 0),
    ];

    $intentPatch = request('PATCH', '/api/v1/ai/intent-settings/task_next_action', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-follow-up-selection',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($intentPatch['status'] === 200, 'Intent patch status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI follow-up selection task ' . randomSuffix(),
        'description' => 'Проверка explicit selection для create_follow_up_task',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $nextAction = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/next-action', [], $rootHeaders);
    assertTrue($nextAction['status'] === 201, 'AI next-action create status must be 201');
    $suggestionPublicId = (string)($nextAction['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');

    $preview = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/preview-apply', [], $rootHeaders);
    assertTrue($preview['status'] === 200, 'Suggestion preview status must be 200');
    $changes = (array)($preview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($changes) > 0, 'Task next-action preview must contain selectable actions');

    $followUpAction = null;
    foreach ($changes as $change) {
        if (!is_array($change)) {
            continue;
        }
        if ((string)($change['type'] ?? '') === 'create_follow_up_task') {
            $followUpAction = $change;
            break;
        }
    }

    assertTrue(is_array($followUpAction), 'Task next-action preview must expose create_follow_up_task action type');
    assertTrue((bool)($followUpAction['requires_explicit_selection'] ?? false) === true, 'Follow-up task action must require explicit selection');
    assertTrue((string)($followUpAction['risk_level'] ?? '') === 'high', 'Follow-up task action must be marked high risk');
    assertTrue((string)($followUpAction['field'] ?? '') === 'subtask.title', 'Follow-up task action must still target existing subtasks endpoint contract');

    $restoreIntent = request('PATCH', '/api/v1/ai/intent-settings/task_next_action', [
        'provider_public_id' => $intentSnapshot['provider_public_id'],
        'model' => $intentSnapshot['model'],
        'feature_flag' => $intentSnapshot['feature_flag'],
        'required_permission' => $intentSnapshot['required_permission'],
        'is_enabled' => $intentSnapshot['is_enabled'] ? 1 : 0,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($restoreIntent['status'] === 200, 'Intent restore patch must be 200');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_follow_up_task_explicit_selection_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_follow_up_task_explicit_selection_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
