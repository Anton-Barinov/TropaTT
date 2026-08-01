<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'IdemLock Project ' . $suffix,
        'description' => 'Regression project',
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $projectGet = liveRequest('GET', 'api/v1/projects/' . $projectPublicId, [], $headers);
    liveAssert($projectGet['status'] === 200, 'Project get must return 200');
    $rowVersion = (int)($projectGet['payload']['data']['project']['row_version'] ?? 0);
    liveAssert($rowVersion > 0, 'Project row_version must be > 0');

    $projectUpdateOk = liveRequest('PATCH', 'api/v1/projects/' . $projectPublicId, [
        'description' => 'Project row version update 1',
        'row_version' => $rowVersion,
    ], $headers);
    liveAssert($projectUpdateOk['status'] === 200, 'Project update with current row_version must return 200');

    $projectUpdateConflict = liveRequest('PATCH', 'api/v1/projects/' . $projectPublicId, [
        'description' => 'Project row version stale update',
        'row_version' => $rowVersion,
    ], $headers);
    liveAssert($projectUpdateConflict['status'] === 409, 'Project stale row_version update must return 409');
    liveAssert((string)($projectUpdateConflict['payload']['code'] ?? '') === 'ROW_VERSION_CONFLICT', 'Project conflict code mismatch');

    $apiClientIdem = 'idem-api-client-' . $suffix;
    $apiClientPayload = [
        'title' => 'Idem API Client ' . $suffix,
        'scopes' => ['tasks.read', 'tasks.write'],
        'is_active' => 1,
    ];
    $apiClientCreate1 = liveRequest('POST', 'api/v1/api-clients', $apiClientPayload, array_merge($headers, [
        'X-Idempotency-Key' => $apiClientIdem,
    ]));
    $apiClientCreate2 = liveRequest('POST', 'api/v1/api-clients', $apiClientPayload, array_merge($headers, [
        'X-Idempotency-Key' => $apiClientIdem,
    ]));
    liveAssert($apiClientCreate1['status'] === 201 && $apiClientCreate2['status'] === 201, 'API client idempotency calls must return 201');
    $apiClientPublicId1 = (string)($apiClientCreate1['payload']['data']['api_client']['public_id'] ?? '');
    $apiClientPublicId2 = (string)($apiClientCreate2['payload']['data']['api_client']['public_id'] ?? '');
    liveAssert($apiClientPublicId1 !== '' && $apiClientPublicId1 === $apiClientPublicId2, 'API client idempotency must return same public_id');
    liveAssert((bool)($apiClientCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'API client replay meta must be true');

    $webhookIdem = 'idem-webhook-' . $suffix;
    $webhookPayload = [
        'title' => 'Idem Webhook ' . $suffix,
        'endpoint' => 'https://example.com/idem-webhook/' . strtolower($suffix),
        'events' => ['task_created'],
    ];
    $webhookCreate1 = liveRequest('POST', 'api/v1/webhooks', $webhookPayload, array_merge($headers, [
        'X-Idempotency-Key' => $webhookIdem,
    ]));
    $webhookCreate2 = liveRequest('POST', 'api/v1/webhooks', $webhookPayload, array_merge($headers, [
        'X-Idempotency-Key' => $webhookIdem,
    ]));
    liveAssert($webhookCreate1['status'] === 201 && $webhookCreate2['status'] === 201, 'Webhook idempotency calls must return 201');
    $webhookPublicId1 = (string)($webhookCreate1['payload']['data']['webhook']['public_id'] ?? '');
    $webhookPublicId2 = (string)($webhookCreate2['payload']['data']['webhook']['public_id'] ?? '');
    liveAssert($webhookPublicId1 !== '' && $webhookPublicId1 === $webhookPublicId2, 'Webhook idempotency must return same public_id');
    liveAssert((bool)($webhookCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Webhook replay meta must be true');

    liveRequest('DELETE', 'api/v1/webhooks/' . $webhookPublicId1, [], $headers);
    liveRequest('DELETE', 'api/v1/api-clients/' . $apiClientPublicId1, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);

    echo "[OK] advanced_idempotency_locking_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_idempotency_locking_live: ' . $e->getMessage() . "\n");
    exit(1);
}

