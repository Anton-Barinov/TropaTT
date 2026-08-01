<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Kanban smoke project ' . randomSuffix(),
        'description' => 'kanban smoke',
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Kanban smoke task',
        'project_public_id' => $projectPublicId,
        'status' => 'new',
    ], $headers);
    assertTrue($task['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id required');

    $board = request('GET', '/api/v1/tasks/board?project_public_id=' . $projectPublicId, [], $headers);
    assertTrue($board['status'] === 200, 'Task board status must be 200');
    assertTrue(($board['payload']['code'] ?? '') === 'TASK_BOARD', 'Task board code mismatch');
    assertTrue(($board['payload']['data']['board']['mode'] ?? '') === 'columns', 'Task board mode must be columns');

    $taskBeforeMove = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $headers);
    $rowVersion = (int)($taskBeforeMove['payload']['data']['task']['row_version'] ?? 0);
    assertTrue($rowVersion > 0, 'Task row_version must be > 0');

    $move = request('POST', '/api/v1/tasks/' . $taskPublicId . '/move', [
        'to_status' => 'in_progress',
        'row_version' => $rowVersion,
    ], $headers);
    assertTrue($move['status'] === 200, 'Task move status must be 200');
    assertTrue(($move['payload']['code'] ?? '') === 'TASK_MOVED', 'Task move code mismatch');
    assertTrue(($move['payload']['data']['task']['status_code'] ?? '') === 'in_progress', 'Task moved status mismatch');

    $moveConflict = request('POST', '/api/v1/tasks/' . $taskPublicId . '/move', [
        'to_status' => 'done',
        'row_version' => $rowVersion,
    ], $headers);
    assertTrue($moveConflict['status'] === 409, 'Task move conflict status must be 409');
    assertTrue(($moveConflict['payload']['code'] ?? '') === 'ROW_VERSION_CONFLICT', 'Task move conflict code mismatch');

    $aliasBoard = request('GET', '/api/v1/task/board?project_public_id=' . $projectPublicId, [], $headers);
    assertTrue($aliasBoard['status'] === 200, 'Task board alias status must be 200');

    $aliasMove = request('POST', '/api/v1/task/move/' . $taskPublicId, [
        'to_status' => 'done',
    ], $headers);
    assertTrue($aliasMove['status'] === 200, 'Task move alias status must be 200');
    assertTrue(($aliasMove['payload']['data']['task']['status_code'] ?? '') === 'done', 'Task alias moved status mismatch');

    $unauthorized = request('GET', '/api/v1/tasks/board');
    assertTrue($unauthorized['status'] === 401, 'Task board unauthorized status must be 401');

    echo "[OK] Kanban board smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
