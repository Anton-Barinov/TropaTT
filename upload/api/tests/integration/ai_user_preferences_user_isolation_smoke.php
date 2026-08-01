<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @return array{public_id:string,headers:array<string,string>,login:string,password:string,token:string}
 */
function createAiUserForPreferencesIsolation(array $rootHeaders, string $rolePublicId): array
{
    $userLogin = 'ai.pref.isolation.' . randomSuffix();
    $userPassword = 'AiPrefIsolationPass#2026!';
    $userToken = 'ai-pref-isolation-token-' . randomSuffix();

    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Preferences Isolation User',
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
    $headers = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    return [
        'public_id' => $userPublicId,
        'headers' => $headers,
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ];
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_pref_isolation_' . randomSuffix(),
        'title' => 'AI Preferences Isolation Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userA = createAiUserForPreferencesIsolation($rootHeaders, $rolePublicId);
    $userB = createAiUserForPreferencesIsolation($rootHeaders, $rolePublicId);

    $patchA = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'preferred_response_length' => 'long',
            'focus_block_minutes' => 150,
            'work_hours_start' => '08:15',
            'work_hours_end' => '16:45',
            'daily_plan_enabled' => 0,
        ],
    ], $userA['headers']);
    assertTrue($patchA['status'] === 200, 'User A preferences patch must be 200');

    $getA = request('GET', '/api/v1/ai/preferences', [], $userA['headers']);
    assertTrue($getA['status'] === 200, 'User A preferences get must be 200');
    $prefsA = (array)($getA['payload']['data']['preferences'] ?? []);
    assertTrue((string)($prefsA['preferred_response_length'] ?? '') === 'long', 'User A preferred_response_length must be updated');
    assertTrue((int)($prefsA['focus_block_minutes'] ?? 0) === 150, 'User A focus_block_minutes must be updated');
    assertTrue((string)($prefsA['work_hours_start'] ?? '') === '08:15', 'User A work_hours_start must be updated');
    assertTrue((string)($prefsA['work_hours_end'] ?? '') === '16:45', 'User A work_hours_end must be updated');
    assertTrue((bool)($prefsA['daily_plan_enabled'] ?? true) === false, 'User A daily_plan_enabled must be updated');

    $getB = request('GET', '/api/v1/ai/preferences', [], $userB['headers']);
    assertTrue($getB['status'] === 200, 'User B preferences get must be 200');
    $prefsB = (array)($getB['payload']['data']['preferences'] ?? []);
    assertTrue((string)($prefsB['preferred_response_length'] ?? '') !== 'long', 'User B preferred_response_length must remain isolated from User A changes');
    assertTrue((int)($prefsB['focus_block_minutes'] ?? 0) !== 150, 'User B focus_block_minutes must remain isolated from User A changes');
    assertTrue((string)($prefsB['work_hours_start'] ?? '') !== '08:15', 'User B work_hours_start must remain isolated from User A changes');
    assertTrue((string)($prefsB['work_hours_end'] ?? '') !== '16:45', 'User B work_hours_end must remain isolated from User A changes');
    assertTrue((bool)($prefsB['daily_plan_enabled'] ?? true) === true, 'User B daily_plan_enabled must remain default when User A changes preferences');

    request('DELETE', '/api/v1/users/' . $userA['public_id'], [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $userB['public_id'], [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_user_preferences_user_isolation_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_user_preferences_user_isolation_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
