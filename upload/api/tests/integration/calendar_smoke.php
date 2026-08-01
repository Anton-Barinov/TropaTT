<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $eventDate = (new DateTimeImmutable('today +14 days'))->format('Y-m-d');
    $eventMonthStart = (new DateTimeImmutable($eventDate))->modify('first day of this month')->format('Y-m-d') . ' 00:00:00';
    $eventMonthEnd = (new DateTimeImmutable($eventDate))->modify('last day of this month')->format('Y-m-d') . ' 23:59:59';
    $pastEventDate = (new DateTimeImmutable('yesterday'))->format('Y-m-d');
    $eventDescription = 'Calendar event description smoke';

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'Calendar Smoke Project ' . randomSuffix(),
        'description' => 'Calendar smoke project',
    ], $headers);
    assertTrue($projectCreate['status'] === 201, 'Project create for calendar event must be 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id for calendar smoke is required');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'Calendar Smoke Task ' . randomSuffix(),
        'description' => 'Calendar smoke task',
        'project_public_id' => $projectPublicId,
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create for calendar event must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id for calendar smoke is required');

    $eventCreate = request('POST', '/api/v1/calendar/events', [
        'title' => 'Calendar Smoke Event ' . randomSuffix(),
        'description' => $eventDescription,
        'starts_at' => $eventDate . ' 11:00:00',
        'ends_at' => $eventDate . ' 11:30:00',
        'task_public_id' => $taskPublicId,
    ], $headers);
    assertTrue($eventCreate['status'] === 201, 'Calendar event create must be 201');
    $eventPublicId = (string)($eventCreate['payload']['data']['event']['public_id'] ?? '');
    assertTrue($eventPublicId !== '', 'Calendar event public_id is required');
    assertTrue((string)($eventCreate['payload']['data']['event']['description'] ?? '') === $eventDescription, 'Calendar event create must persist description');
    assertTrue((string)($eventCreate['payload']['data']['event']['task_public_id'] ?? '') === $taskPublicId, 'Calendar event create must link task_public_id');

    $eventList = request('GET', '/api/v1/calendar/events?from=' . rawurlencode($eventDate . ' 00:00:00') . '&to=' . rawurlencode($eventDate . ' 23:59:59'), [], $headers);
    assertTrue($eventList['status'] === 200, 'Calendar event list must be 200');
    assertTrue(($eventList['payload']['code'] ?? '') === 'CALENDAR_EVENT_LIST', 'Calendar event list code must be CALENDAR_EVENT_LIST');
    $listedEvent = $eventList['payload']['data']['items'][0] ?? [];
    assertTrue((string)($listedEvent['description'] ?? '') === $eventDescription, 'Calendar event list must include description');
    assertTrue((string)($listedEvent['task_public_id'] ?? '') === $taskPublicId, 'Calendar event list must include linked task_public_id');

    $eventGet = request('GET', '/api/v1/calendar/events/' . $eventPublicId, [], $headers);
    assertTrue($eventGet['status'] === 200, 'Calendar event get must be 200');
    assertTrue((string)($eventGet['payload']['data']['event']['description'] ?? '') === $eventDescription, 'Calendar event get must include description');
    assertTrue((string)($eventGet['payload']['data']['event']['task_public_id'] ?? '') === $taskPublicId, 'Calendar event get must include linked task_public_id');

    $eventUpdate = request('PATCH', '/api/v1/calendar/events/' . $eventPublicId, [
        'title' => 'Calendar Smoke Event Updated',
    ], $headers);
    assertTrue($eventUpdate['status'] === 200, 'Calendar event update must be 200');

    $pastCreate = request('POST', '/api/v1/calendar/events', [
        'title' => 'Calendar Past Event ' . randomSuffix(),
        'starts_at' => $pastEventDate . ' 11:00:00',
        'ends_at' => $pastEventDate . ' 11:30:00',
    ], $headers);
    assertTrue($pastCreate['status'] === 422, 'Calendar event create in the past must be rejected');
    assertTrue(isset($pastCreate['payload']['errors']['starts_at']), 'Calendar past validation must point to starts_at');

    $myDay = request('GET', '/api/v1/calendar/my-day?date=' . $eventDate, [], $headers);
    assertTrue($myDay['status'] === 200, 'Calendar my-day must be 200');
    assertTrue(($myDay['payload']['code'] ?? '') === 'CALENDAR_MY_DAY', 'Calendar my-day code must be CALENDAR_MY_DAY');

    $myWeek = request('GET', '/api/v1/calendar/my-week?date=' . $eventDate, [], $headers);
    assertTrue($myWeek['status'] === 200, 'Calendar my-week must be 200');
    assertTrue(($myWeek['payload']['code'] ?? '') === 'CALENDAR_MY_WEEK', 'Calendar my-week code must be CALENDAR_MY_WEEK');

    $myMonth = request('GET', '/api/v1/calendar/my-month?date=' . $eventDate, [], $headers);
    assertTrue($myMonth['status'] === 200, 'Calendar my-month must be 200');
    assertTrue(($myMonth['payload']['code'] ?? '') === 'CALENDAR_MY_MONTH', 'Calendar my-month code must be CALENDAR_MY_MONTH');
    assertTrue((((array)($myMonth['payload']['data']['range'] ?? []))['from'] ?? '') === $eventMonthStart, 'Calendar my-month start must match month start');
    assertTrue((((array)($myMonth['payload']['data']['range'] ?? []))['to'] ?? '') === $eventMonthEnd, 'Calendar my-month end must match month end');

    $aliasDay = request('GET', '/api/v1/calendar/day?date=' . $eventDate, [], $headers);
    assertTrue($aliasDay['status'] === 200, 'Calendar alias day must be 200');

    $aliasWeek = request('GET', '/api/v1/calendar/week?date=' . $eventDate, [], $headers);
    assertTrue($aliasWeek['status'] === 200, 'Calendar alias week must be 200');

    $aliasMonth = request('GET', '/api/v1/calendar/month?date=' . $eventDate, [], $headers);
    assertTrue($aliasMonth['status'] === 200, 'Calendar alias month must be 200');

    $eventDelete = request('DELETE', '/api/v1/calendar/events/' . $eventPublicId, [], $headers);
    assertTrue($eventDelete['status'] === 200, 'Calendar event delete must be 200');

    $eventGet404 = request('GET', '/api/v1/calendar/events/' . $eventPublicId, [], $headers);
    assertTrue($eventGet404['status'] === 404, 'Calendar event get after delete must be 404');

    $taskDelete = request('DELETE', '/api/v1/tasks/' . $taskPublicId, [], $headers);
    assertTrue(in_array($taskDelete['status'], [200, 204], true), 'Calendar linked task cleanup must return 200/204');

    $projectDelete = request('DELETE', '/api/v1/projects/' . $projectPublicId, [], $headers);
    assertTrue(in_array($projectDelete['status'], [200, 204], true), 'Calendar linked project cleanup must return 200/204');

    request('POST', '/api/v1/auth/logout', [], $headers);

    echo "Calendar smoke: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Calendar smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
