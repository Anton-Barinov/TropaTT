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
        'title' => 'Row Version Preview Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-row-version-preview',
        'provider_payload' => [
            'mock_models' => ['mock-row-version-preview'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'row-version-preview-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentList = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentList['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentList['payload']['data']['items'] ?? []);
    $taskSummaryIntent = null;
    foreach ($intentItems as $item) {
        if ((string)($item['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryIntent = is_array($item) ? $item : null;
            break;
        }
    }
    assertTrue(is_array($taskSummaryIntent), 'task_summary intent setting must exist');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 0),
    ];

    $intentPatch = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-row-version-preview',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($intentPatch['status'] === 200, 'Intent patch status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI row version preview task ' . randomSuffix(),
        'description' => 'Исходное описание задачи для проверки diff preview и row_version.',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Улучши описание задачи, перепиши description подробнее и структурированнее',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'AI task summary create status must be 201');
    $suggestionPublicId = (string)($taskSummary['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');

    $preview = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/apply-preview', [], $rootHeaders);
    assertTrue($preview['status'] === 200, 'Suggestion preview status must be 200');
    $previewData = (array)($preview['payload']['data']['preview'] ?? []);
    assertTrue((bool)($previewData['requires_confirmation'] ?? false) === true, 'Description update preview must require confirmation');
    $supportedEndpoints = (array)($previewData['supported_apply_endpoints'] ?? []);

    $changes = (array)($previewData['changes'] ?? []);

    $foundDescriptionChange = null;
    foreach ($changes as $change) {
        if (!is_array($change)) {
            continue;
        }
        if ((string)($change['type'] ?? '') === 'update_task_description' && (string)($change['field'] ?? '') === 'task.description') {
            $foundDescriptionChange = $change;
            break;
        }
    }
    if (!is_array($foundDescriptionChange)) {
        $actionTypes = request('GET', '/api/v1/ai/action-types', [], $rootHeaders);
        assertTrue($actionTypes['status'] === 200, 'AI action-types list status must be 200');
        $actionItems = (array)($actionTypes['payload']['data']['items'] ?? []);
        $hasUpdateAction = false;
        foreach ($actionItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ((string)($item['code'] ?? '') === 'update_task_description') {
                $hasUpdateAction = true;
                break;
            }
        }
        assertTrue(!$hasUpdateAction, 'If update_task_description action is enabled, preview must include update action change');

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
        fwrite(STDOUT, "[OK] ai_task_description_preview_row_version_smoke (update action disabled in runtime)\n");
        return;
    }
    $hasCanonicalEndpoint = in_array('/api/v1/tasks/{public_id}', $supportedEndpoints, true)
        || (string)($foundDescriptionChange['apply_endpoint_hint'] ?? '') === '/api/v1/tasks/{public_id}';
    assertTrue($hasCanonicalEndpoint, 'Description update preview must expose canonical task patch endpoint');
    assertTrue((bool)($foundDescriptionChange['requires_row_version'] ?? false) === true, 'Description update preview must require row_version');
    assertTrue(trim((string)($foundDescriptionChange['value'] ?? '')) !== '', 'Description update preview must contain proposed description value');
    assertTrue((string)($foundDescriptionChange['risk_level'] ?? '') === 'high', 'Description update preview must mark update as high risk');

    $taskRead = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $rootHeaders);
    assertTrue($taskRead['status'] === 200, 'Task read status must be 200');
    $rowVersion = (int)($taskRead['payload']['data']['task']['row_version'] ?? 0);
    assertTrue($rowVersion > 0, 'Task row_version must be > 0 before apply');

    $confirmOk = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/confirm', [
        'decision' => 'applied',
        'apply_target' => '/api/v1/tasks/{public_id}',
        'apply_target_public_id' => $taskPublicId,
        'row_version' => $rowVersion,
    ], $rootHeaders);
    assertTrue($confirmOk['status'] === 200, 'Suggestion confirm with current row_version must succeed');

    $taskPatch = request('PATCH', '/api/v1/tasks/' . $taskPublicId, [
        'description' => 'Описание изменено после confirm для проверки конфликта row_version',
        'row_version' => $rowVersion,
    ], $rootHeaders);
    assertTrue($taskPatch['status'] === 200, 'Task patch with current row_version must succeed');

    $confirmConflict = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/confirm', [
        'decision' => 'applied',
        'apply_target' => '/api/v1/tasks/{public_id}',
        'apply_target_public_id' => $taskPublicId,
        'row_version' => $rowVersion,
    ], $rootHeaders);
    assertTrue($confirmConflict['status'] === 409, 'Suggestion confirm with stale row_version must return 409');
    assertTrue((string)($confirmConflict['payload']['code'] ?? '') === 'AI_ROW_VERSION_CONFLICT', 'Suggestion confirm stale row_version must return AI_ROW_VERSION_CONFLICT');
    assertTrue((int)($confirmConflict['payload']['meta']['row_version'] ?? 0) > $rowVersion, 'Conflict payload must include newer row_version');

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

    fwrite(STDOUT, "[OK] ai_task_description_preview_row_version_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_task_description_preview_row_version_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
