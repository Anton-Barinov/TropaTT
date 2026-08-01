<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Advanced Write Consistency ' . $suffix,
        'description' => 'project for approval consistency',
    ], $headers);
    liveAssert($projectCreate['status'] === 201, 'Project create must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'title' => 'Advanced Write Task ' . $suffix,
        'project_public_id' => $projectPublicId,
    ], $headers);
    liveAssert($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $workflowIdemKey = 'idem-workflow-' . $suffix;
    $workflowPayload = [
        'title' => 'Workflow ' . $suffix,
        'trigger_code' => 'task_created',
        'action_code' => 'send_notification',
        'payload' => ['channel' => 'in_app'],
    ];
    $workflowCreate1 = liveRequest('POST', 'api/v1/workflow/rules', $workflowPayload, array_merge($headers, [
        'X-Idempotency-Key' => $workflowIdemKey,
    ]));
    $workflowCreate2 = liveRequest('POST', 'api/v1/workflow/rules', $workflowPayload, array_merge($headers, [
        'X-Idempotency-Key' => $workflowIdemKey,
    ]));
    liveAssert($workflowCreate1['status'] === 201 && $workflowCreate2['status'] === 201, 'Workflow idempotency calls must return 201');
    $workflowPublicId1 = (string)($workflowCreate1['payload']['data']['rule']['public_id'] ?? '');
    $workflowPublicId2 = (string)($workflowCreate2['payload']['data']['rule']['public_id'] ?? '');
    liveAssert($workflowPublicId1 !== '' && $workflowPublicId1 === $workflowPublicId2, 'Workflow idempotency must return same public_id');
    liveAssert((bool)($workflowCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Workflow replay meta must be true');

    $slaIdemKey = 'idem-sla-' . $suffix;
    $slaPayload = [
        'title' => 'SLA ' . $suffix,
        'response_minutes' => 60,
        'resolve_minutes' => 240,
        'escalation_payload' => ['channel' => 'email'],
    ];
    $slaCreate1 = liveRequest('POST', 'api/v1/sla/policies', $slaPayload, array_merge($headers, [
        'X-Idempotency-Key' => $slaIdemKey,
    ]));
    $slaCreate2 = liveRequest('POST', 'api/v1/sla/policies', $slaPayload, array_merge($headers, [
        'X-Idempotency-Key' => $slaIdemKey,
    ]));
    liveAssert($slaCreate1['status'] === 201 && $slaCreate2['status'] === 201, 'SLA idempotency calls must return 201');
    $slaPublicId1 = (string)($slaCreate1['payload']['data']['policy']['public_id'] ?? '');
    $slaPublicId2 = (string)($slaCreate2['payload']['data']['policy']['public_id'] ?? '');
    liveAssert($slaPublicId1 !== '' && $slaPublicId1 === $slaPublicId2, 'SLA idempotency must return same public_id');
    liveAssert((bool)($slaCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'SLA replay meta must be true');

    $importIdemKey = 'idem-import-' . $suffix;
    $importPayload = [
        'type' => 'projects',
        'format' => 'json_rows',
        'rows' => [
            ['title' => 'Import project ' . $suffix, 'description' => 'import idempotency'],
        ],
    ];
    $importCreate1 = liveRequest('POST', 'api/v1/import/jobs', $importPayload, array_merge($headers, [
        'X-Idempotency-Key' => $importIdemKey,
    ]));
    $importCreate2 = liveRequest('POST', 'api/v1/import/jobs', $importPayload, array_merge($headers, [
        'X-Idempotency-Key' => $importIdemKey,
    ]));
    liveAssert($importCreate1['status'] === 201 && $importCreate2['status'] === 201, 'Import idempotency calls must return 201');
    $importPublicId1 = (string)($importCreate1['payload']['data']['job']['public_id'] ?? '');
    $importPublicId2 = (string)($importCreate2['payload']['data']['job']['public_id'] ?? '');
    liveAssert($importPublicId1 !== '' && $importPublicId1 === $importPublicId2, 'Import idempotency must return same public_id');
    liveAssert((bool)($importCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Import replay meta must be true');

    $exportIdemKey = 'idem-export-' . $suffix;
    $exportPayload = [
        'type' => 'projects',
    ];
    $exportCreate1 = liveRequest('POST', 'api/v1/export/jobs', $exportPayload, array_merge($headers, [
        'X-Idempotency-Key' => $exportIdemKey,
    ]));
    $exportCreate2 = liveRequest('POST', 'api/v1/export/jobs', $exportPayload, array_merge($headers, [
        'X-Idempotency-Key' => $exportIdemKey,
    ]));
    liveAssert($exportCreate1['status'] === 201 && $exportCreate2['status'] === 201, 'Export idempotency calls must return 201');
    $exportPublicId1 = (string)($exportCreate1['payload']['data']['job']['public_id'] ?? '');
    $exportPublicId2 = (string)($exportCreate2['payload']['data']['job']['public_id'] ?? '');
    liveAssert($exportPublicId1 !== '' && $exportPublicId1 === $exportPublicId2, 'Export idempotency must return same public_id');
    liveAssert((bool)($exportCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Export replay meta must be true');

    $webhookIdemKey = 'idem-webhook-advanced-' . $suffix;
    $webhookPayload = [
        'title' => 'Webhook advanced ' . $suffix,
        'endpoint' => 'https://example.com/advanced-webhook/' . strtolower($suffix),
        'events' => ['task_created'],
    ];
    $webhookCreate1 = liveRequest('POST', 'api/v1/webhooks', $webhookPayload, array_merge($headers, [
        'X-Idempotency-Key' => $webhookIdemKey,
    ]));
    $webhookCreate2 = liveRequest('POST', 'api/v1/webhooks', $webhookPayload, array_merge($headers, [
        'X-Idempotency-Key' => $webhookIdemKey,
    ]));
    liveAssert($webhookCreate1['status'] === 201 && $webhookCreate2['status'] === 201, 'Webhook idempotency calls must return 201');
    $webhookPublicId1 = (string)($webhookCreate1['payload']['data']['webhook']['public_id'] ?? '');
    $webhookPublicId2 = (string)($webhookCreate2['payload']['data']['webhook']['public_id'] ?? '');
    liveAssert($webhookPublicId1 !== '' && $webhookPublicId1 === $webhookPublicId2, 'Webhook idempotency must return same public_id');
    liveAssert((bool)($webhookCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Webhook replay meta must be true');

    $approvalIdemKey = 'idem-approval-' . $suffix;
    $approvalPayload = [
        'entity_type' => 'task',
        'entity_public_id' => $taskPublicId,
        'reviewer_public_ids' => [$root['user_public_id']],
        'comment' => 'Advanced approval idempotency',
    ];
    $approvalCreate1 = liveRequest('POST', 'api/v1/approvals', $approvalPayload, array_merge($headers, [
        'X-Idempotency-Key' => $approvalIdemKey,
    ]));
    $approvalCreate2 = liveRequest('POST', 'api/v1/approvals', $approvalPayload, array_merge($headers, [
        'X-Idempotency-Key' => $approvalIdemKey,
    ]));
    liveAssert($approvalCreate1['status'] === 201 && $approvalCreate2['status'] === 201, 'Approval idempotency calls must return 201');
    $approvalPublicId1 = (string)($approvalCreate1['payload']['data']['approval']['public_id'] ?? '');
    $approvalPublicId2 = (string)($approvalCreate2['payload']['data']['approval']['public_id'] ?? '');
    liveAssert($approvalPublicId1 !== '' && $approvalPublicId1 === $approvalPublicId2, 'Approval idempotency must return same public_id');
    liveAssert((bool)($approvalCreate2['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Approval replay meta must be true');

    // optimistic-locking-like invariant for approval processing: repeated action must conflict
    $approve1 = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId1 . '/approve', [
        'comment' => 'First approve',
    ], $headers);
    liveAssert($approve1['status'] === 200, 'First approval must return 200');

    $approve2 = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId1 . '/approve', [
        'comment' => 'Repeated approve',
    ], $headers);
    liveAssert($approve2['status'] === 409, 'Repeated approval must return 409');
    $approve2Code = (string)($approve2['payload']['code'] ?? '');
    liveAssert(
        in_array($approve2Code, ['APPROVAL_FINALIZED', 'APPROVAL_STEP_ALREADY_PROCESSED'], true),
        'Repeated approval conflict code mismatch'
    );

    liveRequest('DELETE', 'api/v1/webhooks/' . $webhookPublicId1, [], $headers);
    liveRequest('DELETE', 'api/v1/workflow/rules/' . $workflowPublicId1, [], $headers);
    liveRequest('DELETE', 'api/v1/sla/policies/' . $slaPublicId1, [], $headers);
    liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $headers);

    echo "[OK] advanced_write_consistency_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_write_consistency_live: ' . $e->getMessage() . "\n");
    exit(1);
}
