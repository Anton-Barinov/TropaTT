<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runIdempotencyConflictRegressionLive(): void
{
    $auth = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $auth['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $projectIdemKey = 'live-idem-project-' . $suffix;
    $projectPayload = ['title' => 'Live Idem Project ' . $suffix];
    $project1 = liveRequest('POST', 'api/v1/projects', $projectPayload, array_merge($headers, ['X-Idempotency-Key' => $projectIdemKey]));
    $project2 = liveRequest('POST', 'api/v1/projects', $projectPayload, array_merge($headers, ['X-Idempotency-Key' => $projectIdemKey]));
    liveAssert($project1['status'] === 201 && $project2['status'] === 201, 'Project idempotency calls must return 201');
    $projectPublicId1 = (string)($project1['payload']['data']['project']['public_id'] ?? '');
    $projectPublicId2 = (string)($project2['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId1 !== '' && $projectPublicId1 === $projectPublicId2, 'Project idempotency must return same public_id');
    liveAssert((bool)($project2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Project replay meta must be true');

    $taskIdemKey = 'live-idem-task-' . $suffix;
    $taskPayload = [
        'project_public_id' => $projectPublicId1,
        'title' => 'Live Idem Task ' . $suffix,
        'description' => 'Idempotency check',
    ];
    $task1 = liveRequest('POST', 'api/v1/tasks', $taskPayload, array_merge($headers, ['X-Idempotency-Key' => $taskIdemKey]));
    $task2 = liveRequest('POST', 'api/v1/tasks', $taskPayload, array_merge($headers, ['X-Idempotency-Key' => $taskIdemKey]));
    liveAssert($task1['status'] === 201 && $task2['status'] === 201, 'Task idempotency calls must return 201');
    $taskPublicId = (string)($task1['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '' && $taskPublicId === (string)($task2['payload']['data']['task']['public_id'] ?? ''), 'Task idempotency must return same public_id');
    liveAssert((bool)($task2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Task replay meta must be true');

    $taskGet = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveAssert($taskGet['status'] === 200, 'Task get must return 200');
    $rowVersion = (int)($taskGet['payload']['data']['task']['row_version'] ?? 0);
    liveAssert($rowVersion > 0, 'Task row_version must be > 0');

    $updateOk = liveRequest('PATCH', 'api/v1/tasks/' . $taskPublicId, [
        'description' => 'row version update 1',
        'row_version' => $rowVersion,
    ], $headers);
    liveAssert($updateOk['status'] === 200, 'Task update with current row_version must return 200');

    $updateConflict = liveRequest('PATCH', 'api/v1/tasks/' . $taskPublicId, [
        'description' => 'row version stale update',
        'row_version' => $rowVersion,
    ], $headers);
    liveAssert($updateConflict['status'] === 409, 'Task stale row_version update must return 409');
    liveAssert((string)($updateConflict['payload']['code'] ?? '') === 'ROW_VERSION_CONFLICT', 'Conflict code must be ROW_VERSION_CONFLICT');
}

runIdempotencyConflictRegressionLive();
echo "[OK] idempotency_conflict_regression_live\n";
