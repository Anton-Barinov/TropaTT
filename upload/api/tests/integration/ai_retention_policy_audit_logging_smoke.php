<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $list = request('GET', '/api/v1/ai/retention-policies', [], $rootHeaders);
    assertTrue($list['status'] === 200, 'Retention policies list status must be 200');
    $items = (array)($list['payload']['data']['items'] ?? []);

    $policyCode = 'suggestions_ttl_days';
    assertTrue(array_key_exists($policyCode, $items), 'Retention policy must exist: ' . $policyCode);
    $beforeDays = max(1, (int)($items[$policyCode] ?? 30));
    $updateDays = $beforeDays >= 3650 ? ($beforeDays - 1) : ($beforeDays + 1);

    $update = request('PATCH', '/api/v1/ai/retention-policies/' . rawurlencode($policyCode), [
        'days' => $updateDays,
    ], $rootHeaders);
    assertTrue($update['status'] === 200, 'Retention policy update status must be 200');

    $audit = request('GET', '/api/v1/ai/audit?limit=100', [], $rootHeaders);
    assertTrue($audit['status'] === 200, 'AI audit list status must be 200');
    $auditItems = (array)($audit['payload']['data']['items'] ?? []);

    $found = false;
    foreach ($auditItems as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        if ((string)($entry['action'] ?? '') !== 'ai_retention_policy_updated') {
            continue;
        }

        $details = (array)($entry['details'] ?? []);
        if ((string)($details['policy_code'] ?? '') !== $policyCode) {
            continue;
        }
        if ((int)($details['before_days'] ?? -1) !== $beforeDays) {
            continue;
        }
        if ((int)($details['after_days'] ?? -1) !== $updateDays) {
            continue;
        }

        $found = true;
        break;
    }

    assertTrue($found, 'Retention policy update must be present in AI audit log with before/after values');

    $restore = request('PATCH', '/api/v1/ai/retention-policies/' . rawurlencode($policyCode), [
        'days' => $beforeDays,
    ], $rootHeaders);
    assertTrue($restore['status'] === 200, 'Retention policy restore status must be 200');

    fwrite(STDOUT, "[OK] ai_retention_policy_audit_logging_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_retention_policy_audit_logging_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
