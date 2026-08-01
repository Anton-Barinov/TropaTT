<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $settingsBefore = request('GET', '/api/v1/ai/settings', [], $rootHeaders);
    assertTrue($settingsBefore['status'] === 200, 'AI settings get must be 200');
    $settings = (array)($settingsBefore['payload']['data']['settings'] ?? []);
    $allowOptOutOriginal = array_key_exists('allow_personal_recommendations_opt_out', $settings)
        ? (bool)$settings['allow_personal_recommendations_opt_out']
        : true;

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_pref_opt_out_' . randomSuffix(),
        'title' => 'AI Preferences Opt-out Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userLogin = 'ai.pref.optout.' . randomSuffix();
    $userPassword = 'AiPrefOptOutPass#2026!';
    $userToken = 'ai-pref-optout-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Preferences Opt-out User',
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

    $enableGlobalOptOut = request('PATCH', '/api/v1/ai/settings', [
        'allow_personal_recommendations_opt_out' => 1,
    ], $rootHeaders);
    assertTrue($enableGlobalOptOut['status'] === 200, 'Enable global opt-out policy must be 200');

    $userDisableRecommendations = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'personal_recommendations_enabled' => 0,
        ],
    ], $userHeaders);
    assertTrue($userDisableRecommendations['status'] === 200, 'User must be able to disable personal recommendations when global policy allows');
    assertTrue((bool)($userDisableRecommendations['payload']['data']['preferences']['personal_recommendations_enabled'] ?? true) === false, 'personal_recommendations_enabled must be false');

    $disableGlobalOptOut = request('PATCH', '/api/v1/ai/settings', [
        'allow_personal_recommendations_opt_out' => 0,
    ], $rootHeaders);
    assertTrue($disableGlobalOptOut['status'] === 200, 'Disable global opt-out policy must be 200');

    $userEnableRecommendations = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'personal_recommendations_enabled' => 1,
        ],
    ], $userHeaders);
    assertTrue($userEnableRecommendations['status'] === 200, 'User should still be able to re-enable personal recommendations');

    $userDisableWhenForbidden = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'personal_recommendations_enabled' => 0,
        ],
    ], $userHeaders);
    assertTrue($userDisableWhenForbidden['status'] === 422, 'User disable must be rejected when global policy forbids opt-out');
    assertTrue((string)($userDisableWhenForbidden['payload']['code'] ?? '') === 'AI_PREFERENCES_OPT_OUT_FORBIDDEN', 'Code must be AI_PREFERENCES_OPT_OUT_FORBIDDEN');

    $restoreGlobal = request('PATCH', '/api/v1/ai/settings', [
        'allow_personal_recommendations_opt_out' => $allowOptOutOriginal ? 1 : 0,
    ], $rootHeaders);
    assertTrue($restoreGlobal['status'] === 200, 'Restore global opt-out policy must be 200');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_user_preferences_opt_out_policy_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_user_preferences_opt_out_policy_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
