<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Bulk smoke project ' . randomSuffix(),
        'description' => 'bulk smoke',
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $tag = request('POST', '/api/v1/tags', [
        'code' => 'bulk_' . randomSuffix(),
        'title' => 'Bulk tag',
        'color' => '#10b981',
    ], $headers);
    assertTrue($tag['status'] === 201, 'Tag create status must be 201');
    $tagPublicId = (string)($tag['payload']['data']['tag']['public_id'] ?? '');
    assertTrue($tagPublicId !== '', 'Tag public_id required');

    $task1 = request('POST', '/api/v1/tasks', [
        'title' => 'Bulk task 1',
        'project_public_id' => $projectPublicId,
    ], $headers);
    $task2 = request('POST', '/api/v1/tasks', [
        'title' => 'Bulk task 2',
        'project_public_id' => $projectPublicId,
    ], $headers);

    assertTrue($task1['status'] === 201 && $task2['status'] === 201, 'Task create status must be 201');
    $task1PublicId = (string)($task1['payload']['data']['task']['public_id'] ?? '');
    $task2PublicId = (string)($task2['payload']['data']['task']['public_id'] ?? '');
    assertTrue($task1PublicId !== '' && $task2PublicId !== '', 'Task public_id required');

    $bulk = request('POST', '/api/v1/tasks/bulk', [
        'task_public_ids' => [$task1PublicId, $task2PublicId],
        'changes' => [
            'status' => 'done',
            'priority' => 'high',
            'add_tag_public_ids' => [$tagPublicId],
        ],
    ], $headers);
    assertTrue($bulk['status'] === 200, 'Task bulk status must be 200');
    assertTrue(($bulk['payload']['code'] ?? '') === 'TASK_BULK_UPDATED', 'Task bulk code mismatch');
    assertTrue((int)($bulk['payload']['data']['summary']['updated'] ?? 0) === 2, 'Task bulk updated count must be 2');

    $task1Get = request('GET', '/api/v1/tasks/' . $task1PublicId, [], $headers);
    $task2Get = request('GET', '/api/v1/tasks/' . $task2PublicId, [], $headers);
    assertTrue(($task1Get['payload']['data']['task']['status_code'] ?? '') === 'done', 'Task1 status must be done');
    assertTrue(($task2Get['payload']['data']['task']['status_code'] ?? '') === 'done', 'Task2 status must be done');
    assertTrue(($task1Get['payload']['data']['task']['priority_code'] ?? '') === 'high', 'Task1 priority must be high');
    assertTrue(($task2Get['payload']['data']['task']['priority_code'] ?? '') === 'high', 'Task2 priority must be high');

    $tags = request('GET', '/api/v1/tasks/' . $task1PublicId . '/tags', [], $headers);
    assertTrue($tags['status'] === 200, 'Task tags list status must be 200');
    assertTrue(count((array)($tags['payload']['data']['items'] ?? [])) >= 1, 'Task tags list must contain at least one tag');

    $alias = request('POST', '/api/v1/task/bulk/update', [
        'task_public_ids' => [$task1PublicId],
        'changes' => ['status' => 'in_progress'],
    ], $headers);
    assertTrue($alias['status'] === 200, 'Task bulk alias status must be 200');

    $unauthorized = request('POST', '/api/v1/tasks/bulk', [
        'task_public_ids' => [$task1PublicId],
        'changes' => ['status' => 'done'],
    ]);
    assertTrue($unauthorized['status'] === 401, 'Task bulk unauthorized status must be 401');

    echo "[OK] Task bulk actions smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
