<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 */
function findUsageByScopeAndIntent(array $items, string $scopePublicId, string $intentSettingPublicId): ?array
{
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }

        $meta = is_array($row['request_meta'] ?? null) ? (array)$row['request_meta'] : [];
        $metaScope = (string)($meta['scope_public_id'] ?? '');
        $metaIntentSetting = (string)($meta['intent_setting_public_id'] ?? '');

        if ($metaScope === $scopePublicId && $metaIntentSetting === $intentSettingPublicId) {
            return $row;
        }
    }

    return null;
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $roleUserCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_bind_user_' . randomSuffix(),
        'title' => 'AI Binding User Role',
    ], $rootHeaders);
    assertTrue($roleUserCreate['status'] === 201, 'AI binding user role create status must be 201');
    $roleUserPublicId = (string)($roleUserCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($roleUserPublicId !== '', 'AI binding user role public_id is required');

    $setUserPermissions = request('PUT', '/api/v1/roles/' . $roleUserPublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setUserPermissions['status'] === 200, 'AI binding user role permissions set must be 200');

    $suffix = randomSuffix();
    $userLogin = 'ai.bind.user.' . $suffix;
    $userPassword = 'AiBindUserPass#2026!';
    $userToken = 'ai-bind-user-token-' . $suffix;
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Binding User',
        'role_public_ids' => [$roleUserPublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'AI binding user create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'AI binding user public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'AI binding user login status must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $providerA = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Binding Provider A ' . $suffix,
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-a-default',
        'provider_payload' => [
            'mock_models' => ['mock-a-default', 'mock-a-alt'],
        ],
        'is_default' => 1,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerA['status'] === 201, 'Provider A create status must be 201');
    $providerAPublicId = (string)($providerA['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerAPublicId !== '', 'Provider A public_id is required');

    $providerB = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Binding Provider B ' . $suffix,
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-b-default',
        'provider_payload' => [
            'mock_models' => ['mock-b-default', 'mock-b-alt'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerB['status'] === 201, 'Provider B create status must be 201');
    $providerBPublicId = (string)($providerB['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerBPublicId !== '', 'Provider B public_id is required');

    $secretA = request('PUT', '/api/v1/ai/providers/' . $providerAPublicId . '/secret', [
        'secret' => 'bind-provider-a-secret-' . $suffix,
    ], $rootHeaders);
    assertTrue($secretA['status'] === 200, 'Provider A secret set must be 200');

    $secretB = request('PUT', '/api/v1/ai/providers/' . $providerBPublicId . '/secret', [
        'secret' => 'bind-provider-b-secret-' . $suffix,
    ], $rootHeaders);
    assertTrue($secretB['status'] === 200, 'Provider B secret set must be 200');

    $flags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flags['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);
    $flagByCode = [];
    foreach ($flagItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $flagByCode[(string)($item['code'] ?? '')] = (string)($item['public_id'] ?? '');
    }

    foreach (['ai.enabled', 'ai.task'] as $flagCode) {
        assertTrue(isset($flagByCode[$flagCode]), 'Feature flag must exist: ' . $flagCode);
        $enableFlag = request('PATCH', '/api/v1/feature-flags/' . $flagByCode[$flagCode], [
            'is_enabled' => 1,
        ], $rootHeaders);
        assertTrue($enableFlag['status'] === 200, 'Enable feature flag must return 200: ' . $flagCode);
    }

    $taskSummaryIntentUpdate = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerAPublicId,
        'model' => 'admin-task-summary-model',
        'is_enabled' => 1,
        'feature_flag' => 'ai.task',
    ], $rootHeaders);
    assertTrue($taskSummaryIntentUpdate['status'] === 200, 'Task summary intent update must be 200');
    $taskSummaryIntentSettingId = (string)($taskSummaryIntentUpdate['payload']['data']['item']['public_id'] ?? '');
    assertTrue($taskSummaryIntentSettingId !== '', 'Task summary intent setting public_id is required');

    $actionScopePublicId = 'tsk_bind_scope_' . $suffix;

    $actionCall = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => $actionScopePublicId,
        'provider_public_id' => $providerBPublicId,
        'model' => 'user-override-model-action',
        'intent_code' => 'analytics_kpi_explanation',
        'input_text' => 'Attempt to override provider/model from user input',
    ], $userHeaders);
    assertTrue($actionCall['status'] === 200, 'AI action call must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Binding Task ' . $suffix,
        'description' => 'Task for backend provider binding smoke',
    ], $userHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create for summary path must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required for summary path');

    $taskSummaryCall = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'provider_public_id' => $providerBPublicId,
        'model' => 'user-override-model-task-summary-endpoint',
        'intent_code' => 'analytics_risks_explanation',
    ], $userHeaders);
    assertTrue($taskSummaryCall['status'] === 201, 'AI task summary endpoint call must be 201');

    $usageTaskSummary = request('GET', '/api/v1/ai/usage?action_type=task_summary&limit=50', [], $rootHeaders);
    assertTrue($usageTaskSummary['status'] === 200, 'AI usage task_summary list must be 200');
    $taskSummaryItems = (array)($usageTaskSummary['payload']['data']['items'] ?? []);

    $actionHit = findUsageByScopeAndIntent($taskSummaryItems, $actionScopePublicId, $taskSummaryIntentSettingId);
    assertTrue(is_array($actionHit), 'Usage row for action task_summary with configured intent setting must exist');
    assertTrue((string)($actionHit['provider_public_id'] ?? '') === $providerAPublicId, 'Action task_summary must use provider from admin intent settings (provider A)');

    $summaryHit = findUsageByScopeAndIntent($taskSummaryItems, $taskPublicId, $taskSummaryIntentSettingId);
    assertTrue(is_array($summaryHit), 'Usage row for /ai/tasks/{id}/summary with configured intent setting must exist');
    assertTrue((string)($summaryHit['provider_public_id'] ?? '') === $providerAPublicId, 'Task summary endpoint must use provider from admin intent settings (provider A)');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $roleUserPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/ai/providers/' . $providerAPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/ai/providers/' . $providerBPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_user_actions_backend_provider_binding_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_user_actions_backend_provider_binding_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
