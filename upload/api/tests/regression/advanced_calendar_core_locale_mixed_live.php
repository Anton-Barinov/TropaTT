<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicCalendarCore(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicCalendarCore($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));
    $eventDate = (new DateTimeImmutable('today +21 days'))->format('Y-m-d');

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'cal_core_locale_' . $suffix,
        'title' => 'Calendar Core Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'cal_core_locale_' . $suffix;
    $token = 'cal-core-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CalendarCoreLocale123!',
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
        'password' => 'CalendarCoreLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $create = liveRequest('POST', 'api/v1/calendar/events', [
        'title' => 'Calendar core event ' . $suffix,
        'starts_at' => $eventDate . ' 10:00:00',
        'ends_at' => $eventDate . ' 11:00:00',
    ], $headers);
    liveAssert($create['status'] === 201, 'Calendar event create must return 201');
    liveAssert((string)($create['payload']['message'] ?? '') === 'Calendar event created', 'Calendar event create message mismatch');
    $eventPublicId = (string)($create['payload']['data']['event']['public_id'] ?? '');
    liveAssert($eventPublicId !== '', 'Calendar event public_id is required');

    $list = liveRequest('GET', 'api/v1/calendar/events', [
        'from' => $eventDate . ' 00:00:00',
        'to' => $eventDate . ' 23:59:59',
    ], $headers);
    liveAssert($list['status'] === 200, 'Calendar event list must return 200');
    liveAssert((string)($list['payload']['message'] ?? '') === 'Calendar events list', 'Calendar event list message mismatch');
    assertNoCyrillicCalendarCore($list['payload'], 'calendar.events.list.payload');

    $get = liveRequest('GET', 'api/v1/calendar/events/' . $eventPublicId, [], $headers);
    liveAssert($get['status'] === 200, 'Calendar event get must return 200');
    liveAssert((string)($get['payload']['message'] ?? '') === 'Calendar event details', 'Calendar event details message mismatch');

    $update = liveRequest('PATCH', 'api/v1/calendar/events/' . $eventPublicId, [
        'title' => 'Calendar core event updated ' . $suffix,
    ], $headers);
    liveAssert($update['status'] === 200, 'Calendar event update must return 200');
    liveAssert((string)($update['payload']['message'] ?? '') === 'Calendar event updated', 'Calendar event update message mismatch');

    $myDay = liveRequest('GET', 'api/v1/calendar/my-day', ['date' => $eventDate], $headers);
    liveAssert($myDay['status'] === 200, 'Calendar my-day must return 200');
    liveAssert((string)($myDay['payload']['message'] ?? '') === 'Day aggregation', 'Calendar my-day message mismatch');

    $myWeek = liveRequest('GET', 'api/v1/calendar/my-week', ['date' => $eventDate], $headers);
    liveAssert($myWeek['status'] === 200, 'Calendar my-week must return 200');
    liveAssert((string)($myWeek['payload']['message'] ?? '') === 'Week aggregation', 'Calendar my-week message mismatch');

    $myMonth = liveRequest('GET', 'api/v1/calendar/my-month', ['date' => $eventDate], $headers);
    liveAssert($myMonth['status'] === 200, 'Calendar my-month must return 200');
    liveAssert((string)($myMonth['payload']['message'] ?? '') === 'Month aggregation', 'Calendar my-month message mismatch');

    $validation = liveRequest('POST', 'api/v1/calendar/events', [
        'title' => str_repeat('A', 260),
        'starts_at' => 'bad-date',
    ], $headers);
    liveAssert($validation['status'] === 422, 'Calendar event validation must return 422');
    liveAssert((string)($validation['payload']['message'] ?? '') === 'Validation error', 'Calendar validation message mismatch');
    assertNoCyrillicCalendarCore($validation['payload']['errors'] ?? [], 'calendar.events.validation.errors');

    $delete = liveRequest('DELETE', 'api/v1/calendar/events/' . $eventPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Calendar event delete must return 200');
    liveAssert((string)($delete['payload']['message'] ?? '') === 'Calendar event deleted', 'Calendar event delete message mismatch');

    $notFound = liveRequest('GET', 'api/v1/calendar/events/' . $eventPublicId, [], $headers);
    liveAssert($notFound['status'] === 404, 'Calendar event not found must return 404');
    liveAssert((string)($notFound['payload']['message'] ?? '') === 'Calendar event not found', 'Calendar event not found message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_calendar_core_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_calendar_core_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
