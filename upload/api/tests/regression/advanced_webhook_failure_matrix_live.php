<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * @param array<string,string> $headers
 */
function createWebhook(string $suffix, string $endpoint, array $headers): string
{
    $create = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Webhook matrix ' . $suffix,
        'endpoint' => $endpoint,
        'events' => ['task.updated'],
        'secret' => 'matrix-secret-' . $suffix,
        'is_active' => 1,
    ], $headers);

    liveAssert($create['status'] === 201, 'Webhook create must return 201 for ' . $endpoint);
    $publicId = (string)($create['payload']['data']['webhook']['public_id'] ?? '');
    liveAssert($publicId !== '', 'Webhook public_id is required for ' . $endpoint);

    return $publicId;
}

/**
 * @param array<string,string> $headers
 * @return array<string,mixed>
 */
function runTestDelivery(string $webhookPublicId, array $headers): array
{
    $response = liveRequest('POST', 'api/v1/webhooks/' . $webhookPublicId . '/test', [], $headers);
    liveAssert($response['status'] === 200, 'Webhook test delivery must return 200 for ' . $webhookPublicId);

    return (array)($response['payload']['data']['delivery'] ?? []);
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $rootPublicId = (string)$root['user_public_id'];
    liveAssert($rootPublicId !== '', 'Root public_id is required');

    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $endpoint4xx = 'https://localhost/api/index.php?route=definitely/missing/' . $suffix;
    $endpoint5xx = 'https://localhost/api/tests/fixtures/webhook_http_500.php';
    $endpointTimeout = 'https://localhost/api/tests/fixtures/webhook_timeout.php';
    $endpointConnect = 'https://127.0.0.1:1/unreachable-' . $suffix;

    $wh4xx = createWebhook('4xx-' . $suffix, $endpoint4xx, $rootHeaders);
    $wh5xx = createWebhook('5xx-' . $suffix, $endpoint5xx, $rootHeaders);
    $whTimeout = createWebhook('timeout-' . $suffix, $endpointTimeout, $rootHeaders);
    $whConnect = createWebhook('connect-' . $suffix, $endpointConnect, $rootHeaders);

    $d4xx = runTestDelivery($wh4xx, $rootHeaders);
    liveAssert((string)($d4xx['status'] ?? '') === 'failed', '4xx case must have status failed');
    liveAssert((int)($d4xx['response_code'] ?? -1) === 404, '4xx case must return response_code 404');
    liveAssert((int)($d4xx['attempts'] ?? 0) === 3, '4xx case must exhaust retries (attempts=3)');

    $d5xx = runTestDelivery($wh5xx, $rootHeaders);
    liveAssert((string)($d5xx['status'] ?? '') === 'failed', '5xx case must have status failed');
    liveAssert((int)($d5xx['response_code'] ?? -1) === 500, '5xx case must return response_code 500');
    liveAssert((int)($d5xx['attempts'] ?? 0) === 3, '5xx case must exhaust retries (attempts=3)');

    $dTimeout = runTestDelivery($whTimeout, $rootHeaders);
    liveAssert((string)($dTimeout['status'] ?? '') === 'error', 'timeout case must have status error');
    liveAssert((int)($dTimeout['response_code'] ?? -1) === 0, 'timeout case must return response_code 0');
    liveAssert((int)($dTimeout['attempts'] ?? 0) === 3, 'timeout case must exhaust retries (attempts=3)');

    $dConnect = runTestDelivery($whConnect, $rootHeaders);
    liveAssert((string)($dConnect['status'] ?? '') === 'error', 'connect case must have status error');
    liveAssert((int)($dConnect['response_code'] ?? -1) === 0, 'connect case must return response_code 0');
    liveAssert((int)($dConnect['attempts'] ?? 0) === 3, 'connect case must exhaust retries (attempts=3)');

    // Auto-disable after consecutive failures on same webhook.
    $autoDisabled = (bool)($dConnect['auto_disabled'] ?? false);
    for ($i = 0; $i < 4 && !$autoDisabled; $i++) {
        $next = runTestDelivery($whConnect, $rootHeaders);
        $autoDisabled = (bool)($next['auto_disabled'] ?? false);
    }
    liveAssert($autoDisabled, 'Webhook must be auto-disabled after consecutive failures');

    $inactiveTest = liveRequest('POST', 'api/v1/webhooks/' . $whConnect . '/test', [], $rootHeaders);
    liveAssert($inactiveTest['status'] === 409, 'Inactive webhook test must return 409');
    liveAssert((string)($inactiveTest['payload']['code'] ?? '') === 'WEBHOOK_INACTIVE', 'Inactive webhook code mismatch');

    // Logs verification.
    $auditLogs = liveRequest('GET', 'api/v1/logs/audit', [
        'entity_type' => 'webhook_subscription',
        'entity_public_id' => $whConnect,
        'limit' => 50,
    ], $rootHeaders);
    liveAssert($auditLogs['status'] === 200, 'Audit logs request must return 200');

    $auditItems = (array)($auditLogs['payload']['data']['items'] ?? []);
    $hasAuditDelivery = false;
    foreach ($auditItems as $item) {
        if ((string)($item['action'] ?? '') === 'webhook_delivery_test') {
            $hasAuditDelivery = true;
            break;
        }
    }
    liveAssert($hasAuditDelivery, 'Audit logs must contain webhook_delivery_test action');

    $securityLogs = liveRequest('GET', 'api/v1/logs/security', [
        'event_type' => 'webhook_test_delivery',
        'actor_public_id' => $rootPublicId,
        'limit' => 100,
    ], $rootHeaders);
    liveAssert($securityLogs['status'] === 200, 'Security logs request must return 200');

    $securityItems = (array)($securityLogs['payload']['data']['items'] ?? []);
    liveAssert(count($securityItems) > 0, 'Security logs must contain webhook_test_delivery events');

    // Cleanup.
    foreach ([$wh4xx, $wh5xx, $whTimeout, $whConnect] as $wid) {
        liveRequest('DELETE', 'api/v1/webhooks/' . $wid, [], $rootHeaders);
    }

    echo "[OK] advanced_webhook_failure_matrix_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_webhook_failure_matrix_live: ' . $e->getMessage() . "\n");
    exit(1);
}
