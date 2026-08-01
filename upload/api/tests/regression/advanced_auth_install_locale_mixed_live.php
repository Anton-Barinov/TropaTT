<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicAuthInstall(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicAuthInstall($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $installStatus = liveRequest('GET', 'install/status', [], ['X-Locale' => 'zz-zz']);
    liveAssert($installStatus['status'] === 200, 'Install status must return 200');
    liveAssert((string)($installStatus['payload']['message'] ?? '') === 'Installation status', 'Install status message mismatch');

    $installCheck = liveRequest('GET', 'install/check', [], ['X-Locale' => 'zz-zz']);
    liveAssert($installCheck['status'] === 200, 'Install check must return 200');
    liveAssert((string)($installCheck['payload']['message'] ?? '') === 'Database connection successful', 'Install check message mismatch');

    $installSetup = liveRequest('POST', 'install/setup', [], ['X-Locale' => 'zz-zz']);
    liveAssert($installSetup['status'] === 409, 'Install setup in installed system must return 409');
    liveAssert((string)($installSetup['payload']['message'] ?? '') === 'System is already installed', 'Install setup message mismatch');

    $invalidLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => 'root',
        'password' => 'wrong-password',
        'token' => 'wrong-token',
    ], ['X-Locale' => 'zz-zz']);
    liveAssert($invalidLogin['status'] === 401, 'Invalid login must return 401');
    liveAssert((string)($invalidLogin['payload']['message'] ?? '') === 'Invalid credentials', 'Invalid login message mismatch');
    assertNoCyrillicAuthInstall($invalidLogin['payload']['errors'] ?? [], 'auth.invalid_login.errors');

    $logoutNoToken = liveRequest('POST', 'api/v1/auth/logout', [], ['X-Locale' => 'zz-zz']);
    liveAssert($logoutNoToken['status'] === 401, 'Logout without token must return 401');
    liveAssert((string)($logoutNoToken['payload']['message'] ?? '') === 'Unauthorized', 'Logout without token message mismatch');
    assertNoCyrillicAuthInstall($logoutNoToken['payload']['errors'] ?? [], 'auth.logout_no_token.errors');

    $meUnauthorized = liveRequest('GET', 'api/v1/auth/me', [], ['X-Locale' => 'zz-zz']);
    liveAssert($meUnauthorized['status'] === 401, 'Auth me without token must return 401');
    liveAssert((string)($meUnauthorized['payload']['message'] ?? '') === 'Unauthorized', 'Auth me unauthorized message mismatch');

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'auth_locale_' . $suffix,
        'title' => 'Auth Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $login = 'auth_locale_' . $suffix;
    $token = 'auth-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'AuthLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'AuthLocale123!',
        'token' => $token,
    ], ['X-Locale' => 'ru-ru']);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    liveAssert((string)($userLogin['payload']['message'] ?? '') === 'Login successful', 'User login message mismatch');
    $accessToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($accessToken !== '', 'Access token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $accessToken,
        'X-Locale' => 'ru-ru',
    ];

    $me = liveRequest('GET', 'api/v1/auth/me', [], $headers);
    liveAssert($me['status'] === 200, 'Auth me with token must return 200');
    liveAssert((string)($me['payload']['message'] ?? '') === 'Current user profile', 'Auth me message mismatch');

    $logout = liveRequest('POST', 'api/v1/auth/logout', [], $headers);
    liveAssert($logout['status'] === 200, 'Logout must return 200');
    liveAssert((string)($logout['payload']['message'] ?? '') === 'Logout successful', 'Logout message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_auth_install_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_auth_install_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
