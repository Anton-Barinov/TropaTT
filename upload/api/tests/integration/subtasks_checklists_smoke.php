<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Subtask Checklist Project ' . randomSuffix(),
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id is required');

    $task = request('POST', '/api/v1/tasks', [
        'title' => 'Subtask Checklist Task ' . randomSuffix(),
        'project_public_id' => $projectPublicId,
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    assertTrue($task['status'] === 201, 'Task create must be 201');
    $taskPublicId = (string)($task['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $subtaskCreate = request('POST', '/api/v1/tasks/' . $taskPublicId . '/subtasks', [
        'title' => 'Subtask ' . randomSuffix(),
        'status' => 'new',
        'sort_order' => 10,
    ], $headers);
    assertTrue($subtaskCreate['status'] === 201, 'Subtask create must be 201');
    $subtaskPublicId = (string)($subtaskCreate['payload']['data']['subtask']['public_id'] ?? '');
    assertTrue($subtaskPublicId !== '', 'Subtask public_id is required');

    $subtaskList = request('GET', '/api/v1/tasks/' . $taskPublicId . '/subtasks', [], $headers);
    assertTrue($subtaskList['status'] === 200, 'Subtask list must be 200');
    assertTrue(is_array($subtaskList['payload']['data']['items'] ?? null), 'Subtask list items must be array');

    $subtaskUpdate = request('PATCH', '/api/v1/subtasks/' . $subtaskPublicId, [
        'title' => 'Subtask updated',
        'status' => 'done',
    ], $headers);
    assertTrue($subtaskUpdate['status'] === 200, 'Subtask update must be 200');

    $subtaskGet = request('GET', '/api/v1/subtasks/' . $subtaskPublicId, [], $headers);
    assertTrue($subtaskGet['status'] === 200, 'Subtask get must be 200');

    $checklistCreate = request('POST', '/api/v1/tasks/' . $taskPublicId . '/checklists', [
        'title' => 'Checklist ' . randomSuffix(),
    ], $headers);
    assertTrue($checklistCreate['status'] === 201, 'Checklist create must be 201');
    $checklistPublicId = (string)($checklistCreate['payload']['data']['checklist']['public_id'] ?? '');
    assertTrue($checklistPublicId !== '', 'Checklist public_id is required');

    $checklistList = request('GET', '/api/v1/tasks/' . $taskPublicId . '/checklists', [], $headers);
    assertTrue($checklistList['status'] === 200, 'Checklist list must be 200');

    $itemCreate = request('POST', '/api/v1/checklists/' . $checklistPublicId . '/items', [
        'title' => 'Checklist item ' . randomSuffix(),
        'is_done' => false,
        'sort_order' => 1,
    ], $headers);
    assertTrue($itemCreate['status'] === 201, 'Checklist item create must be 201');
    $itemPublicId = (string)($itemCreate['payload']['data']['item']['public_id'] ?? '');
    assertTrue($itemPublicId !== '', 'Checklist item public_id is required');

    $itemList = request('GET', '/api/v1/checklists/' . $checklistPublicId . '/items', [], $headers);
    assertTrue($itemList['status'] === 200, 'Checklist item list must be 200');

    $itemUpdate = request('PATCH', '/api/v1/checklist-items/' . $itemPublicId, [
        'is_done' => true,
    ], $headers);
    assertTrue($itemUpdate['status'] === 200, 'Checklist item update must be 200');

    $itemGet = request('GET', '/api/v1/checklist-items/' . $itemPublicId, [], $headers);
    assertTrue($itemGet['status'] === 200, 'Checklist item get must be 200');

    $itemDelete = request('DELETE', '/api/v1/checklist-items/' . $itemPublicId, [], $headers);
    assertTrue($itemDelete['status'] === 200, 'Checklist item delete must be 200');

    $checklistDelete = request('DELETE', '/api/v1/checklists/' . $checklistPublicId, [], $headers);
    assertTrue($checklistDelete['status'] === 200, 'Checklist delete must be 200');

    $subtaskDelete = request('DELETE', '/api/v1/subtasks/' . $subtaskPublicId, [], $headers);
    assertTrue($subtaskDelete['status'] === 200, 'Subtask delete must be 200');

    request('POST', '/api/v1/auth/logout', [], $headers);

    echo "Subtasks/checklists smoke: OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Subtasks/checklists smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
