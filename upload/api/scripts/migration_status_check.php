<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }


require __DIR__ . '/../tests/integration/_bootstrap.php';

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');

    $response = request('GET', '/internal/migration/status', [], authHeaders($token));
    assertTrue($response['status'] === 200, 'Migration status endpoint should return 200');
    assertTrue((string)($response['payload']['code'] ?? '') === 'MIGRATION_STATUS', 'Migration status code mismatch');

    fwrite(STDOUT, "[OK] migration_status_check\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] migration_status_check: " . $e->getMessage() . "\n");
    exit(1);
}

