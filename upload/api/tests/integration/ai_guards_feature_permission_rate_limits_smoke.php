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

    // 1) permission gate: user without ai.use must not call AI endpoint.
    $noAiRoleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_no_use_' . randomSuffix(),
        'title' => 'AI No Use Role',
    ], $rootHeaders);
    assertTrue($noAiRoleCreate['status'] === 201, 'Role without ai.use create must be 201');
    $noAiRolePublicId = (string)($noAiRoleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($noAiRolePublicId !== '', 'Role without ai.use public_id is required');

    $noAiRolePerms = request('PUT', '/api/v1/roles/' . $noAiRolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    assertTrue($noAiRolePerms['status'] === 200, 'Set role permissions without ai.use must be 200');

    $noAiUserLogin = 'ai.no.use.' . randomSuffix();
    $noAiUserPassword = 'NoAiUsePass#2026!';
    $noAiUserToken = 'no-ai-use-token-' . randomSuffix();
    $noAiUserCreate = request('POST', '/api/v1/users', [
        'login' => $noAiUserLogin,
        'password' => $noAiUserPassword,
        'token' => $noAiUserToken,
        'email' => $noAiUserLogin . '@crm.local',
        'full_name' => 'AI No Use User',
        'role_public_ids' => [$noAiRolePublicId],
    ], $rootHeaders);
    assertTrue($noAiUserCreate['status'] === 201, 'User without ai.use create must be 201');
    $noAiUserPublicId = (string)($noAiUserCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($noAiUserPublicId !== '', 'User without ai.use public_id is required');

    $noAiUserAuth = request('POST', '/api/v1/auth/login', [
        'login' => $noAiUserLogin,
        'password' => $noAiUserPassword,
        'token' => $noAiUserToken,
    ]);
    assertTrue($noAiUserAuth['status'] === 200, 'User without ai.use login must be 200');
    $noAiUserHeaders = authHeaders((string)($noAiUserAuth['payload']['data']['access_token'] ?? ''));

    $noAiUseCall = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_guard_' . randomSuffix(),
        'input_text' => 'Permission gate check',
    ], $noAiUserHeaders);
    assertTrue($noAiUseCall['status'] === 403, 'User without ai.use must receive 403 on AI endpoint');
    assertTrue((string)($noAiUseCall['payload']['code'] ?? '') === 'FORBIDDEN', 'Permission gate must return FORBIDDEN');

    // Prepare ai.use user and configured provider for feature/rate limits checks.
    $aiUseRoleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_use_guards_' . randomSuffix(),
        'title' => 'AI Use Guards Role',
    ], $rootHeaders);
    assertTrue($aiUseRoleCreate['status'] === 201, 'Role with ai.use create must be 201');
    $aiUseRolePublicId = (string)($aiUseRoleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($aiUseRolePublicId !== '', 'Role with ai.use public_id is required');

    $aiUseRolePerms = request('PUT', '/api/v1/roles/' . $aiUseRolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($aiUseRolePerms['status'] === 200, 'Set role permissions with ai.use must be 200');

    $aiUseUserLogin = 'ai.use.guards.' . randomSuffix();
    $aiUseUserPassword = 'AiUseGuardPass#2026!';
    $aiUseUserToken = 'ai-use-guard-token-' . randomSuffix();
    $aiUseUserCreate = request('POST', '/api/v1/users', [
        'login' => $aiUseUserLogin,
        'password' => $aiUseUserPassword,
        'token' => $aiUseUserToken,
        'email' => $aiUseUserLogin . '@crm.local',
        'full_name' => 'AI Use Guard User',
        'role_public_ids' => [$aiUseRolePublicId],
    ], $rootHeaders);
    assertTrue($aiUseUserCreate['status'] === 201, 'User with ai.use create must be 201');
    $aiUseUserPublicId = (string)($aiUseUserCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($aiUseUserPublicId !== '', 'User with ai.use public_id is required');

    $aiUseUserAuth = request('POST', '/api/v1/auth/login', [
        'login' => $aiUseUserLogin,
        'password' => $aiUseUserPassword,
        'token' => $aiUseUserToken,
    ]);
    assertTrue($aiUseUserAuth['status'] === 200, 'User with ai.use login must be 200');
    $aiUseUserHeaders = authHeaders((string)($aiUseUserAuth['payload']['data']['access_token'] ?? ''));

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);
    $aiEnabledFlag = findFlagOrFail($flagItems, 'ai.enabled');
    $aiTaskFlag = findFlagOrFail($flagItems, 'ai.task');

    $aiEnabledPublicId = (string)($aiEnabledFlag['public_id'] ?? '');
    $aiTaskPublicId = (string)($aiTaskFlag['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    assertTrue($aiTaskPublicId !== '', 'ai.task public_id is required');

    $aiEnabledOriginal = (bool)($aiEnabledFlag['is_enabled'] ?? false);
    $aiTaskOriginal = (bool)($aiTaskFlag['is_enabled'] ?? false);

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');
    $enableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAiTask['status'] === 200, 'Enable ai.task must be 200');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Guards Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-guards-default',
        'provider_payload' => [
            'mock_models' => ['mock-guards-default'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'guards-secret-' . randomSuffix(),
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
        'model' => 'mock-guards-default',
        'feature_flag' => 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'],
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'Intent patch status must be 200');

    // 2) feature-flag gate: disable ai.task => AI_FEATURE_DISABLED.
    $disableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => 0], $rootHeaders);
    assertTrue($disableAiTask['status'] === 200, 'Disable ai.task must be 200');

    $featureDenied = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_feature_guard_' . randomSuffix(),
        'input_text' => 'Feature flag guard check',
    ], $aiUseUserHeaders);
    assertTrue($featureDenied['status'] === 409, 'Disabled ai.task must return 409');
    assertTrue((string)($featureDenied['payload']['code'] ?? '') === 'AI_FEATURE_DISABLED', 'Feature gate must return AI_FEATURE_DISABLED');

    $enableAiTaskAgain = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAiTaskAgain['status'] === 200, 'Re-enable ai.task must be 200');

    // 3) rate-limit gate: minute limit=1, first call OK, second call => AI_RATE_LIMITED.
    $limitNames = ['max_requests_per_minute', 'max_requests_per_day'];
    $limitsSnapshot = [];
    foreach ($limitNames as $name) {
        $limitGet = request('GET', '/api/v1/settings/' . rawurlencode($name) . '?scope=ai_limits', [], $rootHeaders);
        assertTrue($limitGet['status'] === 200, 'Get ai_limits setting must be 200: ' . $name);
        $limitsSnapshot[$name] = $limitGet['payload']['data']['setting']['value'] ?? null;
    }

    $setMinuteLimit = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'ai_limits',
        'value' => 1,
    ], $rootHeaders);
    assertTrue($setMinuteLimit['status'] === 200, 'Set max_requests_per_minute must be 200');
    $setDayLimit = request('PATCH', '/api/v1/settings/max_requests_per_day', [
        'scope' => 'ai_limits',
        'value' => 100000,
    ], $rootHeaders);
    assertTrue($setDayLimit['status'] === 200, 'Set max_requests_per_day must be 200');

    $rateOk = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_rate_ok_' . randomSuffix(),
        'input_text' => 'Rate limit first request',
    ], $aiUseUserHeaders);
    assertTrue($rateOk['status'] === 200, 'First request under rate limit must return 200');

    $rateLimited = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_rate_limited_' . randomSuffix(),
        'input_text' => 'Rate limit second request',
    ], $aiUseUserHeaders);
    assertTrue($rateLimited['status'] === 429, 'Second request over minute limit must return 429');
    assertTrue((string)($rateLimited['payload']['code'] ?? '') === 'AI_RATE_LIMITED', 'Rate-limit code must be AI_RATE_LIMITED');
    $retryAfter = (int)($rateLimited['payload']['meta']['retry_after'] ?? 0);
    assertTrue($retryAfter > 0, 'Rate-limit response must include retry_after > 0');

    // Restore state.
    request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => (string)($intentSnapshot['provider_public_id'] ?? ''),
        'model' => (string)($intentSnapshot['model'] ?? ''),
        'feature_flag' => (string)($intentSnapshot['feature_flag'] ?? ''),
        'required_permission' => (string)($intentSnapshot['required_permission'] ?? ''),
        'is_enabled' => (bool)($intentSnapshot['is_enabled'] ?? true) ? 1 : 0,
        'max_tokens' => max(1, (int)($intentSnapshot['max_tokens'] ?? 0) > 0 ? (int)$intentSnapshot['max_tokens'] : 1200),
    ], $rootHeaders);

    request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => $aiEnabledOriginal ? 1 : 0], $rootHeaders);
    request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => $aiTaskOriginal ? 1 : 0], $rootHeaders);

    foreach ($limitNames as $name) {
        request('PATCH', '/api/v1/settings/' . rawurlencode($name), [
            'scope' => 'ai_limits',
            'value' => $limitsSnapshot[$name] ?? null,
        ], $rootHeaders);
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $noAiUserPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $noAiRolePublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $aiUseUserPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $aiUseRolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_guards_feature_permission_rate_limits_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_guards_feature_permission_rate_limits_smoke: " . $e->getMessage() . "\n");
    exit(1);
}

