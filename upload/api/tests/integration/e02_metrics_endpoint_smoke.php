<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);

    request('POST', '/internal/migration/up', [], $rootHeaders);

    $metrics = request('GET', '/api/v1/ops/metrics', [], $rootHeaders);
    assertTrue($metrics['status'] === 200, 'Ops metrics must return 200 for root');
    assertTrue((string)($metrics['payload']['code'] ?? '') === 'OPS_METRICS', 'Ops metrics code mismatch');
    $data = (array)($metrics['payload']['data'] ?? []);
    $metricsPayload = (array)($data['metrics'] ?? []);
    assertTrue(array_key_exists('queues', $metricsPayload), 'Metrics payload must include queues');
    assertTrue(array_key_exists('api_24h', $metricsPayload), 'Metrics payload must include API request metrics');
    assertTrue(array_key_exists('ai_24h', $metricsPayload), 'Metrics payload must include AI metrics');
    assertTrue(array_key_exists('p95_duration_ms', (array)$metricsPayload['api_24h']), 'API metrics must include p95 duration');
    assertTrue(array_key_exists('timeouts', (array)$metricsPayload['ai_24h']), 'AI metrics must include timeout counter');

    $suffix = randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ops_metrics_restricted_' . $suffix,
        'title' => 'Ops Metrics Restricted ' . $suffix,
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');

    $login = 'ops.metrics.restricted.' . $suffix;
    $password = 'OpsMetrics#2026!';
    $token = 'ops-metrics-token-' . $suffix;
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $login,
        'password' => $password,
        'email' => $login . '@crm.local',
        'full_name' => 'Ops Metrics Restricted',
        'token' => $token,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');

    $auth = request('POST', '/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'token' => $token,
    ]);
    assertTrue($auth['status'] === 200, 'Restricted login must return 200');
    $restrictedHeaders = authHeaders((string)($auth['payload']['data']['access_token'] ?? ''));

    $forbidden = request('GET', '/api/v1/ops/metrics', [], $restrictedHeaders);
    assertTrue($forbidden['status'] === 403, 'Restricted without logs.view must get 403 for ops metrics');

    if ($userPublicId !== '') {
        request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    }
    if ($rolePublicId !== '') {
        request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] e02_metrics_endpoint_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] e02_metrics_endpoint_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
