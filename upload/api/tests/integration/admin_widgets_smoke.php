<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $summary = request('GET', '/api/v1/admin/widgets/summary', [], $headers);
    assertTrue($summary['status'] === 200, 'Admin widgets summary status must be 200');
    assertTrue(($summary['payload']['code'] ?? '') === 'ADMIN_WIDGETS_SUMMARY', 'Admin widgets summary code mismatch');
    assertTrue(isset($summary['payload']['data']['widgets']['counts']), 'Admin widgets summary counts missing');
    assertTrue(isset($summary['payload']['data']['widgets']['logs']), 'Admin widgets summary logs missing');
    assertTrue(isset($summary['payload']['data']['widgets']['migrations']), 'Admin widgets summary migrations missing');

    $system = request('GET', '/api/v1/admin/widgets/system', [], $headers);
    assertTrue($system['status'] === 200, 'Admin widgets system status must be 200');
    assertTrue(($system['payload']['code'] ?? '') === 'ADMIN_WIDGETS_SYSTEM', 'Admin widgets system code mismatch');
    assertTrue(isset($system['payload']['data']['widgets']['database']['connected']), 'Admin widgets system database connected missing');
    assertTrue(isset($system['payload']['data']['widgets']['storage']['directories']), 'Admin widgets system storage missing');

    $aliasSummary = request('GET', '/api/v1/admin/widget/summary', [], $headers);
    assertTrue($aliasSummary['status'] === 200, 'Admin widgets alias summary status must be 200');

    $aliasSystem = request('GET', '/api/v1/admin/widget/system', [], $headers);
    assertTrue($aliasSystem['status'] === 200, 'Admin widgets alias system status must be 200');

    $unauthorized = request('GET', '/api/v1/admin/widgets/summary');
    assertTrue($unauthorized['status'] === 401, 'Admin widgets unauthorized status must be 401');

    echo "[OK] Admin widgets smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
