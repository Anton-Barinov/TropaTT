<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));
    $eventDate = (new DateTimeImmutable('today +21 days'))->format('Y-m-d');

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'calendar_alias_locale_' . $suffix,
        'title' => 'Calendar Alias Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'calendar_alias_' . $suffix;
    $token = 'calendar-alias-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CalendarAlias123!',
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
        'password' => 'CalendarAlias123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $eventCreate = liveRequest('POST', 'api/v1/calendar/event/create', [
        'title' => 'Alias event ' . $suffix,
        'starts_at' => $eventDate . ' 10:00:00',
        'ends_at' => $eventDate . ' 11:00:00',
    ], $headers);
    liveAssert($eventCreate['status'] === 201, 'Calendar event alias create must return 201');
    liveAssert((string)($eventCreate['payload']['message'] ?? '') === 'Calendar event created', 'Calendar event alias create message mismatch');
    $eventPublicId = (string)($eventCreate['payload']['data']['event']['public_id'] ?? '');
    liveAssert($eventPublicId !== '', 'Event public_id is required');

    $eventList = liveRequest('GET', 'api/v1/calendar/event/list', [], $headers);
    liveAssert($eventList['status'] === 200, 'Calendar event alias list must return 200');
    liveAssert((string)($eventList['payload']['message'] ?? '') === 'Calendar events list', 'Calendar event alias list message mismatch');

    $eventGet = liveRequest('GET', 'api/v1/calendar/event/get/' . $eventPublicId, [], $headers);
    liveAssert($eventGet['status'] === 200, 'Calendar event alias get must return 200');
    liveAssert((string)($eventGet['payload']['message'] ?? '') === 'Calendar event details', 'Calendar event alias get message mismatch');

    $eventUpdate = liveRequest('PATCH', 'api/v1/calendar/event/update/' . $eventPublicId, [
        'title' => 'Alias event updated ' . $suffix,
    ], $headers);
    liveAssert($eventUpdate['status'] === 200, 'Calendar event alias update must return 200');
    liveAssert((string)($eventUpdate['payload']['message'] ?? '') === 'Calendar event updated', 'Calendar event alias update message mismatch');

    $day = liveRequest('GET', 'api/v1/calendar/day', [], $headers);
    liveAssert($day['status'] === 200, 'Calendar day alias must return 200');
    liveAssert((string)($day['payload']['message'] ?? '') === 'Day aggregation', 'Calendar day alias message mismatch');

    $week = liveRequest('GET', 'api/v1/calendar/week', [], $headers);
    liveAssert($week['status'] === 200, 'Calendar week alias must return 200');
    liveAssert((string)($week['payload']['message'] ?? '') === 'Week aggregation', 'Calendar week alias message mismatch');

    $month = liveRequest('GET', 'api/v1/calendar/month', [], $headers);
    liveAssert($month['status'] === 200, 'Calendar month alias must return 200');
    liveAssert((string)($month['payload']['message'] ?? '') === 'Month aggregation', 'Calendar month alias message mismatch');

    $eventDelete = liveRequest('DELETE', 'api/v1/calendar/event/delete/' . $eventPublicId, [], $headers);
    liveAssert($eventDelete['status'] === 200, 'Calendar event alias delete must return 200');
    liveAssert((string)($eventDelete['payload']['message'] ?? '') === 'Calendar event deleted', 'Calendar event alias delete message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_calendar_alias_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_calendar_alias_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
