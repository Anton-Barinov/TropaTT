<?php
declare(strict_types=1);

require __DIR__ . '/../tests/integration/_bootstrap.php';

$limit = 20;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = (int)substr($arg, strlen('--limit='));
    }
}
$limit = max(1, min(100, $limit));

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');
    request('POST', '/internal/migration/up', [], authHeaders($token));

    $response = request('POST', '/api/v1/ops/jobs/run', [
        'limit' => $limit,
    ], authHeaders($token));

    assertTrue($response['status'] === 200, 'Jobs worker endpoint should return 200');
    assertTrue((string)($response['payload']['code'] ?? '') === 'OPS_JOBS_RUN', 'Jobs worker response code mismatch');

    $data = (array)($response['payload']['data'] ?? []);
    fwrite(STDOUT, json_encode([
        'ok' => true,
        'limit' => $limit,
        'import' => (array)($data['import'] ?? []),
        'export' => (array)($data['export'] ?? []),
        'push' => (array)($data['push'] ?? []),
        'webhook' => (array)($data['webhook'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] jobs_worker_run: " . $e->getMessage() . PHP_EOL);
    exit(1);
}
