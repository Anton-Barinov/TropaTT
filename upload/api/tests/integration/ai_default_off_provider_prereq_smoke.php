<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function requireFlagByCode(array $items, string $code): array
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

    $featureFlagConfigPath = dirname(__DIR__, 2) . '/config/feature_flags.php';
    $featureFlagConfig = require $featureFlagConfigPath;
    $defaults = is_array($featureFlagConfig['feature_flags'] ?? null) ? (array)$featureFlagConfig['feature_flags'] : [];
    assertTrue(array_key_exists('ai.enabled', $defaults), 'Config default must include ai.enabled');
    assertTrue((bool)$defaults['ai.enabled'] === false, 'Config default ai.enabled must be false');
    assertTrue(array_key_exists('ai.task', $defaults), 'Config default must include ai.task');
    assertTrue((bool)$defaults['ai.task'] === false, 'Config default ai.task must be false');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $flagAiEnabled = requireFlagByCode($flagItems, 'ai.enabled');
    $flagAiTask = requireFlagByCode($flagItems, 'ai.task');

    $aiEnabledPublicId = (string)($flagAiEnabled['public_id'] ?? '');
    $aiTaskPublicId = (string)($flagAiTask['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    assertTrue($aiTaskPublicId !== '', 'ai.task public_id is required');

    $aiEnabledOriginal = (bool)($flagAiEnabled['is_enabled'] ?? false);
    $aiTaskOriginal = (bool)($flagAiTask['is_enabled'] ?? false);

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_prereq_user_' . randomSuffix(),
        'title' => 'AI Prereq User Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'AI prereq role create must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'AI prereq role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'AI prereq role permissions set must be 200');

    $userLogin = 'ai.prereq.user.' . randomSuffix();
    $userPassword = 'AiPrereqUserPass#2026!';
    $userToken = 'ai-prereq-user-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Prereq User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'AI prereq user create must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'AI prereq user public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'AI prereq user login must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $disableAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, [
        'is_enabled' => 0,
    ], $rootHeaders);
    assertTrue($disableAiEnabled['status'] === 200, 'Disable ai.enabled must be 200');

    $disabledAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_default_off_' . randomSuffix(),
        'input_text' => 'Should be blocked while ai.enabled is off',
    ], $userHeaders);
    assertTrue($disabledAction['status'] === 409, 'AI action must return 409 while ai.enabled=0');
    assertTrue((string)($disabledAction['payload']['code'] ?? '') === 'AI_DISABLED', 'AI action code must be AI_DISABLED while ai.enabled=0');

    $enableAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiEnabled['status'] === 200, 'Enable ai.enabled must be 200');

    $enableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, [
        'is_enabled' => 1,
    ], $rootHeaders);
    assertTrue($enableAiTask['status'] === 200, 'Enable ai.task must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list must be 200');
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

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Prereq Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-prereq-default',
        'provider_payload' => [
            'mock_models' => ['mock-prereq-default', 'mock-prereq-alt'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Prereq provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Prereq provider public_id is required');

    $bindIntentToProviderWithoutSecret = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'admin-prereq-model',
        'is_enabled' => 1,
        'feature_flag' => 'ai.task',
        'max_tokens' => max(1, $intentOriginalMaxTokens > 0 ? $intentOriginalMaxTokens : 1200),
    ], $rootHeaders);
    assertTrue($bindIntentToProviderWithoutSecret['status'] === 200, 'Intent bind to provider-without-secret must be 200');

    $missingProviderAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_provider_missing_' . randomSuffix(),
        'input_text' => 'Should fail because bound provider has no secret',
    ], $userHeaders);
    assertTrue($missingProviderAction['status'] === 409, 'AI action must return 409 while bound provider has no secret');
    assertTrue(
        (string)($missingProviderAction['payload']['code'] ?? '') === 'AI_PROVIDER_NOT_CONFIGURED',
        'AI action code must be AI_PROVIDER_NOT_CONFIGURED while bound provider has no secret; got status='
        . (int)$missingProviderAction['status']
        . ' code=' . (string)($missingProviderAction['payload']['code'] ?? 'unknown')
    );

    $setProviderSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'prereq-provider-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($setProviderSecret['status'] === 200, 'Set provider secret must be 200');

    $configuredAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_provider_ready_' . randomSuffix(),
        'input_text' => 'Should pass after provider secret configured',
    ], $userHeaders);
    assertTrue($configuredAction['status'] === 200, 'AI action must return 200 after admin enables flags and configures provider secret');

    $restoreIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $intentOriginalProvider,
        'model' => $intentOriginalModel,
        'feature_flag' => $intentOriginalFeatureFlag,
        'is_enabled' => $intentOriginalEnabled ? 1 : 0,
        'max_tokens' => max(1, $intentOriginalMaxTokens > 0 ? $intentOriginalMaxTokens : 1200),
    ], $rootHeaders);
    assertTrue($restoreIntent['status'] === 200, 'Intent settings restore must be 200');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    $restoreAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, [
        'is_enabled' => $aiEnabledOriginal ? 1 : 0,
    ], $rootHeaders);
    assertTrue($restoreAiEnabled['status'] === 200, 'Restore ai.enabled must be 200');

    $restoreAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, [
        'is_enabled' => $aiTaskOriginal ? 1 : 0,
    ], $rootHeaders);
    assertTrue($restoreAiTask['status'] === 200, 'Restore ai.task must be 200');

    fwrite(STDOUT, "[OK] ai_default_off_provider_prereq_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_default_off_provider_prereq_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
