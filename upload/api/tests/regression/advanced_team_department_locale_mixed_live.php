<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicTeamDepartment(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicTeamDepartment($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'td_locale_' . $suffix,
        'title' => 'Team/Dept Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['team.manage', 'department.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'td_locale_' . $suffix;
    $token = 'td-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'TdLocale123!',
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
        'password' => 'TdLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $teamList = liveRequest('GET', 'api/v1/teams', [], $headers);
    liveAssert($teamList['status'] === 200, 'Team list must return 200');
    liveAssert((string)($teamList['payload']['message'] ?? '') === 'Team list', 'Team list message mismatch');

    $teamValidation = liveRequest('POST', 'api/v1/teams', [], $headers);
    liveAssert($teamValidation['status'] === 422, 'Team validation must return 422');
    liveAssert((string)($teamValidation['payload']['message'] ?? '') === 'Validation error', 'Team validation message mismatch');
    assertNoCyrillicTeamDepartment($teamValidation['payload']['errors'] ?? [], 'team.validation.errors');

    $teamCreate = liveRequest('POST', 'api/v1/teams', [
        'title' => 'Team ' . $suffix,
    ], $headers);
    liveAssert($teamCreate['status'] === 201, 'Team create must return 201');
    liveAssert((string)($teamCreate['payload']['message'] ?? '') === 'Team created', 'Team create message mismatch');
    $teamPublicId = (string)($teamCreate['payload']['data']['team']['public_id'] ?? '');
    liveAssert($teamPublicId !== '', 'Team public_id is required');

    $teamGet = liveRequest('GET', 'api/v1/teams/' . $teamPublicId, [], $headers);
    liveAssert($teamGet['status'] === 200, 'Team get must return 200');
    liveAssert((string)($teamGet['payload']['message'] ?? '') === 'Team details', 'Team detail message mismatch');

    $teamUpdate = liveRequest('PATCH', 'api/v1/teams/' . $teamPublicId, [
        'title' => 'Team Updated ' . $suffix,
    ], $headers);
    liveAssert($teamUpdate['status'] === 200, 'Team update must return 200');
    liveAssert((string)($teamUpdate['payload']['message'] ?? '') === 'Team updated', 'Team update message mismatch');

    $teamNotFound = liveRequest('GET', 'api/v1/teams/tem_missing_' . $suffix, [], $headers);
    liveAssert($teamNotFound['status'] === 404, 'Team not found must return 404');
    liveAssert((string)($teamNotFound['payload']['message'] ?? '') === 'Team not found', 'Team not found message mismatch');

    $departmentList = liveRequest('GET', 'api/v1/departments', [], $headers);
    liveAssert($departmentList['status'] === 200, 'Department list must return 200');
    liveAssert((string)($departmentList['payload']['message'] ?? '') === 'Department list', 'Department list message mismatch');

    $departmentValidation = liveRequest('POST', 'api/v1/departments', [], $headers);
    liveAssert($departmentValidation['status'] === 422, 'Department validation must return 422');
    liveAssert((string)($departmentValidation['payload']['message'] ?? '') === 'Validation error', 'Department validation message mismatch');
    assertNoCyrillicTeamDepartment($departmentValidation['payload']['errors'] ?? [], 'department.validation.errors');

    $departmentCreate = liveRequest('POST', 'api/v1/departments', [
        'title' => 'Department ' . $suffix,
    ], $headers);
    liveAssert($departmentCreate['status'] === 201, 'Department create must return 201');
    liveAssert((string)($departmentCreate['payload']['message'] ?? '') === 'Department created', 'Department create message mismatch');
    $departmentPublicId = (string)($departmentCreate['payload']['data']['department']['public_id'] ?? '');
    liveAssert($departmentPublicId !== '', 'Department public_id is required');

    $departmentGet = liveRequest('GET', 'api/v1/departments/' . $departmentPublicId, [], $headers);
    liveAssert($departmentGet['status'] === 200, 'Department get must return 200');
    liveAssert((string)($departmentGet['payload']['message'] ?? '') === 'Department details', 'Department detail message mismatch');

    $departmentUpdate = liveRequest('PATCH', 'api/v1/departments/' . $departmentPublicId, [
        'title' => 'Department Updated ' . $suffix,
    ], $headers);
    liveAssert($departmentUpdate['status'] === 200, 'Department update must return 200');
    liveAssert((string)($departmentUpdate['payload']['message'] ?? '') === 'Department updated', 'Department update message mismatch');

    $departmentNotFound = liveRequest('GET', 'api/v1/departments/dep_missing_' . $suffix, [], $headers);
    liveAssert($departmentNotFound['status'] === 404, 'Department not found must return 404');
    liveAssert((string)($departmentNotFound['payload']['message'] ?? '') === 'Department not found', 'Department not found message mismatch');

    liveRequest('DELETE', 'api/v1/teams/' . $teamPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/departments/' . $departmentPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_team_department_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_team_department_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
