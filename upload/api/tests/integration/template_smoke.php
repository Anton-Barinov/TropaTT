<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runTemplateSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $taskCreate = request('POST', '/api/v1/template/tasks', [
        'title' => 'Task Template ' . $suffix,
        'payload' => ['status' => 'new', 'priority' => 'normal', 'checklist' => ['A', 'B']],
        'is_active' => 1,
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task template create status must be 201');
    $taskTemplateId = (string)($taskCreate['payload']['data']['template']['public_id'] ?? '');
    assertTrue($taskTemplateId !== '', 'Task template public_id is required');

    $taskGet = request('GET', '/api/v1/template/tasks/' . $taskTemplateId, [], $headers);
    assertTrue($taskGet['status'] === 200, 'Task template get status must be 200');

    $taskUpdate = request('PATCH', '/api/v1/template/tasks/' . $taskTemplateId, [
        'title' => 'Task Template Updated ' . $suffix,
        'is_active' => 0,
    ], $headers);
    assertTrue($taskUpdate['status'] === 200, 'Task template update status must be 200');
    assertTrue(($taskUpdate['payload']['data']['template']['is_active'] ?? true) === false, 'Task template is_active must be false');

    $taskListAlias = request('GET', '/api/v1/template/task/list?limit=5&search=' . rawurlencode($suffix), [], $headers);
    assertTrue($taskListAlias['status'] === 200, 'Task template alias list status must be 200');

    $projectCreate = request('POST', '/api/v1/template/projects', [
        'title' => 'Project Template ' . $suffix,
        'payload' => ['status' => 'active', 'priority' => 'normal'],
    ], $headers);
    assertTrue($projectCreate['status'] === 201, 'Project template create status must be 201');
    $projectTemplateId = (string)($projectCreate['payload']['data']['template']['public_id'] ?? '');
    assertTrue($projectTemplateId !== '', 'Project template public_id is required');

    $projectListAlias = request('GET', '/api/v1/template/project/list?limit=5&search=' . rawurlencode($suffix), [], $headers);
    assertTrue($projectListAlias['status'] === 200, 'Project template alias list status must be 200');

    $projectDelete = request('DELETE', '/api/v1/template/projects/' . $projectTemplateId, [], $headers);
    assertTrue($projectDelete['status'] === 200, 'Project template delete status must be 200');

    $taskDelete = request('DELETE', '/api/v1/template/tasks/' . $taskTemplateId, [], $headers);
    assertTrue($taskDelete['status'] === 200, 'Task template delete status must be 200');

    $unauthorized = request('GET', '/api/v1/template/tasks');
    assertTrue($unauthorized['status'] === 401, 'Templates endpoint without token must return 401');
}

runTemplateSmoke();
echo "[OK] template_smoke\n";
