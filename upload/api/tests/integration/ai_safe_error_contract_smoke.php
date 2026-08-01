<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlag(array $items, string $code): array
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

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $aiEnabledFlag = findFlag($flagItems, 'ai.enabled');
    $aiTaskFlag = findFlag($flagItems, 'ai.task');

    $aiEnabledPublicId = (string)($aiEnabledFlag['public_id'] ?? '');
    $aiTaskPublicId = (string)($aiTaskFlag['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    assertTrue($aiTaskPublicId !== '', 'ai.task public_id is required');

    $aiEnabledOriginal = (bool)($aiEnabledFlag['is_enabled'] ?? false);
    $aiTaskOriginal = (bool)($aiTaskFlag['is_enabled'] ?? false);

    $limitNames = ['max_requests_per_minute', 'max_requests_per_day'];
    $limitOriginal = [];
    foreach ($limitNames as $name) {
        $setting = request('GET', '/api/v1/settings/' . rawurlencode($name) . '?scope=ai_limits', [], $rootHeaders);
        if ($setting['status'] === 200) {
            $limitOriginal[$name] = $setting['payload']['data']['setting']['value'] ?? null;
        } else {
            $limitOriginal[$name] = null;
        }
    }

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_error_user_' . randomSuffix(),
        'title' => 'AI Error Contract User Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'AI error role create must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'AI error role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'AI error role permissions set must be 200');

    $userLogin = 'ai.error.user.' . randomSuffix();
    $userPassword = 'AiErrorUserPass#2026!';
    $userToken = 'ai-error-user-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Error Contract User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'AI error user create must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'AI error user public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'AI error user login must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $disableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 0], $rootHeaders);
    assertTrue($disableAi['status'] === 200, 'Disable ai.enabled must be 200');

    $disabledAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_err_disabled_' . randomSuffix(),
    ], $userHeaders);
    assertTrue($disabledAction['status'] === 409, 'Disabled action status must be 409');
    assertTrue((string)($disabledAction['payload']['code'] ?? '') === 'AI_DISABLED', 'Disabled action code must be AI_DISABLED');

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');
    $enableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAiTask['status'] === 200, 'Enable ai.task must be 200');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Error Contract Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-error-default',
        'provider_payload' => [
            'mock_models' => ['mock-error-default', 'mock-error-alt'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Error contract provider create must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Error contract provider public_id is required');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = null;
    foreach ($intentItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryIntent = $item;
            break;
        }
    }
    assertTrue(is_array($taskSummaryIntent), 'task_summary intent setting must exist');

    $intentOriginalProvider = trim((string)($taskSummaryIntent['provider_public_id'] ?? ''));
    $intentOriginalModel = (string)($taskSummaryIntent['model'] ?? '');
    $intentOriginalFeatureFlag = (string)($taskSummaryIntent['feature_flag'] ?? '');
    $intentOriginalEnabled = (bool)($taskSummaryIntent['is_enabled'] ?? true);
    $intentOriginalMaxTokens = (int)($taskSummaryIntent['max_tokens'] ?? 0);

    $bindIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'admin-error-model',
        'is_enabled' => 1,
        'feature_flag' => 'ai.task',
        'max_tokens' => max(1, $intentOriginalMaxTokens > 0 ? $intentOriginalMaxTokens : 1200),
    ], $rootHeaders);
    assertTrue($bindIntent['status'] === 200, 'Intent bind status must be 200');

    $missingProviderAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_err_provider_missing_' . randomSuffix(),
    ], $userHeaders);
    assertTrue($missingProviderAction['status'] === 409, 'Provider-missing action status must be 409');
    assertTrue((string)($missingProviderAction['payload']['code'] ?? '') === 'AI_PROVIDER_NOT_CONFIGURED', 'Provider-missing code must be AI_PROVIDER_NOT_CONFIGURED');

    $secret = 'super-secret-error-contract-' . randomSuffix();
    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', ['secret' => $secret], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Set provider secret status must be 200');

    $setMinuteLimit = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'ai_limits',
        'value' => 1,
    ], $rootHeaders);
    assertTrue($setMinuteLimit['status'] === 200, 'Set max_requests_per_minute must be 200');

    $setDayLimit = request('PATCH', '/api/v1/settings/max_requests_per_day', [
        'scope' => 'ai_limits',
        'value' => 5000,
    ], $rootHeaders);
    assertTrue($setDayLimit['status'] === 200, 'Set max_requests_per_day must be 200');

    $firstAllowed = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_err_rate_ok_' . randomSuffix(),
    ], $userHeaders);
    assertTrue($firstAllowed['status'] === 200, 'First action before limit must be 200');

    $rateLimited = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_err_rate_limited_' . randomSuffix(),
    ], $userHeaders);
    assertTrue($rateLimited['status'] === 429, 'Rate-limited action status must be 429');
    assertTrue((string)($rateLimited['payload']['code'] ?? '') === 'AI_RATE_LIMITED', 'Rate-limited action code must be AI_RATE_LIMITED');
    assertTrue((int)($rateLimited['payload']['meta']['retry_after'] ?? 0) > 0, 'Rate-limited response must include retry_after');

    $patchProviderAuthError = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'provider_payload' => [
            'mock_models' => ['mock-error-default', 'mock-error-alt'],
            'simulate_test_error' => 'auth',
        ],
    ], $rootHeaders);
    assertTrue($patchProviderAuthError['status'] === 200, 'Patch provider simulate auth error must be 200');

    $providerTestAuthFailed = request('POST', '/api/v1/ai/providers/' . $providerPublicId . '/test', [], $rootHeaders);
    assertTrue($providerTestAuthFailed['status'] === 502, 'Provider auth error status must be 502');
    assertTrue((string)($providerTestAuthFailed['payload']['code'] ?? '') === 'AI_PROVIDER_AUTH_FAILED', 'Provider auth error code must be AI_PROVIDER_AUTH_FAILED');
    $providerMeta = (array)($providerTestAuthFailed['payload']['meta']['provider_error'] ?? []);
    assertTrue((string)($providerMeta['category'] ?? '') === 'auth', 'Provider auth error meta category must be auth');
    assertTrue((bool)($providerMeta['retryable'] ?? true) === false, 'Provider auth error retryable must be false');

    $rawResponse = json_encode($providerTestAuthFailed['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($rawResponse), 'Provider error response JSON must be encodable');
    assertTrue(!str_contains($rawResponse, $secret), 'Provider error response must not leak provider secret value');

    $restoreProviderPayload = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
        'provider_payload' => [
            'mock_models' => ['mock-error-default', 'mock-error-alt'],
        ],
    ], $rootHeaders);
    assertTrue($restoreProviderPayload['status'] === 200, 'Restore provider payload must be 200');

    foreach ($limitNames as $name) {
        $value = $limitOriginal[$name] ?? null;
        if ($value === null) {
            continue;
        }
        $restoreLimit = request('PATCH', '/api/v1/settings/' . rawurlencode($name), [
            'scope' => 'ai_limits',
            'value' => $value,
        ], $rootHeaders);
        assertTrue($restoreLimit['status'] === 200, 'Restore ai_limits setting must be 200: ' . $name);
    }

    $restoreIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $intentOriginalProvider,
        'model' => $intentOriginalModel,
        'feature_flag' => $intentOriginalFeatureFlag,
        'is_enabled' => $intentOriginalEnabled ? 1 : 0,
        'max_tokens' => max(1, $intentOriginalMaxTokens > 0 ? $intentOriginalMaxTokens : 1200),
    ], $rootHeaders);
    assertTrue($restoreIntent['status'] === 200, 'Restore intent settings must be 200');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    $restoreAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => $aiEnabledOriginal ? 1 : 0], $rootHeaders);
    assertTrue($restoreAiEnabled['status'] === 200, 'Restore ai.enabled must be 200');
    $restoreAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => $aiTaskOriginal ? 1 : 0], $rootHeaders);
    assertTrue($restoreAiTask['status'] === 200, 'Restore ai.task must be 200');

    fwrite(STDOUT, "[OK] ai_safe_error_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_safe_error_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
