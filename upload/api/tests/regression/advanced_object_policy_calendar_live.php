<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'obj_cal_' . $suffix,
        'title' => 'Object Calendar ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['project.manage', 'task.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $ownerLogin = 'obj_cal_owner_' . $suffix;
    $ownerTokenFactor = 'obj-cal-owner-token-' . $suffix;
    $ownerCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $ownerLogin,
        'password' => 'ObjCalOwner123!',
        'token' => $ownerTokenFactor,
        'email' => $ownerLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($ownerCreate['status'] === 201, 'Owner user create must return 201');
    $ownerUserPublicId = (string)($ownerCreate['payload']['data']['user']['public_id'] ?? '');

    $viewerLogin = 'obj_cal_viewer_' . $suffix;
    $viewerTokenFactor = 'obj-cal-viewer-token-' . $suffix;
    $viewerCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $viewerLogin,
        'password' => 'ObjCalViewer123!',
        'token' => $viewerTokenFactor,
        'email' => $viewerLogin . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($viewerCreate['status'] === 201, 'Viewer user create must return 201');
    $viewerUserPublicId = (string)($viewerCreate['payload']['data']['user']['public_id'] ?? '');

    $ownerLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $ownerLogin,
        'password' => 'ObjCalOwner123!',
        'token' => $ownerTokenFactor,
    ]);
    liveAssert($ownerLoginResp['status'] === 200, 'Owner login must return 200');
    $ownerToken = (string)($ownerLoginResp['payload']['data']['access_token'] ?? '');
    $ownerHeaders = ['Authorization' => 'Bearer ' . $ownerToken];

    $viewerLoginResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $viewerLogin,
        'password' => 'ObjCalViewer123!',
        'token' => $viewerTokenFactor,
    ]);
    liveAssert($viewerLoginResp['status'] === 200, 'Viewer login must return 200');
    $viewerToken = (string)($viewerLoginResp['payload']['data']['access_token'] ?? '');
    $viewerHeaders = ['Authorization' => 'Bearer ' . $viewerToken];

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'ObjCal Project ' . $suffix,
        'description' => 'Project for object policy calendar',
    ], $ownerHeaders);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'ObjCal Task ' . $suffix,
        'description' => 'Task for object policy calendar',
        'status' => 'new',
        'priority' => 'normal',
    ], $ownerHeaders);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');

    $eventCreate = liveRequest('POST', 'api/v1/calendar/events', [
        'title' => 'ObjCal Event ' . $suffix,
        'starts_at' => gmdate('Y-m-d H:i:s'),
        'ends_at' => gmdate('Y-m-d H:i:s', time() + 3600),
        'project_public_id' => $projectPublicId,
        'task_public_id' => $taskPublicId,
    ], $ownerHeaders);
    liveAssert($eventCreate['status'] === 201, 'Event create must return 201');
    $eventPublicId = (string)($eventCreate['payload']['data']['event']['public_id'] ?? '');

    $viewerGetEvent = liveRequest('GET', 'api/v1/calendar/events/' . $eventPublicId, [], $viewerHeaders);
    liveAssert($viewerGetEvent['status'] === 404, 'Non-owner get event must return 404');

    $viewerDeleteEvent = liveRequest('DELETE', 'api/v1/calendar/events/' . $eventPublicId, [], $viewerHeaders);
    liveAssert($viewerDeleteEvent['status'] === 404, 'Non-owner delete event must return 404');

    $viewerCreateForeignProject = liveRequest('POST', 'api/v1/calendar/events', [
        'title' => 'Viewer forbidden project',
        'starts_at' => gmdate('Y-m-d H:i:s'),
        'project_public_id' => $projectPublicId,
    ], $viewerHeaders);
    liveAssert($viewerCreateForeignProject['status'] === 404, 'Non-owner create event with foreign project must return 404');
    liveAssert((string)($viewerCreateForeignProject['payload']['code'] ?? '') === 'PROJECT_NOT_FOUND', 'Non-owner foreign project code mismatch');

    $viewerCreateForeignTask = liveRequest('POST', 'api/v1/calendar/events', [
        'title' => 'Viewer forbidden task',
        'starts_at' => gmdate('Y-m-d H:i:s'),
        'task_public_id' => $taskPublicId,
    ], $viewerHeaders);
    liveAssert($viewerCreateForeignTask['status'] === 404, 'Non-owner create event with foreign task must return 404');
    liveAssert((string)($viewerCreateForeignTask['payload']['code'] ?? '') === 'TASK_NOT_FOUND', 'Non-owner foreign task code mismatch');

    $viewerOwnEventCreate = liveRequest('POST', 'api/v1/calendar/events', [
        'title' => 'Viewer own event',
        'starts_at' => gmdate('Y-m-d H:i:s'),
    ], $viewerHeaders);
    liveAssert($viewerOwnEventCreate['status'] === 201, 'Viewer own event create must return 201');
    $viewerEventPublicId = (string)($viewerOwnEventCreate['payload']['data']['event']['public_id'] ?? '');

    $viewerUpdateOwnToForeign = liveRequest('PATCH', 'api/v1/calendar/events/' . $viewerEventPublicId, [
        'project_public_id' => $projectPublicId,
    ], $viewerHeaders);
    liveAssert($viewerUpdateOwnToForeign['status'] === 404, 'Non-owner update own event to foreign project must return 404');
    liveAssert((string)($viewerUpdateOwnToForeign['payload']['code'] ?? '') === 'PROJECT_NOT_FOUND', 'Non-owner update foreign project code mismatch');

    $viewerDay = liveRequest('GET', 'api/v1/calendar/my-day', [], $viewerHeaders);
    liveAssert(
        $viewerDay['status'] === 200,
        'Viewer my-day must return 200, got ' . $viewerDay['status'] . ' (' . (string)($viewerDay['payload']['code'] ?? 'no_code') . ')'
    );
    $viewerEvents = $viewerDay['payload']['data']['events'] ?? [];
    liveAssert(is_array($viewerEvents), 'Viewer my-day events must be array');
    foreach ($viewerEvents as $event) {
        liveAssert((string)($event['public_id'] ?? '') !== $eventPublicId, 'Viewer my-day must not include owner event');
    }

    $rootEventGet = liveRequest('GET', 'api/v1/calendar/events/' . $eventPublicId, [], $rootHeaders);
    liveAssert($rootEventGet['status'] === 200, 'Root must access owner event');

    liveRequest('DELETE', 'api/v1/calendar/events/' . $viewerEventPublicId, [], $viewerHeaders);
    liveRequest('DELETE', 'api/v1/calendar/events/' . $eventPublicId, [], $ownerHeaders);
    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $ownerHeaders);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $ownerHeaders);

    liveRequest('DELETE', 'api/v1/users/' . $ownerUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $viewerUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_object_policy_calendar_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_object_policy_calendar_live: ' . $e->getMessage() . "\n");
    exit(1);
}
