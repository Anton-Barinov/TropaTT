<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $aiSettingsBefore = request('GET', '/api/v1/ai/settings', [], $rootHeaders);
    assertTrue($aiSettingsBefore['status'] === 200, 'AI settings get must be 200');
    $settingsBefore = (array)($aiSettingsBefore['payload']['data']['settings'] ?? []);
    $defaultModelBefore = (string)($settingsBefore['default_model'] ?? '');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_pref_no_provider_' . randomSuffix(),
        'title' => 'AI Preferences No Provider Access Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userLogin = 'ai.pref.noprovider.' . randomSuffix();
    $userPassword = 'AiPrefNoProviderPass#2026!';
    $userToken = 'ai-pref-noprovider-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Preferences No Provider User',
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

    $prefPatch = request('PATCH', '/api/v1/ai/preferences', [
        'preferences' => [
            'preferred_response_length' => 'long',
            'default_model' => 'forbidden-model-via-preferences',
            'provider_public_id' => 'aip_forbidden_via_preferences',
            'base_url' => 'https://example.com',
            'token' => 'forbidden-token',
            'secret' => 'forbidden-secret',
        ],
    ], $userHeaders);
    assertTrue($prefPatch['status'] === 200, 'AI preferences patch must be 200 for allowed preference fields');

    $preferences = (array)($prefPatch['payload']['data']['preferences'] ?? []);
    assertTrue((string)($preferences['preferred_response_length'] ?? '') === 'long', 'Allowed preference field must be updated');
    assertTrue(!array_key_exists('default_model', $preferences), 'Provider/model fields must not be persisted in user preferences');
    assertTrue(!array_key_exists('provider_public_id', $preferences), 'Provider fields must not be persisted in user preferences');
    assertTrue(!array_key_exists('base_url', $preferences), 'Provider URL must not be persisted in user preferences');
    assertTrue(!array_key_exists('token', $preferences), 'Token-like field must not be persisted in user preferences');
    assertTrue(!array_key_exists('secret', $preferences), 'Secret-like field must not be persisted in user preferences');

    $prefGet = request('GET', '/api/v1/ai/preferences', [], $userHeaders);
    assertTrue($prefGet['status'] === 200, 'AI preferences get must be 200');
    $preferencesGet = (array)($prefGet['payload']['data']['preferences'] ?? []);
    assertTrue(!array_key_exists('default_model', $preferencesGet), 'Provider/model fields must not appear in stored preferences');
    assertTrue(!array_key_exists('provider_public_id', $preferencesGet), 'Provider fields must not appear in stored preferences');

    $providersForbidden = request('GET', '/api/v1/ai/providers', [], $userHeaders);
    assertTrue($providersForbidden['status'] === 403, 'ai.use-only user must not gain provider access via preferences');

    $aiSettingsAfter = request('GET', '/api/v1/ai/settings', [], $rootHeaders);
    assertTrue($aiSettingsAfter['status'] === 200, 'AI settings get after preferences patch must be 200');
    $settingsAfter = (array)($aiSettingsAfter['payload']['data']['settings'] ?? []);
    assertTrue((string)($settingsAfter['default_model'] ?? '') === $defaultModelBefore, 'Global ai.settings.default_model must not change via user preferences');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_user_preferences_no_provider_access_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_user_preferences_no_provider_access_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
