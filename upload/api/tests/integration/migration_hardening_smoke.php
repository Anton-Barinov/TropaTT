<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $unauthorizedDryRun = request('GET', '/internal/migration/dry-run');
    assertTrue($unauthorizedDryRun['status'] === 401, 'Dry-run unauthorized status must be 401');

    $unauthorizedRollback = request('GET', '/internal/migration/rollback-check');
    assertTrue($unauthorizedRollback['status'] === 401, 'Rollback-check unauthorized status must be 401');

    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $status = request('GET', '/internal/migration/status', [], $headers);
    assertTrue($status['status'] === 200, 'Migration status must be 200');
    assertTrue(($status['payload']['code'] ?? '') === 'MIGRATION_STATUS', 'Migration status code mismatch');

    $dryRun = request('GET', '/internal/migration/dry-run', [], $headers);
    assertTrue($dryRun['status'] === 200, 'Migration dry-run status must be 200');
    assertTrue(($dryRun['payload']['code'] ?? '') === 'MIGRATION_DRY_RUN', 'Migration dry-run code mismatch');
    $dryRunData = (array)($dryRun['payload']['data']['dry_run'] ?? []);
    assertTrue(array_key_exists('would_execute', $dryRunData), 'Dry-run must include would_execute');
    assertTrue(array_key_exists('count', $dryRunData), 'Dry-run must include count');

    $rollbackCheck = request('GET', '/internal/migration/rollback-check', [], $headers);
    assertTrue($rollbackCheck['status'] === 200, 'Migration rollback-check status must be 200');
    assertTrue(($rollbackCheck['payload']['code'] ?? '') === 'MIGRATION_ROLLBACK_CHECK', 'Migration rollback-check code mismatch');
    $rollbackData = (array)($rollbackCheck['payload']['data']['rollback_check'] ?? []);
    $checks = (array)($rollbackData['checks'] ?? []);
    assertTrue(array_key_exists('rollback_possible', $rollbackData), 'Rollback-check must include rollback_possible');
    assertTrue(array_key_exists('reverse_order_safe', $checks), 'Rollback-check must include checks.reverse_order_safe');
    assertTrue(array_key_exists('unknown_applied', $checks), 'Rollback-check must include checks.unknown_applied');
    assertTrue(array_key_exists('all_applied_have_down', $checks), 'Rollback-check must include checks.all_applied_have_down');

    $up = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($up['status'] === 200, 'Migration up must be 200');
    assertTrue(($up['payload']['code'] ?? '') === 'MIGRATION_UP_DONE', 'Migration up code mismatch');

    echo "[OK] Migration hardening smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
