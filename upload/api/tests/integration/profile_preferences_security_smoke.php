<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $profile = request('GET', '/api/v1/profile/me', [], $headers);
    assertTrue($profile['status'] === 200, 'Profile me status must be 200');
    assertTrue(($profile['payload']['code'] ?? '') === 'PROFILE_ME', 'Profile me code mismatch');
    $rootPublicId = (string)($profile['payload']['data']['user']['public_id'] ?? '');
    assertTrue($rootPublicId !== '', 'Profile me public_id required');

    $profileUpdate = request('PATCH', '/api/v1/profile/me', [
        'full_name' => 'Главный администратор',
        'locale' => 'ru-ru',
        'timezone' => 'Europe/Moscow',
    ], $headers);
    assertTrue($profileUpdate['status'] === 200, 'Profile update status must be 200');
    assertTrue(($profileUpdate['payload']['code'] ?? '') === 'PROFILE_UPDATED', 'Profile update code mismatch');

    $setPrefs = request('PUT', '/api/v1/profile/preferences', [
        'preferences' => [
            'dashboard_layout' => 'compact',
            'notifications' => ['email' => false, 'in_app' => true],
        ],
    ], $headers);
    assertTrue($setPrefs['status'] === 200, 'Profile preferences set status must be 200');
    assertTrue(($setPrefs['payload']['code'] ?? '') === 'PROFILE_PREFERENCES_UPDATED', 'Profile preferences set code mismatch');

    $getPrefs = request('GET', '/api/v1/profile/preferences', [], $headers);
    assertTrue($getPrefs['status'] === 200, 'Profile preferences get status must be 200');
    assertTrue(($getPrefs['payload']['code'] ?? '') === 'PROFILE_PREFERENCES', 'Profile preferences get code mismatch');
    assertTrue(($getPrefs['payload']['data']['preferences']['dashboard_layout'] ?? '') === 'compact', 'Profile preference dashboard_layout mismatch');

    $aliasGet = request('GET', '/api/v1/profile/get', [], $headers);
    assertTrue($aliasGet['status'] === 200, 'Profile alias get status must be 200');

    $tmpLogin = 'profile_' . randomSuffix();
    $tmpPassword = 'TmpPass123!';
    $tmpToken = 'tmp_' . randomSuffix();
    $tmpUser = request('POST', '/api/v1/users', [
        'login' => $tmpLogin,
        'password' => $tmpPassword,
        'token' => $tmpToken,
        'full_name' => 'Profile User',
        'email' => $tmpLogin . '@crm.local',
    ], $headers);
    assertTrue($tmpUser['status'] === 201, 'Temp user create status must be 201');

    $tmpLoginRes = request('POST', '/api/v1/auth/login', [
        'login' => $tmpLogin,
        'password' => $tmpPassword,
        'token' => $tmpToken,
    ]);
    assertTrue($tmpLoginRes['status'] === 200, 'Temp user login status must be 200');
    $tmpAccessToken = (string)($tmpLoginRes['payload']['data']['access_token'] ?? '');
    assertTrue($tmpAccessToken !== '', 'Temp user access token required');
    $tmpHeaders = authHeaders($tmpAccessToken);

    $changeBad = request('POST', '/api/v1/profile/change-password', [
        'current_password' => 'wrong-password',
        'new_password' => 'NewTmpPass123!',
    ], $tmpHeaders);
    assertTrue($changeBad['status'] === 422, 'Profile change password invalid current status must be 422');
    assertTrue(($changeBad['payload']['code'] ?? '') === 'INVALID_CURRENT_PASSWORD', 'Profile change password invalid current code mismatch');

    $changeOk = request('POST', '/api/v1/profile/change-password', [
        'current_password' => $tmpPassword,
        'new_password' => 'NewTmpPass123!',
    ], $tmpHeaders);
    assertTrue($changeOk['status'] === 200, 'Profile change password success status must be 200');
    assertTrue(($changeOk['payload']['code'] ?? '') === 'PROFILE_PASSWORD_CHANGED', 'Profile change password success code mismatch');

    $relogin = request('POST', '/api/v1/auth/login', [
        'login' => $tmpLogin,
        'password' => 'NewTmpPass123!',
        'token' => $tmpToken,
    ]);
    assertTrue($relogin['status'] === 200, 'Temp user relogin with new password must be 200');

    $unauth = request('GET', '/api/v1/profile/me');
    assertTrue($unauth['status'] === 401, 'Profile unauthorized status must be 401');

    echo "[OK] Profile/preferences/security smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
