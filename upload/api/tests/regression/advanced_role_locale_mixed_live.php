<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicRole(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicRole($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));
    $login = 'role_locale_' . $suffix;
    $token = 'role-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'RoleLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'is_root' => 1,
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'RoleLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $list = liveRequest('GET', 'api/v1/roles', [], $headers);
    liveAssert($list['status'] === 200, 'Role list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Roles list', 'Role list message mismatch');

    $createValidation = liveRequest('POST', 'api/v1/roles', [], $headers);
    liveAssert($createValidation['status'] === 422, 'Role create validation must return 422');
    liveAssert((string)($createValidation['payload']['message'] ?? '') === 'Validation error', 'Role create validation message mismatch');

    $managedCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'managed_role_' . $suffix,
        'title' => 'Managed Role ' . $suffix,
    ], $headers);
    liveAssert($managedCreate['status'] === 201, 'Managed role create must return 201');
    liveAssert((string)($managedCreate['payload']['message'] ?? '') === 'Role created', 'Managed role create message mismatch');
    $managedRolePublicId = (string)($managedCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($managedRolePublicId !== '', 'Managed role public_id is required');

    $update = liveRequest('PATCH', 'api/v1/roles/' . $managedRolePublicId, [
        'title' => 'Managed Role Updated ' . $suffix,
    ], $headers);
    liveAssert($update['status'] === 200, 'Managed role update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Role updated', 'Managed role update message mismatch');

    $delete = liveRequest('DELETE', 'api/v1/roles/' . $managedRolePublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Managed role delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Role deleted', 'Managed role delete message mismatch');

    $updateMissing = liveRequest('PATCH', 'api/v1/roles/rol_missing_' . $suffix, [
        'title' => 'Nope',
    ], $headers);
    liveAssert($updateMissing['status'] === 404, 'Missing role update must return 404');
    liveAssert((string)($updateMissing['payload']['message'] ?? '') === 'Failed to update role', 'Missing role update message mismatch');

    assertNoCyrillicRole($createValidation['payload']['errors'] ?? [], 'role.create.validation.errors');
    assertNoCyrillicRole($updateMissing['payload']['errors'] ?? [], 'role.update_missing.errors');

    liveRequest('PATCH', 'api/v1/users/' . $userPublicId, [
        'is_root' => 0,
    ], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);

    echo "[OK] advanced_role_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_role_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
