<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Apply preview allowlist provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-preview-allowlist',
        'provider_payload' => [
            'mock_models' => ['mock-preview-allowlist'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'preview-allowlist-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $targetIntents = ['task_summary', 'task_decomposition', 'task_checklist', 'task_comment_draft', 'task_next_action'];
    $intentSnapshots = [];
    $intentList = request('GET', '/api/v1/ai/intent-settings', [], $headers);
    assertTrue($intentList['status'] === 200, 'Intent settings list must return 200');
    $intentItems = (array)($intentList['payload']['data']['items'] ?? []);

    foreach ($targetIntents as $intentCode) {
        $found = null;
        foreach ($intentItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string)($item['intent_code'] ?? '') === $intentCode) {
                $found = $item;
                break;
            }
        }
        assertTrue(is_array($found), 'Intent setting not found: ' . $intentCode);
        $intentSnapshots[$intentCode] = [
            'provider_public_id' => trim((string)($found['provider_public_id'] ?? '')),
            'model' => (string)($found['model'] ?? ''),
            'feature_flag' => (string)($found['feature_flag'] ?? ''),
            'required_permission' => (string)($found['required_permission'] ?? ''),
            'is_enabled' => (bool)($found['is_enabled'] ?? true),
            'max_tokens' => (int)($found['max_tokens'] ?? 0),
        ];

        $patch = request('PATCH', '/api/v1/ai/intent-settings/' . rawurlencode($intentCode), [
            'provider_public_id' => $providerPublicId,
            'model' => 'mock-preview-allowlist',
            'feature_flag' => (string)($intentSnapshots[$intentCode]['feature_flag'] ?? '') !== '' ? (string)$intentSnapshots[$intentCode]['feature_flag'] : 'ai.task',
            'required_permission' => (string)($intentSnapshots[$intentCode]['required_permission'] ?? '') !== '' ? (string)$intentSnapshots[$intentCode]['required_permission'] : 'ai.use',
            'is_enabled' => 1,
            'max_tokens' => max(1, (int)($intentSnapshots[$intentCode]['max_tokens'] ?? 0) > 0 ? (int)$intentSnapshots[$intentCode]['max_tokens'] : 1200),
        ], $headers);
        assertTrue($patch['status'] === 200, 'Intent patch must return 200 for ' . $intentCode);
    }

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'Apply preview allowlist task ' . randomSuffix(),
        'description' => 'Проверка allowlisted action types в preview-apply.',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $generationCalls = [
        '/api/v1/ai/tasks/' . $taskPublicId . '/summary',
        '/api/v1/ai/tasks/' . $taskPublicId . '/decompose',
        '/api/v1/ai/tasks/' . $taskPublicId . '/checklist',
        '/api/v1/ai/tasks/' . $taskPublicId . '/comment-draft',
        '/api/v1/ai/tasks/' . $taskPublicId . '/next-action',
    ];

    $allowedActionTypes = [
        'update_task_description' => true,
        'create_comment_draft' => true,
        'create_subtask' => true,
        'create_checklist' => true,
        'create_checklist_item' => true,
        'create_follow_up_task' => true,
    ];

    foreach ($generationCalls as $uri) {
        $create = request('POST', $uri, [], $headers);
        assertTrue($create['status'] === 201, 'Suggestion create must return 201 for ' . $uri);
        $suggestionPublicId = (string)($create['payload']['data']['suggestion']['public_id'] ?? '');
        assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required for ' . $uri);

        $preview = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/preview-apply', [], $headers);
        assertTrue($preview['status'] === 200, 'Preview apply must return 200 for ' . $uri);
        $changes = (array)($preview['payload']['data']['preview']['changes'] ?? []);
        foreach ($changes as $change) {
            if (!is_array($change)) {
                continue;
            }
            $actionType = (string)($change['type'] ?? '');
            assertTrue(isset($allowedActionTypes[$actionType]), 'Preview action type must be allowlisted, got: ' . $actionType);
        }
    }

    $serviceSource = file_get_contents(dirname(__DIR__, 2) . '/system/library/service/AiSuggestionService.php');
    assertTrue(is_string($serviceSource), 'Unable to read AiSuggestionService source');
    assertTrue(str_contains($serviceSource, 'private function getEnabledActionTypesForPreview(): array'), 'Preview allowlist helper must exist');
    assertTrue(!str_contains($serviceSource, "['actions']"), 'Preview flow must not build actions from dynamic payload.actions field');

    foreach ($targetIntents as $intentCode) {
        $snapshot = (array)$intentSnapshots[$intentCode];
        $restore = request('PATCH', '/api/v1/ai/intent-settings/' . rawurlencode($intentCode), [
            'provider_public_id' => (string)($snapshot['provider_public_id'] ?? ''),
            'model' => (string)($snapshot['model'] ?? ''),
            'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
            'required_permission' => (string)($snapshot['required_permission'] ?? ''),
            'is_enabled' => (bool)($snapshot['is_enabled'] ?? true) ? 1 : 0,
            'max_tokens' => max(1, (int)($snapshot['max_tokens'] ?? 0) > 0 ? (int)$snapshot['max_tokens'] : 1200),
        ], $headers);
        assertTrue($restore['status'] === 200, 'Intent restore must return 200 for ' . $intentCode);
    }
    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_apply_preview_allowlisted_actions_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_apply_preview_allowlisted_actions_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
