<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $tasks = request('GET', '/api/v1/tasks?limit=1', [], $headers);
    assertTrue($tasks['status'] === 200, 'Tasks list status must be 200');
    $taskPublicId = (string)($tasks['payload']['data']['items'][0]['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id required for worklog smoke');

    $create = request('POST', '/api/v1/worklogs', [
        'task_public_id' => $taskPublicId,
        'minutes_spent' => 25,
        'note' => 'integration smoke worklog',
        'logged_at' => gmdate('Y-m-d H:i:s'),
    ], $headers);
    assertTrue($create['status'] === 201, 'Worklog create status must be 201');
    assertTrue(($create['payload']['code'] ?? '') === 'WORKLOG_CREATED', 'Worklog create code mismatch');
    $worklogPublicId = (string)($create['payload']['data']['worklog']['public_id'] ?? '');
    assertTrue($worklogPublicId !== '', 'Worklog public_id required');

    $list = request('GET', '/api/v1/worklogs?limit=5', [], $headers);
    assertTrue($list['status'] === 200, 'Worklog list status must be 200');
    assertTrue(($list['payload']['code'] ?? '') === 'WORKLOG_LIST', 'Worklog list code mismatch');

    $dashboard = request('GET', '/api/v1/dashboard/summary', [], $headers);
    assertTrue($dashboard['status'] === 200, 'Dashboard summary status must be 200');
    assertTrue(($dashboard['payload']['code'] ?? '') === 'DASHBOARD_SUMMARY', 'Dashboard summary code mismatch');

    $analyticsSummary = request('GET', '/api/v1/analytics/summary', [], $headers);
    assertTrue($analyticsSummary['status'] === 200, 'Analytics summary status must be 200');
    assertTrue(($analyticsSummary['payload']['code'] ?? '') === 'ANALYTICS_SUMMARY', 'Analytics summary code mismatch');

    $analyticsProjects = request('GET', '/api/v1/analytics/projects?limit=5', [], $headers);
    assertTrue($analyticsProjects['status'] === 200, 'Analytics projects status must be 200');
    assertTrue(($analyticsProjects['payload']['code'] ?? '') === 'ANALYTICS_PROJECTS', 'Analytics projects code mismatch');

    $analyticsUsers = request('GET', '/api/v1/analytics/users?limit=5', [], $headers);
    assertTrue($analyticsUsers['status'] === 200, 'Analytics users status must be 200');
    assertTrue(($analyticsUsers['payload']['code'] ?? '') === 'ANALYTICS_USERS', 'Analytics users code mismatch');

    $delete = request('DELETE', '/api/v1/worklogs/' . $worklogPublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Worklog delete status must be 200');
    assertTrue(($delete['payload']['code'] ?? '') === 'WORKLOG_DELETED', 'Worklog delete code mismatch');

    echo "[OK] Worklog + Dashboard + Analytics smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
