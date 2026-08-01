<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);

    $projectRoot = dirname(__DIR__, 3);
    $routes = require $projectRoot . '/web/config/routes.php';
    assertTrue(is_array($routes), 'web routes must be array');
    assertTrue(array_key_exists('admin-settings', $routes), 'web route admin-settings must exist');

    $tpl = file_get_contents($projectRoot . '/web/view/template/page/admin_settings.php');
    assertTrue(is_string($tpl) && str_contains($tpl, 'data-page="admin-settings"'), 'admin-settings template marker missing');
    assertTrue(is_string($tpl) && str_contains($tpl, 'id="adminSettingsSystemBody"'), 'admin-settings system table missing');
    assertTrue(is_string($tpl) && str_contains($tpl, 'id="adminSettingsRetentionBody"'), 'admin-settings retention table missing');

    $bindings = file_get_contents($projectRoot . '/web/assets/js/page-api-bindings.js');
    assertTrue(is_string($bindings) && str_contains($bindings, 'async function renderAdminSettingsPage()'), 'renderAdminSettingsPage must exist');
    assertTrue(is_string($bindings) && str_contains($bindings, "if (route === 'admin-settings') return await renderAdminSettingsPage();"), 'admin-settings route renderer must be connected');
    assertTrue(is_string($bindings) && str_contains($bindings, 'api/v1/settings'), 'admin-settings bindings must call settings API');
    assertTrue(is_string($bindings) && str_contains($bindings, 'api/v1/retention/metadata'), 'admin-settings bindings must call retention API');

    $settingsList = request('GET', '/api/v1/settings?scope=system&limit=20', [], $rootHeaders);
    assertTrue($settingsList['status'] === 200, 'System settings list must return 200');

    $systemItems = (array)($settingsList['payload']['data']['items'] ?? []);
    $previousValue = null;
    foreach ($systemItems as $item) {
        if ((string)($item['name'] ?? '') === 'max_requests_per_minute') {
            $previousValue = (string)($item['value'] ?? '');
            break;
        }
    }
    if ($previousValue === null || $previousValue === '') {
        $previousValue = '60';
    }
    $nextValue = (string)((int)$previousValue + 1);

    $settingPatch = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'system',
        'value' => $nextValue,
    ], $rootHeaders);
    assertTrue($settingPatch['status'] === 200, 'Patch max_requests_per_minute must return 200');

    $settingRestore = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'system',
        'value' => $previousValue,
    ], $rootHeaders);
    assertTrue($settingRestore['status'] === 200, 'Restore max_requests_per_minute must return 200');

    $beforeRetention = request('GET', '/api/v1/retention/metadata', [], $rootHeaders);
    assertTrue($beforeRetention['status'] === 200, 'Retention metadata get must return 200');
    $previousRetention = (int)($beforeRetention['payload']['data']['retention']['request_logs_days'] ?? 180);
    $nextRetention = $previousRetention + 1;

    $retentionPatch = request('PATCH', '/api/v1/retention/metadata', [
        'request_logs_days' => $nextRetention,
    ], $rootHeaders);
    assertTrue($retentionPatch['status'] === 200, 'Retention metadata patch must return 200');

    $retentionRestore = request('PATCH', '/api/v1/retention/metadata', [
        'request_logs_days' => $previousRetention,
    ], $rootHeaders);
    assertTrue($retentionRestore['status'] === 200, 'Retention metadata restore must return 200');

    $suffix = randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'settings_restricted_' . $suffix,
        'title' => 'Settings Restricted ' . $suffix,
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Restricted role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Restricted role public_id is required');

    $login = 'settings.restricted.' . $suffix;
    $password = 'SettingsRestricted#2026!';
    $token = 'settings-restricted-token-' . $suffix;

    $userCreate = request('POST', '/api/v1/users', [
        'login' => $login,
        'password' => $password,
        'email' => $login . '@crm.local',
        'full_name' => 'Settings Restricted User',
        'token' => $token,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'Restricted user create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');

    $auth = request('POST', '/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'token' => $token,
    ]);
    assertTrue($auth['status'] === 200, 'Restricted login must return 200');
    $restrictedHeaders = authHeaders((string)($auth['payload']['data']['access_token'] ?? ''));

    $forbiddenSettings = request('GET', '/api/v1/settings?scope=system&limit=10', [], $restrictedHeaders);
    assertTrue($forbiddenSettings['status'] === 403, 'Restricted role without settings.manage must get 403 for settings list');

    $forbiddenRetention = request('GET', '/api/v1/retention/metadata', [], $restrictedHeaders);
    assertTrue($forbiddenRetention['status'] === 403, 'Restricted role without settings.manage must get 403 for retention metadata');

    if ($userPublicId !== '') {
        request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    }
    if ($rolePublicId !== '') {
        request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] web_admin_settings_page_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_admin_settings_page_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
