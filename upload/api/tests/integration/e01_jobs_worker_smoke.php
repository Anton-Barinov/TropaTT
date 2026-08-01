<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders((string)$root['token']);
    request('POST', '/internal/migration/up', [], $headers);

    $import = request('POST', '/api/v1/import/jobs', [
        'type' => 'tasks',
        'async' => 1,
        'rows' => [[
            'title' => 'E01 async import ' . randomSuffix(),
            'status' => 'new',
            'priority' => 'normal',
        ]],
    ], $headers);
    assertTrue($import['status'] === 201, 'Async import job create must return 201');
    $importId = (string)($import['payload']['data']['job']['public_id'] ?? '');
    assertTrue($importId !== '', 'Async import job id is required');

    $export = request('POST', '/api/v1/export/jobs', [
        'type' => 'tasks',
        'async' => 1,
        'filters' => ['search' => 'E01 async import'],
    ], $headers);
    assertTrue($export['status'] === 201, 'Async export job create must return 201');
    $exportId = (string)($export['payload']['data']['job']['public_id'] ?? '');
    assertTrue($exportId !== '', 'Async export job id is required');

    $run = request('POST', '/api/v1/ops/jobs/run', ['limit' => 10], $headers);
    assertTrue($run['status'] === 200, 'Worker run endpoint must return 200');
    assertTrue((string)($run['payload']['code'] ?? '') === 'OPS_JOBS_RUN', 'Worker run code mismatch');

    $importGet = request('GET', '/api/v1/import/jobs/' . $importId, [], $headers);
    assertTrue($importGet['status'] === 200, 'Async import get must return 200');
    $importStatus = (string)($importGet['payload']['data']['job']['status'] ?? '');
    assertTrue(in_array($importStatus, ['completed', 'completed_with_errors', 'retry', 'dead_letter'], true), 'Async import status after worker is invalid');

    $exportGet = request('GET', '/api/v1/export/jobs/' . $exportId, [], $headers);
    assertTrue($exportGet['status'] === 200, 'Async export get must return 200');
    $exportStatus = (string)($exportGet['payload']['data']['job']['status'] ?? '');
    assertTrue(in_array($exportStatus, ['completed', 'retry', 'dead_letter'], true), 'Async export status after worker is invalid');

    $suffix = randomSuffix();
    $pushCreate = request('POST', '/api/v1/notifications/push-subscriptions', [
        'endpoint' => 'https://push.example.local/sub/' . $suffix,
        'p256dh' => 'p256dh-' . $suffix,
        'auth' => 'auth-' . $suffix,
        'device_label' => 'e01-worker',
        'user_agent' => 'crm-api-integration-test/1.0',
    ], $headers);
    assertTrue($pushCreate['status'] === 201, 'Push subscription create must return 201');
    $pushPublicId = (string)($pushCreate['payload']['data']['subscription']['public_id'] ?? '');
    assertTrue($pushPublicId !== '', 'Push subscription public_id required');

    $notification = request('POST', '/api/v1/notifications', [
        'title' => 'E01 Push Queue ' . $suffix,
        'body' => 'Queue check',
        'category' => 'system',
        'target_user_public_id' => (string)($root['user_public_id'] ?? ''),
    ], $headers);
    assertTrue($notification['status'] === 201, 'Notification create for push queue must return 201');

    $runPush = request('POST', '/api/v1/ops/jobs/run', ['limit' => 10], $headers);
    assertTrue($runPush['status'] === 200, 'Worker run endpoint (push) must return 200');
    $pushStats = (array)($runPush['payload']['data']['push'] ?? []);
    assertTrue(array_key_exists('processed', $pushStats), 'Push worker stats must include processed');
    assertTrue((int)($pushStats['processed'] ?? 0) >= 0, 'Push worker processed must be numeric');

    request('DELETE', '/api/v1/notifications/push-subscriptions/' . $pushPublicId, [], $headers);

    $webhook = request('POST', '/api/v1/webhooks', [
        'title' => 'E01 async webhook ' . $suffix,
        'endpoint' => 'https://example.com/webhook-e01-' . $suffix,
        'events' => ['webhook.test'],
        'is_active' => 1,
        'secret' => 'e01-webhook-' . $suffix,
    ], $headers);
    assertTrue($webhook['status'] === 201, 'Webhook create for queue must return 201');
    $webhookPublicId = (string)($webhook['payload']['data']['webhook']['public_id'] ?? '');
    assertTrue($webhookPublicId !== '', 'Webhook public_id required');

    $queuedWebhook = request('POST', '/api/v1/webhooks/' . $webhookPublicId . '/test', ['async' => 1], $headers);
    assertTrue($queuedWebhook['status'] === 202, 'Async webhook test must return 202');
    assertTrue((string)($queuedWebhook['payload']['code'] ?? '') === 'WEBHOOK_TEST_QUEUED', 'Async webhook response code mismatch');

    $runWebhook = request('POST', '/api/v1/ops/jobs/run', ['limit' => 10], $headers);
    assertTrue($runWebhook['status'] === 200, 'Worker run endpoint (webhook) must return 200');
    $webhookStats = (array)($runWebhook['payload']['data']['webhook'] ?? []);
    assertTrue(array_key_exists('processed', $webhookStats), 'Webhook worker stats must include processed');
    assertTrue((int)($webhookStats['processed'] ?? 0) >= 0, 'Webhook worker processed must be numeric');

    $deliveries = request('GET', '/api/v1/webhooks/' . $webhookPublicId . '/deliveries', [], $headers);
    assertTrue($deliveries['status'] === 200, 'Webhook deliveries after async queue must return 200');
    $deliveryItems = (array)($deliveries['payload']['data']['items'] ?? []);
    assertTrue(count($deliveryItems) >= 1, 'Webhook deliveries must include queued delivery');

    request('DELETE', '/api/v1/webhooks/' . $webhookPublicId, [], $headers);

    fwrite(STDOUT, "[OK] e01_jobs_worker_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] e01_jobs_worker_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
