<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');
    $headers = authHeaders($token);

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'Web tasks smoke project ' . randomSuffix(),
        'status' => 'active',
        'priority' => 'normal',
    ], $headers);
    assertTrue($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Web tasks smoke task ' . randomSuffix(),
        'description' => 'Task created by web tasks smoke',
        'status' => 'new',
        'priority' => 'normal',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskList = request('GET', '/api/v1/tasks?project_public_id=' . rawurlencode($projectPublicId) . '&limit=20', [], $headers);
    assertTrue($taskList['status'] === 200, 'Task list must return 200');
    $items = (array)($taskList['payload']['data']['items'] ?? []);
    $foundInList = false;
    foreach ($items as $item) {
        if ((string)($item['public_id'] ?? '') === $taskPublicId) {
            $foundInList = true;
            break;
        }
    }
    assertTrue($foundInList, 'Created task must be present in task list');

    $taskGet = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $headers);
    assertTrue($taskGet['status'] === 200, 'Task detail endpoint must return 200');
    $taskFromDetail = (array)($taskGet['payload']['data']['task'] ?? []);
    assertTrue((string)($taskFromDetail['public_id'] ?? '') === $taskPublicId, 'Task detail must return created task');

    $webIndex = dirname(__DIR__, 2) . '/../web/index.php';
    assertTrue(is_file($webIndex), 'Web index.php must exist');

    $_GET = ['route' => 'task-detail', 'task_public_id' => $taskPublicId];
    $_POST = [];
    $_FILES = [];
    $_COOKIE = [];
    $_SERVER = [
        'REQUEST_METHOD' => 'GET',
        'REQUEST_URI' => '/index.php?route=task-detail&task_public_id=' . rawurlencode($taskPublicId),
        'SCRIPT_NAME' => '/index.php',
        'REMOTE_ADDR' => '127.0.0.1',
        'HTTP_USER_AGENT' => 'crm-web-tasks-smoke/1.0',
    ];

    ob_start();
    require $webIndex;
    $html = (string)ob_get_clean();

    assertTrue($html !== '', 'Rendered task-detail html must not be empty');
    assertTrue(str_contains($html, 'data-page="tasks"'), 'Task detail page must expose data-page="tasks"');
    assertTrue(str_contains($html, 'id="taskStatusSelect"'), 'Task detail must render task status selector');
    assertTrue(str_contains($html, 'id="commentsList"'), 'Task detail must render comments container');
    assertTrue(str_contains($html, 'id="taskAiSummaryCard"'), 'Task detail must render AI summary card');

    fwrite(STDOUT, "[OK] web_tasks_list_create_detail_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_tasks_list_create_detail_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
