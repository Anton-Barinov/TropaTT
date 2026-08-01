<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicPermission(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicPermission($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'perm_locale_' . $suffix,
        'title' => 'Permission Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['role.view', 'role.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'perm_locale_' . $suffix;
    $token = 'perm-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'PermissionLocale123!',
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
        'password' => 'PermissionLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $permissionList = liveRequest('GET', 'api/v1/permissions', [], $headers);
    liveAssert($permissionList['status'] === 200, 'Permission list must return 200');
    liveAssert((string)($permissionList['payload']['message'] ?? '') === 'Permission registry', 'Permission list message mismatch');

    $rolePermissionList = liveRequest('GET', 'api/v1/roles/' . $rolePublicId . '/permissions', [], $headers);
    liveAssert($rolePermissionList['status'] === 200, 'Role permission list must return 200');
    liveAssert((string)($rolePermissionList['payload']['message'] ?? '') === 'Role permissions', 'Role permissions list message mismatch');

    $validation = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => 'invalid',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Role permissions validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Role permissions validation message mismatch');
    assertNoCyrillicPermission($validation['payload']['errors'] ?? [], 'permission.validation.errors');

    $setUpdate = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['role.view'],
    ], $headers);
    liveAssert($setUpdate['status'] === 403, 'Role permissions update by non-root must return 403');
    liveAssert((string)($setUpdate['payload']['message'] ?? '') === 'Failed to update role permissions', 'Role permissions update failed message mismatch');

    $notFound = liveRequest('GET', 'api/v1/roles/rol_missing_' . $suffix . '/permissions', [], $headers);
    liveAssert($notFound['status'] === 404, 'Role permission list not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Role not found', 'Role not found message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_permission_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_permission_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
