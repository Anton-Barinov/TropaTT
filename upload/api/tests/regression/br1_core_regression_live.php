<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runBr1CoreRegressionLive(): void
{
    $base = liveRequest('GET', '');
    liveAssert($base['status'] === 404, 'Base route must return 404 ROUTE_NOT_FOUND');
    liveAssert((string)($base['payload']['code'] ?? '') === 'ROUTE_NOT_FOUND', 'Base route code must be ROUTE_NOT_FOUND');

    $install = liveRequest('GET', 'install/status');
    liveAssert($install['status'] === 200, 'install/status must return 200');

    $health = liveRequest('GET', 'api/v1/health/status');
    liveAssert($health['status'] === 200, 'health/status must return 200');

    $auth = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $auth['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Regression Project ' . $suffix,
        'description' => 'Regression baseline',
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Regression Task ' . $suffix,
        'description' => 'Regression task',
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $commentCreate = liveRequest('POST', 'api/v1/tasks/' . $taskPublicId . '/comments', [
        'body' => 'Regression comment ' . $suffix,
    ], $headers);
    liveAssert($commentCreate['status'] === 201, 'Comment create must return 201');

    $taskGet = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveAssert($taskGet['status'] === 200, 'Task get must return 200');
    liveAssert((string)($taskGet['payload']['code'] ?? '') === 'TASK_DETAIL', 'Task get code must be TASK_DETAIL');

    $taskUpdate = liveRequest('PATCH', 'api/v1/tasks/' . $taskPublicId, [
        'title' => 'Regression Task Updated ' . $suffix,
    ], $headers);
    liveAssert($taskUpdate['status'] === 200, 'Task update must return 200');

    $taskComments = liveRequest('GET', 'api/v1/tasks/' . $taskPublicId . '/comments', [], $headers);
    liveAssert($taskComments['status'] === 200, 'Task comments list must return 200');

    $unauthorized = liveRequest('GET', 'api/v1/projects');
    liveAssert($unauthorized['status'] === 401, 'Protected projects route without token must return 401');
}

runBr1CoreRegressionLive();
echo "[OK] br1_core_regression_live\n";
