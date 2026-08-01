<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);

    $projectRoot = dirname(__DIR__, 3);
    $adminUsersTpl = file_get_contents($projectRoot . '/web/view/template/page/admin_users.php');
    assertTrue(is_string($adminUsersTpl) && str_contains($adminUsersTpl, 'id="adminUsersSavedViewSelect"'), 'admin-users saved view select missing');

    $tasksTpl = file_get_contents($projectRoot . '/web/view/template/page/tasks.php');
    assertTrue(is_string($tasksTpl) && str_contains($tasksTpl, 'id="tasksBulkStatusSelect"'), 'tasks bulk status control missing');
    assertTrue(is_string($tasksTpl) && str_contains($tasksTpl, 'id="tasksBulkPrioritySelect"'), 'tasks bulk priority control missing');
    assertTrue(is_string($tasksTpl) && str_contains($tasksTpl, 'id="tasksBulkHelpBtn"'), 'tasks bulk help button missing');

    $bindings = file_get_contents($projectRoot . '/web/assets/js/page-api-bindings.js');
    assertTrue(is_string($bindings) && str_contains($bindings, 'entity_type: \'admin_user\''), 'admin-users saved view bindings missing');
    assertTrue(is_string($bindings) && str_contains($bindings, 'tasksBulkStatusSelect'), 'tasks bulk status js binding missing');
    assertTrue(is_string($bindings) && str_contains($bindings, 'bulkBar.dataset.boundHotkeys'), 'tasks bulk keyboard shortcut binding missing');

    $adminViewCreate = request('POST', '/api/v1/views', [
        'entity_type' => 'admin_user',
        'title' => 'Admin users view ' . randomSuffix(),
        'filters' => [
            'search' => 'root',
            'is_active' => '1',
        ],
    ], $rootHeaders);
    assertTrue($adminViewCreate['status'] === 201, 'Admin users view create must return 201');
    $adminViewId = (string)($adminViewCreate['payload']['data']['view']['public_id'] ?? '');
    assertTrue($adminViewId !== '', 'Admin users view public_id is required');

    $adminViewsList = request('GET', '/api/v1/views?entity_type=admin_user&limit=20', [], $rootHeaders);
    assertTrue($adminViewsList['status'] === 200, 'Admin users views list must return 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'Bulk smoke task ' . randomSuffix(),
        'description' => 'bulk-status-priority-smoke',
        'status' => 'new',
        'priority' => 'normal',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskId !== '', 'Created task public_id is required');

    $bulk = request('POST', '/api/v1/tasks/bulk', [
        'task_public_ids' => [$taskId, 'task_missing_' . randomSuffix()],
        'changes' => [
            'priority' => 'high',
        ],
    ], $rootHeaders);
    assertTrue($bulk['status'] === 200, 'Tasks bulk update must return 200');
    $summary = (array)($bulk['payload']['data']['summary'] ?? []);
    assertTrue((int)($summary['updated'] ?? 0) >= 1, 'Bulk summary must report at least one updated task');
    assertTrue((int)($summary['skipped'] ?? 0) >= 1, 'Bulk summary must report skipped tasks for missing ids');

    if ($adminViewId !== '') {
        request('DELETE', '/api/v1/views/' . $adminViewId, [], $rootHeaders);
    }
    if ($taskId !== '') {
        request('DELETE', '/api/v1/tasks/' . $taskId, [], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] web_admin_users_saved_views_and_tasks_bulk_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_admin_users_saved_views_and_tasks_bulk_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
