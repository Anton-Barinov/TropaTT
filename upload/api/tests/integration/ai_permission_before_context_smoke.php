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

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flags['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);

    $aiEnabled = findFlagOrFail($flagItems, 'ai.enabled');
    $aiProject = findFlagOrFail($flagItems, 'ai.project');

    $aiEnabledPublicId = (string)($aiEnabled['public_id'] ?? '');
    $aiProjectPublicId = (string)($aiProject['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    assertTrue($aiProjectPublicId !== '', 'ai.project public_id is required');

    $aiEnabledOriginal = (bool)($aiEnabled['is_enabled'] ?? false);
    $aiProjectOriginal = (bool)($aiProject['is_enabled'] ?? false);

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');

    $enableAiProject = request('PATCH', '/api/v1/feature-flags/' . $aiProjectPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAiProject['status'] === 200, 'Enable ai.project must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);

    $projectSummaryIntent = null;
    foreach ($intentItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === 'project_summary') {
            $projectSummaryIntent = $item;
            break;
        }
    }
    assertTrue(is_array($projectSummaryIntent), 'project_summary intent setting must exist');

    $intentOriginalProvider = trim((string)($projectSummaryIntent['provider_public_id'] ?? ''));
    $intentOriginalModel = (string)($projectSummaryIntent['model'] ?? '');
    $intentOriginalFeatureFlag = (string)($projectSummaryIntent['feature_flag'] ?? '');
    $intentOriginalEnabled = (bool)($projectSummaryIntent['is_enabled'] ?? true);
    $intentOriginalRequiredPermission = (string)($projectSummaryIntent['required_permission'] ?? '');
    $intentOriginalMaxTokens = (int)($projectSummaryIntent['max_tokens'] ?? 0);

    $restrictProjectSummary = request('PATCH', '/api/v1/ai/intent-settings/project_summary', [
        'required_permission' => 'ai.admin',
        'feature_flag' => 'ai.project',
        'is_enabled' => 1,
        'provider_public_id' => $intentOriginalProvider,
        'model' => $intentOriginalModel,
        'max_tokens' => max(1, $intentOriginalMaxTokens > 0 ? $intentOriginalMaxTokens : 1200),
    ], $rootHeaders);
    assertTrue($restrictProjectSummary['status'] === 200, 'Restrict project_summary required_permission must be 200');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_perm_ctx_' . randomSuffix(),
        'title' => 'AI Permission Context User Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userLogin = 'ai.perm.ctx.' . randomSuffix();
    $userPassword = 'AiPermCtxPass#2026!';
    $userToken = 'ai-perm-ctx-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Permission Context User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'User create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'User public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'User login status must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $projectSummaryCall = request('POST', '/api/v1/ai/projects/prj_not_existing_anywhere/summary', [
        'prompt' => 'Should not matter',
    ], $userHeaders);

    assertTrue($projectSummaryCall['status'] === 403, 'project_summary must fail with FORBIDDEN before context/object lookup');
    assertTrue((string)($projectSummaryCall['payload']['code'] ?? '') === 'FORBIDDEN', 'project_summary error code must be FORBIDDEN when required_permission is missing');

    $restoreIntent = request('PATCH', '/api/v1/ai/intent-settings/project_summary', [
        'required_permission' => $intentOriginalRequiredPermission,
        'feature_flag' => $intentOriginalFeatureFlag,
        'is_enabled' => $intentOriginalEnabled ? 1 : 0,
        'provider_public_id' => $intentOriginalProvider,
        'model' => $intentOriginalModel,
        'max_tokens' => max(1, $intentOriginalMaxTokens > 0 ? $intentOriginalMaxTokens : 1200),
    ], $rootHeaders);
    assertTrue($restoreIntent['status'] === 200, 'Restore project_summary intent settings must be 200');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    $restoreAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => $aiEnabledOriginal ? 1 : 0], $rootHeaders);
    assertTrue($restoreAiEnabled['status'] === 200, 'Restore ai.enabled must be 200');
    $restoreAiProject = request('PATCH', '/api/v1/feature-flags/' . $aiProjectPublicId, ['is_enabled' => $aiProjectOriginal ? 1 : 0], $rootHeaders);
    assertTrue($restoreAiProject['status'] === 200, 'Restore ai.project must be 200');

    fwrite(STDOUT, "[OK] ai_permission_before_context_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_permission_before_context_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
