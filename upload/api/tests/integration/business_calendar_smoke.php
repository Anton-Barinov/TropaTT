<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runBusinessCalendarSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $calendarCreate = request('POST', '/api/v1/calendar/business', [
        'title' => 'Business Calendar ' . $suffix,
        'timezone' => 'Europe/Moscow',
    ], $headers);
    assertTrue($calendarCreate['status'] === 201, 'Business calendar create must be 201');
    $calendarPublicId = (string)($calendarCreate['payload']['data']['calendar']['public_id'] ?? '');
    assertTrue($calendarPublicId !== '', 'Business calendar public_id is required');

    $calendarGet = request('GET', '/api/v1/calendar/business/' . $calendarPublicId, [], $headers);
    assertTrue($calendarGet['status'] === 200, 'Business calendar get must be 200');

    $holidayCreate = request('POST', '/api/v1/calendar/holidays', [
        'calendar_public_id' => $calendarPublicId,
        'holiday_date' => '2026-12-31',
        'title' => 'Smoke Holiday ' . $suffix,
    ], $headers);
    assertTrue($holidayCreate['status'] === 201, 'Holiday create must be 201');
    $holidayPublicId = (string)($holidayCreate['payload']['data']['holiday']['public_id'] ?? '');
    assertTrue($holidayPublicId !== '', 'Holiday public_id is required');

    $holidayList = request('GET', '/api/v1/calendar/holidays?calendar_public_id=' . urlencode($calendarPublicId), [], $headers);
    assertTrue($holidayList['status'] === 200, 'Holiday list must be 200');

    $workingCreate = request('POST', '/api/v1/calendar/working-hours', [
        'calendar_public_id' => $calendarPublicId,
        'weekday' => 1,
        'start_time' => '09:00',
        'end_time' => '18:00',
    ], $headers);
    assertTrue($workingCreate['status'] === 201, 'Working hours create must be 201');
    $workingPublicId = (string)($workingCreate['payload']['data']['working_hours']['public_id'] ?? '');
    assertTrue($workingPublicId !== '', 'Working hours public_id is required');

    $workingList = request('GET', '/api/v1/calendar/working-hours?calendar_public_id=' . urlencode($calendarPublicId), [], $headers);
    assertTrue($workingList['status'] === 200, 'Working hours list must be 200');

    $workingDelete = request('DELETE', '/api/v1/calendar/working-hours/' . $workingPublicId, [], $headers);
    assertTrue($workingDelete['status'] === 200, 'Working hours delete must be 200');

    $holidayDelete = request('DELETE', '/api/v1/calendar/holidays/' . $holidayPublicId, [], $headers);
    assertTrue($holidayDelete['status'] === 200, 'Holiday delete must be 200');

    $calendarDelete = request('DELETE', '/api/v1/calendar/business/' . $calendarPublicId, [], $headers);
    assertTrue($calendarDelete['status'] === 200, 'Business calendar delete must be 200');

    $unauthorized = request('GET', '/api/v1/calendar/business');
    assertTrue($unauthorized['status'] === 401, 'Business calendar list without token must be 401');
}

runBusinessCalendarSmoke();
echo "[OK] business_calendar_smoke\n";
