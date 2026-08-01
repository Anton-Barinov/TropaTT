<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $unauthorized = liveRequest('GET', 'api/v1/health/deep');
    liveAssert($unauthorized['status'] === 401, 'Health deep without auth must return 401');

    $auth = liveLoginRoot();
    $response = liveRequest('GET', 'api/v1/health/deep', [], [
        'Authorization' => 'Bearer ' . $auth['token'],
    ]);
    liveAssert($response['status'] === 200, 'Health deep must return 200');

    $code = (string)($response['payload']['code'] ?? '');
    liveAssert(in_array($code, ['HEALTH_DEEP_OK', 'HEALTH_DEEP_DEGRADED'], true), 'Health deep code must be HEALTH_DEEP_OK or HEALTH_DEEP_DEGRADED');

    $checks = (array)($response['payload']['data']['checks'] ?? []);
    liveAssert(array_key_exists('db_read', $checks), 'Health deep checks.db_read is required');
    liveAssert(array_key_exists('db_write', $checks), 'Health deep checks.db_write is required');
    liveAssert(array_key_exists('storage_rw', $checks), 'Health deep checks.storage_rw is required');
    liveAssert(array_key_exists('queue_ready', $checks), 'Health deep checks.queue_ready is required');

    $degraded = (bool)($response['payload']['data']['degraded_mode'] ?? false);
    $status = (string)($response['payload']['data']['status'] ?? '');
    if ($degraded) {
        liveAssert($status === 'degraded', 'Health deep status must be degraded when degraded_mode=true');
    } else {
        liveAssert($status === 'ok', 'Health deep status must be ok when degraded_mode=false');
    }

    echo "[OK] advanced_health_deep_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_health_deep_live: ' . $e->getMessage() . "\n");
    exit(1);
}
