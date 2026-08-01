<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $statuses = request('GET', '/api/v1/statuses?scope=task', [], $headers);
    assertTrue($statuses['status'] === 200, 'Statuses list must be 200');
    assertTrue(($statuses['payload']['code'] ?? '') === 'STATUS_LIST', 'Statuses code must be STATUS_LIST');

    $priorities = request('GET', '/api/v1/priorities', [], $headers);
    assertTrue($priorities['status'] === 200, 'Priorities list must be 200');

    $statusCode = 'smoke_sts_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $statusCreate = request('POST', '/api/v1/statuses', [
        'scope' => 'task',
        'code' => $statusCode,
        'title' => 'Smoke Status',
        'color' => '#123456',
        'sort_order' => 999,
        'is_active' => 1,
    ], $headers);
    assertTrue($statusCreate['status'] === 201, 'Status create must be 201');
    $statusPublicId = (string)($statusCreate['payload']['data']['status']['public_id'] ?? '');
    assertTrue($statusPublicId !== '', 'Status public_id is required');

    $statusUpdate = request('PATCH', '/api/v1/statuses/' . $statusPublicId, [
        'title' => 'Smoke Status Updated',
    ], $headers);
    assertTrue($statusUpdate['status'] === 200, 'Status update must be 200');

    $priorityCode = 'smoke_pri_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $priorityCreate = request('POST', '/api/v1/priorities', [
        'code' => $priorityCode,
        'title' => 'Smoke Priority',
        'weight' => 777,
        'color' => '#654321',
    ], $headers);
    assertTrue($priorityCreate['status'] === 201, 'Priority create must be 201');
    $priorityPublicId = (string)($priorityCreate['payload']['data']['priority']['public_id'] ?? '');
    assertTrue($priorityPublicId !== '', 'Priority public_id is required');

    $priorityUpdate = request('PATCH', '/api/v1/priorities/' . $priorityPublicId, [
        'title' => 'Smoke Priority Updated',
    ], $headers);
    assertTrue($priorityUpdate['status'] === 200, 'Priority update must be 200');

    $tagCode = 'smoke_tag_' . substr(bin2hex(random_bytes(4)), 0, 6);
    $tagCreate = request('POST', '/api/v1/tags', [
        'code' => $tagCode,
        'title' => 'Smoke Tag',
        'color' => '#abcdef',
    ], $headers);
    assertTrue($tagCreate['status'] === 201, 'Tag create must be 201');
    $tagPublicId = (string)($tagCreate['payload']['data']['tag']['public_id'] ?? '');
    assertTrue($tagPublicId !== '', 'Tag public_id is required');

    $project = request('POST', '/api/v1/projects', ['title' => 'Dictionary Filter Project ' . randomSuffix()], $headers);
    assertTrue($project['status'] === 201, 'Project create must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');

    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Dictionary Filter Task ' . randomSuffix(),
        'project_public_id' => $projectPublicId,
        'status' => 'new',
        'priority' => 'high',
    ], $headers);
    assertTrue($task['status'] === 201, 'Task create must be 201');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? '');

    $attach = request('POST', '/api/v1/tasks/' . $taskPublicId . '/tags/' . $tagPublicId, [], $headers);
    assertTrue($attach['status'] === 200, 'Task tag attach must be 200');

    $taskTagList = request('GET', '/api/v1/tasks/' . $taskPublicId . '/tags', [], $headers);
    assertTrue($taskTagList['status'] === 200, 'Task tag list must be 200');

    $filterByPriority = request('GET', '/api/v1/tasks?priority=high&project_public_id=' . $projectPublicId, [], $headers);
    assertTrue($filterByPriority['status'] === 200, 'Task list by priority must be 200');
    assertTrue(($filterByPriority['payload']['code'] ?? '') === 'TASK_LIST', 'Task list code must be TASK_LIST');

    $filterByTag = request('GET', '/api/v1/tasks?tag_public_id=' . $tagPublicId . '&project_public_id=' . $projectPublicId, [], $headers);
    assertTrue($filterByTag['status'] === 200, 'Task list by tag must be 200');
    $items = (array)($filterByTag['payload']['data']['items'] ?? []);
    assertTrue(count($items) >= 1, 'Task list by tag must contain at least one task');

    $detach = request('DELETE', '/api/v1/tasks/' . $taskPublicId . '/tags/' . $tagPublicId, [], $headers);
    assertTrue($detach['status'] === 200, 'Task tag detach must be 200');

    request('DELETE', '/api/v1/tags/' . $tagPublicId, [], $headers);
    request('DELETE', '/api/v1/priorities/' . $priorityPublicId, [], $headers);
    request('DELETE', '/api/v1/statuses/' . $statusPublicId, [], $headers);

    request('POST', '/api/v1/auth/logout', [], $headers);

    echo "Dictionaries/task-filters smoke: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Dictionaries/task-filters smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
