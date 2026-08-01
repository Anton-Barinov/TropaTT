<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $customCreate = liveRequest('POST', 'api/v1/custom-fields', [
        'scope' => 'task',
        'code' => 'edge_' . strtolower($suffix),
        'title' => 'Edge Field ' . $suffix,
        'type' => 'text',
        'is_required' => 0,
    ], $headers);
    liveAssert($customCreate['status'] === 201, 'Custom field create must return 201');
    $customPublicId = (string)($customCreate['payload']['data']['field']['public_id'] ?? '');
    liveAssert($customPublicId !== '', 'Custom field public_id required');

    $setValues = liveRequest('POST', 'api/v1/custom-fields/values', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_edge_entity_' . strtolower($suffix),
        'values' => [
            $customPublicId => 'Value ' . $suffix,
        ],
    ], $headers);
    liveAssert($setValues['status'] === 200, 'Custom field values set must return 200');

    $workflowCreate = liveRequest('POST', 'api/v1/workflow/rules', [
        'title' => 'Edge Workflow ' . $suffix,
        'trigger_code' => 'task_created',
        'action_code' => 'send_notification',
        'payload' => ['channel' => 'in_app'],
    ], $headers);
    liveAssert($workflowCreate['status'] === 201, 'Workflow rule create must return 201');
    $workflowPublicId = (string)($workflowCreate['payload']['data']['rule']['public_id'] ?? '');
    liveAssert($workflowPublicId !== '', 'Workflow rule public_id required');

    $workflowRun = liveRequest('POST', 'api/v1/workflow/rules/' . $workflowPublicId . '/run-test', [
        'simulate_error' => 0,
    ], $headers);
    liveAssert($workflowRun['status'] === 201, 'Workflow run-test must return 201');

    $slaCreate = liveRequest('POST', 'api/v1/sla/policies', [
        'title' => 'Edge SLA ' . $suffix,
        'response_minutes' => 15,
        'resolve_minutes' => 120,
        'escalation_payload' => ['level' => 'team_lead'],
    ], $headers);
    liveAssert($slaCreate['status'] === 201, 'SLA create must return 201');
    $slaPublicId = (string)($slaCreate['payload']['data']['policy']['public_id'] ?? '');
    liveAssert($slaPublicId !== '', 'SLA public_id required');

    $approvalCreate = liveRequest('POST', 'api/v1/approvals', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_approval_' . strtolower($suffix),
        'reviewer_public_ids' => [$root['user_public_id']],
        'comment' => 'Edge approval flow',
    ], $headers);
    liveAssert($approvalCreate['status'] === 201, 'Approval create must return 201');
    $approvalPublicId = (string)($approvalCreate['payload']['data']['approval']['public_id'] ?? '');
    liveAssert($approvalPublicId !== '', 'Approval public_id required');

    $approvalApprove = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId . '/approve', [
        'comment' => 'Approved in feature test',
    ], $headers);
    liveAssert($approvalApprove['status'] === 200, 'Approval approve must return 200');
    liveAssert((string)($approvalApprove['payload']['code'] ?? '') === 'APPROVAL_APPROVED', 'Approval approve code mismatch');

    $workflowDelete = liveRequest('DELETE', 'api/v1/workflow/rules/' . $workflowPublicId, [], $headers);
    liveAssert($workflowDelete['status'] === 200, 'Workflow delete must return 200');

    $slaDelete = liveRequest('DELETE', 'api/v1/sla/policies/' . $slaPublicId, [], $headers);
    liveAssert($slaDelete['status'] === 200, 'SLA delete must return 200');

    $customDelete = liveRequest('DELETE', 'api/v1/custom-fields/' . $customPublicId, [], $headers);
    liveAssert($customDelete['status'] === 200, 'Custom field delete must return 200');

    echo "[OK] advanced_modules_feature_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_modules_feature_live: ' . $e->getMessage() . "\n");
    exit(1);
}
