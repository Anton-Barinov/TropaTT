<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlagOrFailActionPerm(array $items, string $code): array
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
    $aiEnabledFlag = findFlagOrFailActionPerm($flagItems, 'ai.enabled');
    $aiEnabledPublicId = (string)($aiEnabledFlag['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    $aiEnabledOriginal = (bool)($aiEnabledFlag['is_enabled'] ?? false);

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_perm_denial_' . randomSuffix(),
        'title' => 'AI Permission Denial Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userLogin = 'ai.perm.denial.' . randomSuffix();
    $userPassword = 'AiPermDenialPass#2026!';
    $userToken = 'ai-perm-denial-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Permission Denial User',
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

    $actionTypesResponse = request('GET', '/api/v1/ai/action-types', [], $rootHeaders);
    assertTrue($actionTypesResponse['status'] === 200, 'Action types list status must be 200');
    $actionTypes = (array)($actionTypesResponse['payload']['data']['items'] ?? []);
    assertTrue(count($actionTypes) > 0, 'Action types allowlist must not be empty');

    $intentSettingsResponse = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettingsResponse['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettingsResponse['payload']['data']['items'] ?? []);
    $intentByCode = [];
    foreach ($intentItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $intentCode = trim((string)($item['intent_code'] ?? ''));
        if ($intentCode === '') {
            continue;
        }
        $intentByCode[$intentCode] = $item;
    }

    $snapshots = [];
    foreach ($actionTypes as $actionTypeRaw) {
        $actionType = trim((string)$actionTypeRaw);
        if ($actionType === '') {
            continue;
        }

        $intent = $intentByCode[$actionType] ?? null;
        assertTrue(is_array($intent), 'Intent settings row must exist for action type: ' . $actionType);

        $snapshots[$actionType] = [
            'required_permission' => (string)($intent['required_permission'] ?? ''),
            'feature_flag' => (string)($intent['feature_flag'] ?? ''),
            'is_enabled' => (bool)($intent['is_enabled'] ?? true),
            'provider_public_id' => trim((string)($intent['provider_public_id'] ?? '')),
            'model' => (string)($intent['model'] ?? ''),
            'max_tokens' => (int)($intent['max_tokens'] ?? 0),
        ];

        $hardenIntent = request('PATCH', '/api/v1/ai/intent-settings/' . rawurlencode($actionType), [
            'required_permission' => 'ai.admin',
            'feature_flag' => 'ai.enabled',
            'is_enabled' => 1,
            'provider_public_id' => $snapshots[$actionType]['provider_public_id'],
            'model' => $snapshots[$actionType]['model'],
            'max_tokens' => max(1, $snapshots[$actionType]['max_tokens'] > 0 ? $snapshots[$actionType]['max_tokens'] : 1200),
        ], $rootHeaders);
        assertTrue($hardenIntent['status'] === 200, 'Intent hardening patch must be 200 for action type: ' . $actionType);
    }

    $denied = [];
    foreach ($actionTypes as $actionTypeRaw) {
        $actionType = trim((string)$actionTypeRaw);
        if ($actionType === '') {
            continue;
        }

        $call = request('POST', '/api/v1/ai/actions/' . rawurlencode($actionType), [
            'scope_type' => 'task',
            'scope_public_id' => 'tsk_perm_denial_' . randomSuffix(),
            'input_text' => 'Permission denial coverage check',
        ], $userHeaders);

        if ($call['status'] !== 403 || (string)($call['payload']['code'] ?? '') !== 'FORBIDDEN') {
            $denied[] = $actionType . ':status=' . (int)$call['status'] . ':code=' . (string)($call['payload']['code'] ?? '');
        }
    }

    assertTrue($denied === [], 'Each action type must return FORBIDDEN for ai.use-only user. Failures: ' . implode(', ', $denied));

    foreach ($snapshots as $actionType => $snapshot) {
        $restore = request('PATCH', '/api/v1/ai/intent-settings/' . rawurlencode($actionType), [
            'required_permission' => (string)$snapshot['required_permission'],
            'feature_flag' => (string)$snapshot['feature_flag'],
            'is_enabled' => (bool)$snapshot['is_enabled'] ? 1 : 0,
            'provider_public_id' => (string)$snapshot['provider_public_id'],
            'model' => (string)$snapshot['model'],
            'max_tokens' => max(1, (int)$snapshot['max_tokens'] > 0 ? (int)$snapshot['max_tokens'] : 1200),
        ], $rootHeaders);
        assertTrue($restore['status'] === 200, 'Intent restore patch must be 200 for action type: ' . $actionType);
    }

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    $restoreAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => $aiEnabledOriginal ? 1 : 0], $rootHeaders);
    assertTrue($restoreAiEnabled['status'] === 200, 'Restore ai.enabled must be 200');

    fwrite(STDOUT, "[OK] ai_action_type_permission_denial_coverage_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_action_type_permission_denial_coverage_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
