<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicWorklog(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicWorklog($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'worklog_locale_' . $suffix,
        'title' => 'Worklog Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'worklog_locale_' . $suffix;
    $token = 'worklog-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'WorklogLocale123!',
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
        'password' => 'WorklogLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $create = liveRequest('POST', 'api/v1/worklogs', [
        'minutes_spent' => 30,
        'note' => 'Locale worklog ' . $suffix,
        'logged_at' => '2026-04-20 10:00:00',
    ], $headers);
    liveAssert($create['status'] === 201, 'Worklog create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Worklog created', 'Worklog create message mismatch');
    $worklogPublicId = (string)($create['payload']['data']['worklog']['public_id'] ?? '');
    liveAssert($worklogPublicId !== '', 'Worklog public_id is required');

    $list = liveRequest('GET', 'api/v1/worklogs', ['limit' => 5], $headers);
    liveAssert($list['status'] === 200, 'Worklog list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Worklogs list', 'Worklog list message mismatch');
    assertNoCyrillicWorklog($list['payload'], 'worklog.list.payload');

    $get = liveRequest('GET', 'api/v1/worklogs/' . $worklogPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Worklog get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Worklog details', 'Worklog get message mismatch');
    assertNoCyrillicWorklog($get['payload'], 'worklog.get.payload');

    $update = liveRequest('PATCH', 'api/v1/worklogs/' . $worklogPublicId, [
        'minutes_spent' => 45,
    ], $headers);
    liveAssert($update['status'] === 200, 'Worklog update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Worklog updated', 'Worklog update message mismatch');

    $validation = liveRequest('POST', 'api/v1/worklogs', [
        'minutes_spent' => 0,
        'logged_at' => 'bad-date',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Worklog validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Worklog validation message mismatch');
    assertNoCyrillicWorklog($validation['payload']['errors'] ?? [], 'worklog.validation.errors');

    $notFound = liveRequest('GET', 'api/v1/worklogs/wlg_missing_' . $suffix, [], $headers);
    liveAssert($notFound['status'] === 404, 'Worklog not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Worklog not found', 'Worklog not found message mismatch');

    $delete = liveRequest('DELETE', 'api/v1/worklogs/' . $worklogPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Worklog delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Worklog deleted', 'Worklog delete message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_worklog_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_worklog_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
