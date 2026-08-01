<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $suffix = randomSuffix();
    $blockedLocalhost = request('POST', '/api/v1/webhooks', [
        'title' => 'Blocked localhost webhook ' . $suffix,
        'endpoint' => 'https://localhost/api/index.php?route=api/v1/health/status',
        'events' => ['task.created'],
        'is_active' => 1,
    ], $headers);
    assertTrue($blockedLocalhost['status'] === 422, 'Localhost webhook endpoint must be rejected');
    assertTrue(($blockedLocalhost['payload']['code'] ?? '') === 'WEBHOOK_ENDPOINT_LOCALHOST_FORBIDDEN', 'Localhost webhook endpoint code mismatch');

    $blockedPrivateIp = request('POST', '/api/v1/webhooks', [
        'title' => 'Blocked private IP webhook ' . $suffix,
        'endpoint' => 'https://10.0.0.1/webhook',
        'events' => ['task.created'],
        'is_active' => 1,
    ], $headers);
    assertTrue($blockedPrivateIp['status'] === 422, 'Private IP webhook endpoint must be rejected');
    assertTrue(($blockedPrivateIp['payload']['code'] ?? '') === 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN', 'Private IP webhook endpoint code mismatch');

    $blockedMetadata = request('POST', '/api/v1/webhooks', [
        'title' => 'Blocked metadata webhook ' . $suffix,
        'endpoint' => 'http://169.254.169.254/latest/meta-data',
        'events' => ['task.created'],
        'is_active' => 1,
    ], $headers);
    assertTrue($blockedMetadata['status'] === 422, 'Metadata webhook endpoint must be rejected');
    assertTrue(
        in_array((string)($blockedMetadata['payload']['code'] ?? ''), ['WEBHOOK_ENDPOINT_SCHEME_NOT_ALLOWED', 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN'], true),
        'Metadata webhook endpoint code mismatch'
    );

    $blockedHttp = request('POST', '/api/v1/webhooks', [
        'title' => 'Blocked HTTP webhook ' . $suffix,
        'endpoint' => 'http://example.com/webhook',
        'events' => ['task.created'],
        'is_active' => 1,
    ], $headers);
    assertTrue($blockedHttp['status'] === 422, 'HTTP webhook endpoint must be rejected in production');
    assertTrue(($blockedHttp['payload']['code'] ?? '') === 'WEBHOOK_ENDPOINT_SCHEME_NOT_ALLOWED', 'HTTP webhook endpoint code mismatch');

    $create = request('POST', '/api/v1/webhooks', [
        'title' => 'Hardening webhook ' . $suffix,
        'endpoint' => 'https://example.com/webhook-hardening-' . $suffix,
        'events' => ['task.created'],
        'is_active' => 1,
        'secret' => 'hardening-' . $suffix,
    ], $headers);
    assertTrue($create['status'] === 201, 'Webhook hardening create status must be 201');
    $webhookPublicId = (string)($create['payload']['data']['webhook']['public_id'] ?? '');
    assertTrue($webhookPublicId !== '', 'Webhook hardening public_id is required');

    $blockedUpdate = request('PATCH', '/api/v1/webhooks/' . $webhookPublicId, [
        'endpoint' => 'https://127.0.0.1/hook',
    ], $headers);
    assertTrue($blockedUpdate['status'] === 422, 'Private update endpoint must be rejected');
    assertTrue(($blockedUpdate['payload']['code'] ?? '') === 'WEBHOOK_ENDPOINT_PRIVATE_IP_FORBIDDEN', 'Private update endpoint code mismatch');

    for ($i = 1; $i <= 3; $i++) {
        $test = request('POST', '/api/v1/webhooks/' . $webhookPublicId . '/test', [], $headers);
        assertTrue($test['status'] === 200, 'Webhook hardening test status must be 200');
        assertTrue((int)($test['payload']['data']['delivery']['attempts'] ?? 0) >= 1, 'Webhook hardening attempts must be >= 1');
        assertTrue(isset($test['payload']['data']['delivery']['signed']), 'Webhook hardening signed flag must exist');
    }

    $list = request('GET', '/api/v1/webhooks', ['search' => $suffix], $headers);
    assertTrue($list['status'] === 200, 'Webhook hardening list status must be 200');
    $items = (array)($list['payload']['data']['items'] ?? []);
    $matched = null;
    foreach ($items as $item) {
        if ((string)($item['public_id'] ?? '') === $webhookPublicId) {
            $matched = $item;
            break;
        }
    }
    assertTrue(is_array($matched), 'Webhook hardening list item not found');
    assertTrue((int)($matched['is_active'] ?? 1) === 0, 'Webhook must be auto-disabled after repeated failures');

    $inactiveTest = request('POST', '/api/v1/webhooks/' . $webhookPublicId . '/test', [], $headers);
    assertTrue($inactiveTest['status'] === 409, 'Inactive webhook test must return 409');
    assertTrue(($inactiveTest['payload']['code'] ?? '') === 'WEBHOOK_INACTIVE', 'Inactive webhook code mismatch');

    $deliveries = request('GET', '/api/v1/webhooks/' . $webhookPublicId . '/deliveries', [], $headers);
    assertTrue($deliveries['status'] === 200, 'Webhook hardening deliveries status must be 200');
    $deliveryItems = (array)($deliveries['payload']['data']['items'] ?? []);
    assertTrue(count($deliveryItems) >= 3, 'Webhook hardening deliveries must include retry runs');

    request('DELETE', '/api/v1/webhooks/' . $webhookPublicId, [], $headers);

    echo "[OK] Webhook hardening smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
