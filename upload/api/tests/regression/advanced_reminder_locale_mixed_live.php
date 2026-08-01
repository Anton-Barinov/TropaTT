<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicReminder(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicReminder($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'rem_locale_' . $suffix,
        'title' => 'Reminder Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $login = 'rem_locale_' . $suffix;
    $token = 'rem-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'ReminderLocale123!',
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
        'password' => 'ReminderLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $create = liveRequest('POST', 'api/v1/reminders', [
        'remind_at' => '2026-04-20 09:30:00',
        'status' => 'new',
    ], $headers);
    liveAssert($create['status'] === 201, 'Reminder create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Reminder created', 'Reminder create message mismatch');
    $reminderPublicId = (string)($create['payload']['data']['reminder']['public_id'] ?? '');
    liveAssert($reminderPublicId !== '', 'Reminder public_id is required');

    $list = liveRequest('GET', 'api/v1/reminders', [], $headers);
    liveAssert($list['status'] === 200, 'Reminder list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Reminders list', 'Reminder list message mismatch');
    assertNoCyrillicReminder($list['payload'], 'reminder.list.payload');

    $get = liveRequest('GET', 'api/v1/reminders/' . $reminderPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Reminder get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Reminder details', 'Reminder get message mismatch');
    assertNoCyrillicReminder($get['payload'], 'reminder.get.payload');

    $update = liveRequest('PATCH', 'api/v1/reminders/' . $reminderPublicId, [
        'status' => 'pending',
    ], $headers);
    liveAssert($update['status'] === 200, 'Reminder update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Reminder updated', 'Reminder update message mismatch');

    $validation = liveRequest('POST', 'api/v1/reminders', [
        'status' => 'oops',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Reminder validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Reminder validation message mismatch');
    assertNoCyrillicReminder($validation['payload']['errors'] ?? [], 'reminder.validation.errors');

    $notFound = liveRequest('GET', 'api/v1/reminders/rmn_missing_' . $suffix, [], $headers);
    liveAssert($notFound['status'] === 404, 'Reminder not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Reminder not found', 'Reminder not found message mismatch');

    $delete = liveRequest('DELETE', 'api/v1/reminders/' . $reminderPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Reminder delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Reminder deleted', 'Reminder delete message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_reminder_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_reminder_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
