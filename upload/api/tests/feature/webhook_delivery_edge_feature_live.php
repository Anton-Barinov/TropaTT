<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];

    $suffix = 'edge-' . date('YmdHis') . '-' . bin2hex(random_bytes(2));

    $create = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Webhook Edge ' . $suffix,
        'endpoint' => 'https://example.com/unreachable',
        'events' => ['task.updated'],
        'secret' => 'edge-secret-' . $suffix,
        'is_active' => 1,
    ], $headers);
    liveAssert($create['status'] === 201, 'Webhook create must return 201');

    $webhookPublicId = (string)($create['payload']['data']['webhook']['public_id'] ?? '');
    liveAssert($webhookPublicId !== '', 'Webhook public_id is required');

    $autoDisabled = false;
    for ($i = 1; $i <= 4; $i++) {
        $test = liveRequest('POST', 'api/v1/webhooks/' . $webhookPublicId . '/test', [], $headers);
        liveAssert($test['status'] === 200, 'Webhook test delivery must return 200 while active');

        $deliveryStatus = (string)($test['payload']['data']['delivery']['status'] ?? '');
        liveAssert(in_array($deliveryStatus, ['failed', 'error', 'sent'], true), 'Unexpected delivery status');

        if (($test['payload']['data']['delivery']['auto_disabled'] ?? false) === true) {
            $autoDisabled = true;
            break;
        }
    }

    liveAssert($autoDisabled, 'Webhook must be auto-disabled after consecutive failures');

    $testWhenInactive = liveRequest('POST', 'api/v1/webhooks/' . $webhookPublicId . '/test', [], $headers);
    liveAssert($testWhenInactive['status'] === 409, 'Webhook test for inactive subscription must return 409');
    liveAssert((string)($testWhenInactive['payload']['code'] ?? '') === 'WEBHOOK_INACTIVE', 'Expected WEBHOOK_INACTIVE for inactive subscription');

    $list = liveRequest('GET', 'api/v1/webhooks', ['search' => $suffix], $headers);
    liveAssert($list['status'] === 200, 'Webhook list must return 200');

    $found = null;
    foreach ((array)($list['payload']['data']['items'] ?? []) as $item) {
        if ((string)($item['public_id'] ?? '') === $webhookPublicId) {
            $found = $item;
            break;
        }
    }
    liveAssert(is_array($found), 'Webhook should be present in list');
    liveAssert((int)($found['is_active'] ?? 1) === 0, 'Webhook must be inactive after auto-disable');

    $delete = liveRequest('DELETE', 'api/v1/webhooks/' . $webhookPublicId, [], $headers);
    liveAssert($delete['status'] === 200, 'Webhook delete must return 200');

    echo "[OK] webhook_delivery_edge_feature_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] webhook_delivery_edge_feature_live: ' . $e->getMessage() . "\n");
    exit(1);
}
