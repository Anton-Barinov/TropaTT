<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'BR1 Project ' . randomSuffix(),
        'description' => 'Integration project',
    ], $headers);
    assertTrue($projectCreate['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id is required');

    $projectGet = request('GET', '/api/v1/projects/' . $projectPublicId, [], $headers);
    assertTrue($projectGet['status'] === 200, 'Project get status must be 200');

    $projectList = request('GET', '/api/v1/projects', [], $headers);
    assertTrue($projectList['status'] === 200, 'Project list status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'BR1 Task ' . randomSuffix(),
        'description' => 'Integration task',
        'project_public_id' => $projectPublicId,
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskGet = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $headers);
    assertTrue($taskGet['status'] === 200, 'Task get status must be 200');

    $taskPatch = request('PATCH', '/api/v1/tasks/' . $taskPublicId, [
        'status' => 'in_progress',
        'title' => 'BR1 Task Updated ' . randomSuffix(),
        'description' => 'Updated description',
    ], $headers);
    assertTrue($taskPatch['status'] === 200, 'Task patch status must be 200');

    $taskList = request('GET', '/api/v1/tasks?project_public_id=' . rawurlencode($projectPublicId), [], $headers);
    assertTrue($taskList['status'] === 200, 'Task list status must be 200');

    $commentCreate = request('POST', '/api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => 'Integration comment ' . randomSuffix(),
    ], $headers);
    assertTrue($commentCreate['status'] === 201, 'Comment create status must be 201');

    $commentList = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comments', [], $headers);
    assertTrue($commentList['status'] === 200, 'Comment list status must be 200');
    $commentItems = $commentList['payload']['data']['items'] ?? [];
    assertTrue(is_array($commentItems) && count($commentItems) > 0, 'Comment list must contain created comment');

    $fileCreate = request('POST', '/api/v1/files', [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'name' => 'br1_payload.txt',
        'mime_type' => 'text/plain',
        'content_base64' => base64_encode('BR1 file payload'),
    ], $headers);
    assertTrue($fileCreate['status'] === 201, 'File create status must be 201');
    $filePublicId = (string)($fileCreate['payload']['data']['file']['public_id'] ?? '');
    assertTrue($filePublicId !== '', 'File public_id is required');

    $fileGet = request('GET', '/api/v1/files/' . $filePublicId, [], $headers);
    assertTrue($fileGet['status'] === 200, 'File get status must be 200');

    $fileDelete = request('DELETE', '/api/v1/files/' . $filePublicId, [], $headers);
    assertTrue($fileDelete['status'] === 200, 'File delete status must be 200');

    $logout = request('POST', '/api/v1/auth/logout', [], $headers);
    assertTrue($logout['status'] === 200, 'Logout status must be 200');

    echo "BR-1 smoke: OK\n";
    echo "project_public_id={$projectPublicId}\n";
    echo "task_public_id={$taskPublicId}\n";
    echo "file_public_id={$filePublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "BR-1 smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
