<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $suffix = randomSuffix();
    $create = request('POST', '/api/v1/webhooks', [
        'title' => 'Webhook ' . $suffix,
        'endpoint' => 'https://example.com/webhook-health',
        'events' => ['task.created', 'task.updated'],
        'is_active' => 1,
        'secret' => 'sec_' . $suffix,
    ], $headers);
    assertTrue($create['status'] === 201, 'Webhook create status must be 201');
    $webhookPublicId = (string)($create['payload']['data']['webhook']['public_id'] ?? '');
    assertTrue($webhookPublicId !== '', 'Webhook public_id is required');

    $list = request('GET', '/api/v1/webhooks', [], $headers);
    assertTrue($list['status'] === 200, 'Webhook list status must be 200');

    $test = request('POST', '/api/v1/webhooks/' . $webhookPublicId . '/test', [], $headers);
    assertTrue($test['status'] === 200, 'Webhook test status must be 200');
    assertTrue(isset($test['payload']['data']['delivery']['status']), 'Webhook test delivery status is required');

    $deliveries = request('GET', '/api/v1/webhooks/deliveries', [], $headers);
    assertTrue($deliveries['status'] === 200, 'Webhook deliveries status must be 200');

    $deliveriesByWebhook = request('GET', '/api/v1/webhooks/' . $webhookPublicId . '/deliveries', [], $headers);
    assertTrue($deliveriesByWebhook['status'] === 200, 'Webhook deliveries by webhook status must be 200');

    $aliasDeliveries = request('GET', '/api/v1/webhook/deliveries/' . $webhookPublicId, [], $headers);
    assertTrue($aliasDeliveries['status'] === 200, 'Webhook alias deliveries status must be 200');

    $ops = request('GET', '/api/v1/ops/system', [], $headers);
    assertTrue($ops['status'] === 200, 'Ops system status must be 200');
    assertTrue(isset($ops['payload']['data']['webhooks']['subscriptions_total']), 'Ops system webhook summary missing');

    $opsAlias = request('GET', '/api/v1/ops/system/get', [], $headers);
    assertTrue($opsAlias['status'] === 200, 'Ops system alias status must be 200');

    $delete = request('DELETE', '/api/v1/webhooks/' . $webhookPublicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Webhook delete status must be 200');

    $unauthorizedWebhook = request('GET', '/api/v1/webhooks');
    assertTrue($unauthorizedWebhook['status'] === 401, 'Webhook list unauthorized status must be 401');

    $unauthorizedOps = request('GET', '/api/v1/ops/system');
    assertTrue($unauthorizedOps['status'] === 401, 'Ops system unauthorized status must be 401');

    echo "[OK] Webhook/Ops smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
