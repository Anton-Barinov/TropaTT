<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $up = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($up['status'] === 200, 'Migration up must be 200');

    $rollbackCheck = request('GET', '/internal/migration/rollback-check', [], $headers);
    assertTrue($rollbackCheck['status'] === 200, 'Rollback-check status must be 200');
    assertTrue((string)($rollbackCheck['payload']['code'] ?? '') === 'MIGRATION_ROLLBACK_CHECK', 'Rollback-check code mismatch');

    $rollbackData = (array)($rollbackCheck['payload']['data']['rollback_check'] ?? []);
    assertTrue(array_key_exists('rollback_possible', $rollbackData), 'rollback_check.rollback_possible is required');
    assertTrue(array_key_exists('safe_plan_available', $rollbackData), 'rollback_check.safe_plan_available is required');
    assertTrue(array_key_exists('safe_rollback_plan', $rollbackData), 'rollback_check.safe_rollback_plan is required');

    $rollbackPossible = (bool)($rollbackData['rollback_possible'] ?? false);
    $safePlanAvailable = (bool)($rollbackData['safe_plan_available'] ?? false);
    $safePlan = is_array($rollbackData['safe_rollback_plan'] ?? null) ? (array)$rollbackData['safe_rollback_plan'] : [];
    $nonReversible = is_array($rollbackData['non_reversible_applied'] ?? null) ? (array)$rollbackData['non_reversible_applied'] : [];

    if (!$rollbackPossible) {
        assertTrue($safePlanAvailable, 'Safe rollback plan must be available when direct rollback is not possible');
        assertTrue($safePlan !== [], 'Safe rollback plan steps must be present when direct rollback is not possible');

        if ($nonReversible !== []) {
            $joined = implode("\n", array_map(static fn($v): string => (string)$v, $safePlan));
            $expectedMarker = (string)$nonReversible[0];
            assertTrue(strpos($joined, $expectedMarker) !== false, 'Safe rollback plan must mention non-reversible migration key');
        }
    }

    fwrite(STDOUT, "[OK] migration_safe_rollback_plan_smoke\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
