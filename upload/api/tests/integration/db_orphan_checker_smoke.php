<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    ensureTestRuntimeReady();

    $script = dirname(__DIR__, 2) . '/scripts/db_orphan_checker.php';
    $cmd = 'php ' . escapeshellarg($script) . ' --json';
    $output = [];
    $code = 1;
    exec($cmd, $output, $code);

    assertTrue($code === 0, 'db_orphan_checker.php must exit with code 0');
    $raw = trim(implode("\n", $output));
    assertTrue($raw !== '', 'db_orphan_checker output must not be empty');

    $json = json_decode($raw, true);
    assertTrue(is_array($json), 'db_orphan_checker output must be valid JSON');
    $summary = is_array($json['summary'] ?? null) ? $json['summary'] : [];
    assertTrue(array_key_exists('executed_checks', $summary), 'summary.executed_checks is required');
    assertTrue(array_key_exists('total_orphans', $summary), 'summary.total_orphans is required');
    assertTrue(((int)($summary['executed_checks'] ?? 0) + (int)($summary['skipped_checks'] ?? 0)) >= 55, 'db_orphan_checker must cover the critical FK/app-integrity matrix');

    $checks = is_array($json['checks'] ?? null) ? $json['checks'] : [];
    $keys = array_map(static fn(array $row): string => (string)($row['key'] ?? ''), $checks);
    foreach ([
        'user_sessions.user_id->users.id',
        'files.uploader_user_id->users.id',
        'subtasks.task_id->tasks.id',
        'checklist_items.checklist_id->checklists.id',
        'calendar_events.owner_user_id->users.id',
        'webhook_deliveries.webhook_id->webhook_subscriptions.id',
        'notification_push_subscriptions.user_id->users.id',
    ] as $requiredKey) {
        assertTrue(in_array($requiredKey, $keys, true), 'db_orphan_checker missing critical integrity check: ' . $requiredKey);
    }

    fwrite(STDOUT, "[OK] db_orphan_checker_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] db_orphan_checker_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
