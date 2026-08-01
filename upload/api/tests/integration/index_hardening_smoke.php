<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $migrationUp = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($migrationUp['status'] === 200, 'Migration up must be 200');

    $status = request('GET', '/internal/migration/status', [], $headers);
    assertTrue($status['status'] === 200, 'Migration status must be 200');
    $applied = (array)($status['payload']['data']['migration_status']['applied'] ?? []);
    assertTrue(in_array('20260419_000007_index_repair', $applied, true), 'Index repair migration must be applied');

    $tasks = request('GET', '/api/v1/tasks?limit=5&updated_since=2026-01-01 00:00:00&pagination_mode=cursor', [], $headers);
    assertTrue($tasks['status'] === 200, 'Tasks list must be 200');
    assertTrue(($tasks['payload']['code'] ?? '') === 'TASK_LIST', 'Tasks list code mismatch');

    $projects = request('GET', '/api/v1/projects?limit=5&updated_since=2026-01-01 00:00:00&pagination_mode=cursor', [], $headers);
    assertTrue($projects['status'] === 200, 'Projects list must be 200');
    assertTrue(($projects['payload']['code'] ?? '') === 'PROJECT_LIST', 'Projects list code mismatch');

    $requestLogs = request('GET', '/api/v1/logs/request?limit=5&method=GET', [], $headers);
    assertTrue($requestLogs['status'] === 200, 'Request logs list must be 200');
    assertTrue(($requestLogs['payload']['code'] ?? '') === 'REQUEST_LOG_LIST', 'Request logs list code mismatch');

    $auditLogs = request('GET', '/api/v1/logs/audit?limit=5&entity_type=project', [], $headers);
    assertTrue($auditLogs['status'] === 200, 'Audit logs list must be 200');
    assertTrue(($auditLogs['payload']['code'] ?? '') === 'AUDIT_LOG_LIST', 'Audit logs list code mismatch');

    $activity = request('GET', '/api/v1/activity/feed?limit=5&channel=request', [], $headers);
    assertTrue($activity['status'] === 200, 'Activity feed must be 200');
    assertTrue(($activity['payload']['code'] ?? '') === 'ACTIVITY_FEED', 'Activity feed code mismatch');

    echo "[OK] Index hardening smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
