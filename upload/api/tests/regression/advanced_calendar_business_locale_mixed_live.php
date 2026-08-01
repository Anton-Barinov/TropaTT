<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicCalendarBusiness(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicCalendarBusiness($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'cal_business_locale_' . $suffix,
        'title' => 'Calendar Business Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['settings.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'cal_business_locale_' . $suffix;
    $token = 'cal-business-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'CalendarBusinessLocale123!',
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
        'password' => 'CalendarBusinessLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $calendarCreate = liveRequest('POST', 'api/v1/calendar/business', [
        'title' => 'Business calendar ' . $suffix,
        'timezone' => 'Europe/Moscow',
    ], $headers);
    liveAssert($calendarCreate['status'] === 201, 'Business calendar create must return 201');
    liveAssert((string)($calendarCreate['payload']['message'] ?? '') === 'Business calendar created', 'Business calendar create message mismatch');
    $calendarPublicId = (string)($calendarCreate['payload']['data']['calendar']['public_id'] ?? '');
    liveAssert($calendarPublicId !== '', 'Business calendar public_id is required');

    $calendarList = liveRequest('GET', 'api/v1/calendar/business', ['limit' => 5], $headers);
    liveAssert($calendarList['status'] === 200, 'Business calendar list must return 200');
    liveAssert((string)($calendarList['payload']['message'] ?? '') === 'Business calendars list', 'Business calendar list message mismatch');
    assertNoCyrillicCalendarBusiness($calendarList['payload'], 'calendar.business.list.payload');

    $calendarGet = liveRequest('GET', 'api/v1/calendar/business/' . $calendarPublicId, [], $headers);
    liveAssert($calendarGet['status'] === 200, 'Business calendar get must return 200');
    liveAssert((string)($calendarGet['payload']['message'] ?? '') === 'Business calendar details', 'Business calendar get message mismatch');

    $calendarUpdate = liveRequest('PATCH', 'api/v1/calendar/business/' . $calendarPublicId, [
        'title' => 'Business calendar updated ' . $suffix,
    ], $headers);
    liveAssert($calendarUpdate['status'] === 200, 'Business calendar update must return 200');
    liveAssert((string)($calendarUpdate['payload']['message'] ?? '') === 'Business calendar updated', 'Business calendar update message mismatch');

    $holidayCreate = liveRequest('POST', 'api/v1/calendar/holidays', [
        'calendar_public_id' => $calendarPublicId,
        'holiday_date' => '2026-12-31',
        'title' => 'Holiday ' . $suffix,
    ], $headers);
    liveAssert($holidayCreate['status'] === 201, 'Holiday create must return 201');
    liveAssert((string)($holidayCreate['payload']['message'] ?? '') === 'Holiday created', 'Holiday create message mismatch');
    $holidayPublicId = (string)($holidayCreate['payload']['data']['holiday']['public_id'] ?? '');
    liveAssert($holidayPublicId !== '', 'Holiday public_id is required');

    $holidayGet = liveRequest('GET', 'api/v1/calendar/holidays/' . $holidayPublicId, [], $headers);
    liveAssert($holidayGet['status'] === 200, 'Holiday get must return 200');
    liveAssert((string)($holidayGet['payload']['message'] ?? '') === 'Holiday details', 'Holiday get message mismatch');

    $holidayUpdate = liveRequest('PATCH', 'api/v1/calendar/holidays/' . $holidayPublicId, [
        'title' => 'Holiday updated ' . $suffix,
    ], $headers);
    liveAssert($holidayUpdate['status'] === 200, 'Holiday update must return 200');
    liveAssert((string)($holidayUpdate['payload']['message'] ?? '') === 'Holiday updated', 'Holiday update message mismatch');

    $workingCreate = liveRequest('POST', 'api/v1/calendar/working-hours', [
        'calendar_public_id' => $calendarPublicId,
        'weekday' => 1,
        'start_time' => '09:00:00',
        'end_time' => '18:00:00',
    ], $headers);
    liveAssert($workingCreate['status'] === 201, 'Working hours create must return 201');
    liveAssert((string)($workingCreate['payload']['message'] ?? '') === 'Working hours created', 'Working hours create message mismatch');
    $workingPublicId = (string)($workingCreate['payload']['data']['working_hours']['public_id'] ?? '');
    liveAssert($workingPublicId !== '', 'Working hours public_id is required');

    $workingGet = liveRequest('GET', 'api/v1/calendar/working-hours/' . $workingPublicId, [], $headers);
    liveAssert($workingGet['status'] === 200, 'Working hours get must return 200');
    liveAssert((string)($workingGet['payload']['message'] ?? '') === 'Working hours details', 'Working hours get message mismatch');

    $workingUpdate = liveRequest('PATCH', 'api/v1/calendar/working-hours/' . $workingPublicId, [
        'weekday' => 2,
    ], $headers);
    liveAssert($workingUpdate['status'] === 200, 'Working hours update must return 200');
    liveAssert((string)($workingUpdate['payload']['message'] ?? '') === 'Working hours updated', 'Working hours update message mismatch');

    $validationTime = liveRequest('PATCH', 'api/v1/calendar/working-hours/' . $workingPublicId, [
        'start_time' => 'bad',
    ], $headers);
    liveAssert($validationTime['status'] === 422, 'Working hours invalid time must return 422');
    liveAssert((string)($validationTime['payload']['message'] ?? '') === 'Validation error', 'Working hours validation message mismatch');
    assertNoCyrillicCalendarBusiness($validationTime['payload']['errors'] ?? [], 'calendar.working_hours.validation.errors');

    $workingDelete = liveRequest('DELETE', 'api/v1/calendar/working-hours/' . $workingPublicId, [], $headers);
    liveAssert($workingDelete['status'] === 200, 'Working hours delete must return 200');
    liveAssert((string)($workingDelete['payload']['message'] ?? '') === 'Working hours deleted', 'Working hours delete message mismatch');

    $holidayDelete = liveRequest('DELETE', 'api/v1/calendar/holidays/' . $holidayPublicId, [], $headers);
    liveAssert($holidayDelete['status'] === 200, 'Holiday delete must return 200');
    liveAssert((string)($holidayDelete['payload']['message'] ?? '') === 'Holiday deleted', 'Holiday delete message mismatch');

    $calendarDelete = liveRequest('DELETE', 'api/v1/calendar/business/' . $calendarPublicId, [], $headers);
    liveAssert($calendarDelete['status'] === 200, 'Business calendar delete must return 200');
    liveAssert((string)($calendarDelete['payload']['message'] ?? '') === 'Business calendar deleted', 'Business calendar delete message mismatch');

    $calendarNotFound = liveRequest('GET', 'api/v1/calendar/business/' . $calendarPublicId, [], $headers);
    liveAssert($calendarNotFound['status'] === 404, 'Business calendar not found must return 404');
    liveAssert((string)($calendarNotFound['payload']['message'] ?? '') === 'Business calendar not found', 'Business calendar not found message mismatch');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_calendar_business_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_calendar_business_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
