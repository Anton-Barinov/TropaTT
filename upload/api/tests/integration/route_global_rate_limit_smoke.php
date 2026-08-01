<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $storageBase = trim((string)getenv('CRM_STORAGE_BASE'));
    if ($storageBase !== '') {
        $rateLimitFile = rtrim($storageBase, '/\\') . '/cache/route_global_rate_limit.json';
        if (is_file($rateLimitFile)) {
            @unlink($rateLimitFile);
        }
    }

    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');

    $headers = authHeaders($token);
    $rateLimited = null;
    $firstStatus = 0;

    for ($i = 0; $i < 140; $i++) {
        $response = request('POST', '/api/v1/notifications/mark-all-read', [], $headers);
        if ($i === 0) {
            $firstStatus = (int)$response['status'];
        }

        if ((int)$response['status'] === 429) {
            $rateLimited = $response;
            break;
        }
    }

    assertTrue($firstStatus === 200, 'First request should pass before rate limit');
    assertTrue(is_array($rateLimited), 'Route global rate limit should eventually return 429');
    assertTrue((string)($rateLimited['payload']['code'] ?? '') === 'RATE_LIMITED', 'Rate limited response code must be RATE_LIMITED');
    assertTrue((int)($rateLimited['payload']['meta']['retry_after'] ?? 0) >= 1, 'Rate limited response must include meta.retry_after');

    fwrite(STDOUT, "[OK] route_global_rate_limit_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] route_global_rate_limit_smoke: " . $e->getMessage() . "\n");
    exit(1);
}

