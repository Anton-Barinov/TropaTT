<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/notifications/push-subscriptions', [
        'endpoint' => 'https://push.example.local/sub/' . $suffix,
        'p256dh' => 'p256dh-' . $suffix,
        'auth' => 'auth-' . $suffix,
        'device_label' => 'integration',
        'user_agent' => 'crm-integration-test/1.0',
    ], $headers);
    assertTrue($create['status'] === 201, 'Push subscription create must return 201');
    $publicId = (string)($create['payload']['data']['subscription']['public_id'] ?? '');
    assertTrue($publicId !== '', 'Push subscription public_id required');

    $list = request('GET', '/api/v1/notifications/push-subscriptions', [], $headers);
    assertTrue($list['status'] === 200, 'Push subscription list must return 200');
    $items = (array)($list['payload']['data']['items'] ?? []);
    assertTrue(count($items) >= 1, 'Push subscription list must contain at least one item');

    $test = request('POST', '/api/v1/notifications/push-test', [], $headers);
    assertTrue($test['status'] === 200, 'Push test must return 200');
    assertTrue((string)($test['payload']['code'] ?? '') === 'NOTIFICATION_PUSH_TEST', 'Push test response code mismatch');
    $dispatch = (array)($test['payload']['data']['dispatch'] ?? []);
    assertTrue(array_key_exists('gateway_configured', $dispatch), 'Push test dispatch.gateway_configured required');

    $delete = request('DELETE', '/api/v1/notifications/push-subscriptions/' . $publicId, [], $headers);
    assertTrue($delete['status'] === 200, 'Push subscription delete must return 200');

    echo "[OK] notification_push_subscription_smoke\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] notification_push_subscription_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
