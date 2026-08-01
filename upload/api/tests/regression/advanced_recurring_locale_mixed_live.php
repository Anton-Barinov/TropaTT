<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicRecurring(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicRecurring($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'rec_locale_' . $suffix,
        'title' => 'Recurring Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'rec_locale_' . $suffix;
    $token = 'rec-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'RecLocale123!',
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
        'password' => 'RecLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $list = liveRequest('GET', 'api/v1/recurring', [], $headers);
    liveAssert($list['status'] === 200, 'Recurring list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Recurring rules list', 'Recurring list message mismatch');

    $validation = liveRequest('POST', 'api/v1/recurring', [
        'entity_type' => 'wrong',
        'entity_public_id' => 'tsk_rec_' . $suffix,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Recurring validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Recurring validation message mismatch');
    assertNoCyrillicRecurring($validation['payload']['errors'] ?? [], 'recurring.validation.errors');

    $create = liveRequest('POST', 'api/v1/recurring', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_rec_' . $suffix,
        'rrule' => 'FREQ=DAILY;INTERVAL=1',
        'is_active' => 1,
    ], $headers);
    liveAssert($create['status'] === 201, 'Recurring create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Recurring rule created', 'Recurring create message mismatch');
    $rulePublicId = (string)($create['payload']['data']['rule']['public_id'] ?? '');
    liveAssert($rulePublicId !== '', 'Recurring rule public_id is required');

    $get = liveRequest('GET', 'api/v1/recurring/' . $rulePublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Recurring get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Recurring rule details', 'Recurring detail message mismatch');

    $pause = liveRequest('POST', 'api/v1/recurring/' . $rulePublicId . '/pause', [], $headers);
    liveAssert($pause['status'] === 200, 'Recurring pause must return 200');
    liveAssert((string)($pause['payload']['message'] ?? '') === 'Recurring rule paused', 'Recurring pause message mismatch');

    $resume = liveRequest('POST', 'api/v1/recurring/' . $rulePublicId . '/resume', [], $headers);
    liveAssert($resume['status'] === 200, 'Recurring resume must return 200');
    liveAssert((string)($resume['payload']['message'] ?? '') === 'Recurring rule resumed', 'Recurring resume message mismatch');

    $update = liveRequest('PATCH', 'api/v1/recurring/' . $rulePublicId, [
        'rrule' => 'FREQ=WEEKLY;INTERVAL=1',
    ], $headers);
    liveAssert($update['status'] === 200, 'Recurring update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Recurring rule updated', 'Recurring update message mismatch');

    $notFound = liveRequest('GET', 'api/v1/recurring/rec_missing_' . $suffix, [], $headers);
    liveAssert($notFound['status'] === 404, 'Recurring not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Recurring rule not found', 'Recurring not found message mismatch');

    liveRequest('DELETE', 'api/v1/recurring/' . $rulePublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_recurring_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_recurring_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
