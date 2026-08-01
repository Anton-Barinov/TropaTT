<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

function createManagedUser(array $rootHeaders, string $login, string $token, string $rolePublicId, string $locale = 'en-gb'): array
{
    $create = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'EdgeUser123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => $locale,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($create['status'] === 201, 'Managed user create must return 201');
    $publicId = (string)($create['payload']['data']['user']['public_id'] ?? '');
    liveAssert($publicId !== '', 'Managed user public_id is required');

    $loginResponse = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'EdgeUser123!',
        'token' => $token,
    ]);
    liveAssert($loginResponse['status'] === 200, 'Managed user login must return 200');
    $accessToken = (string)($loginResponse['payload']['data']['access_token'] ?? '');
    liveAssert($accessToken !== '', 'Managed user access token is required');

    return [
        'public_id' => $publicId,
        'headers' => ['Authorization' => 'Bearer ' . $accessToken],
    ];
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $createdUserIds = [];
    $rolePublicId = '';
    $workflowRuleId = '';
    $slaPolicyId = '';
    $projectPublicId = '';
    $taskPublicId = '';

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'edge_approval_' . $suffix,
        'title' => 'Edge Approval ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create for approval edges must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $rolePermissions = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['approval.manage'],
    ], $rootHeaders);
    liveAssert($rolePermissions['status'] === 200, 'Role permissions set must return 200');

    $reviewerOne = createManagedUser($rootHeaders, 'edge_rev1_' . $suffix, 'edge-rev1-' . $suffix, $rolePublicId);
    $reviewerTwo = createManagedUser($rootHeaders, 'edge_rev2_' . $suffix, 'edge-rev2-' . $suffix, $rolePublicId);
    $outsiderReviewer = createManagedUser($rootHeaders, 'edge_rev3_' . $suffix, 'edge-rev3-' . $suffix, $rolePublicId);

    $createdUserIds[] = $reviewerOne['public_id'];
    $createdUserIds[] = $reviewerTwo['public_id'];
    $createdUserIds[] = $outsiderReviewer['public_id'];

    // Workflow actions/retries edge: failed run followed by successful retry.
    $workflowCreate = liveRequest('POST', 'api/v1/workflow/rules', [
        'title' => 'Edge WF ' . $suffix,
        'trigger_code' => 'task_created',
        'action_code' => 'send_notification',
        'payload' => ['channel' => 'in_app'],
    ], $rootHeaders);
    liveAssert($workflowCreate['status'] === 201, 'Workflow create must return 201');
    $workflowRuleId = (string)($workflowCreate['payload']['data']['rule']['public_id'] ?? '');
    liveAssert($workflowRuleId !== '', 'Workflow rule public_id is required');

    $workflowInvalidPayload = liveRequest('PATCH', 'api/v1/workflow/rules/' . $workflowRuleId, [
        'payload' => 'not-an-object',
    ], $rootHeaders);
    liveAssert($workflowInvalidPayload['status'] === 422, 'Workflow payload type validation must return 422');

    $workflowRunFailed = liveRequest('POST', 'api/v1/workflow/rules/' . $workflowRuleId . '/run-test', [
        'simulate_error' => 1,
        'error_message' => 'edge failed run',
    ], $rootHeaders);
    liveAssert($workflowRunFailed['status'] === 201, 'Workflow failed run must return 201');

    $workflowRunRetry = liveRequest('POST', 'api/v1/workflow/rules/' . $workflowRuleId . '/run-test', [], $rootHeaders);
    liveAssert($workflowRunRetry['status'] === 201, 'Workflow retry run must return 201');

    $workflowRuns = liveRequest('GET', 'api/v1/workflow/runs', [], $rootHeaders);
    liveAssert($workflowRuns['status'] === 200, 'Workflow runs list must return 200');
    $runItems = (array)($workflowRuns['payload']['data']['items'] ?? []);
    $hasFailed = false;
    $hasSuccess = false;
    foreach ($runItems as $run) {
        if ((string)($run['rule_public_id'] ?? '') !== $workflowRuleId) {
            continue;
        }
        if ((string)($run['status'] ?? '') === 'failed') {
            $hasFailed = true;
        }
        if ((string)($run['status'] ?? '') === 'success') {
            $hasSuccess = true;
        }
    }
    liveAssert($hasFailed && $hasSuccess, 'Workflow runs must contain failed and success statuses');

    // SLA breach/escalation edge: overdue tasks reflected in report + escalation validation.
    $projectCreate = liveRequest('POST', 'api/v1/projects', [
        'title' => 'Edge SLA Project ' . $suffix,
    ], $rootHeaders);
    liveAssert($projectCreate['status'] === 201, 'Project create for SLA edge must return 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    liveAssert($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = liveRequest('POST', 'api/v1/tasks', [
        'project_public_id' => $projectPublicId,
        'title' => 'Edge SLA overdue task ' . $suffix,
        'due_at' => gmdate('Y-m-d H:i:s', time() - 7200),
    ], $rootHeaders);
    liveAssert($taskCreate['status'] === 201, 'Task create for SLA edge must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    liveAssert($taskPublicId !== '', 'Task public_id is required');

    $slaInvalidEscalation = liveRequest('POST', 'api/v1/sla/policies', [
        'title' => 'Edge SLA Invalid ' . $suffix,
        'response_minutes' => 10,
        'resolve_minutes' => 40,
        'escalation_payload' => 'invalid',
    ], $rootHeaders);
    liveAssert($slaInvalidEscalation['status'] === 422, 'SLA escalation payload validation must return 422');

    $slaCreate = liveRequest('POST', 'api/v1/sla/policies', [
        'title' => 'Edge SLA Valid ' . $suffix,
        'response_minutes' => 15,
        'resolve_minutes' => 60,
        'escalation_payload' => ['chain' => ['team_lead', 'cto'], 'mode' => 'hard'],
    ], $rootHeaders);
    liveAssert($slaCreate['status'] === 201, 'SLA create must return 201');
    $slaPolicyId = (string)($slaCreate['payload']['data']['policy']['public_id'] ?? '');
    liveAssert($slaPolicyId !== '', 'SLA policy public_id is required');

    $slaReport = liveRequest('GET', 'api/v1/sla/report', [], $rootHeaders);
    liveAssert($slaReport['status'] === 200, 'SLA report must return 200');
    liveAssert((int)($slaReport['payload']['data']['report']['tasks_overdue'] ?? 0) >= 1, 'SLA report must include overdue tasks >= 1');

    // Approvals multi-step edge.
    $approvalCreate = liveRequest('POST', 'api/v1/approvals', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_approval_edge_' . $suffix,
        'reviewer_public_ids' => [$reviewerOne['public_id'], $reviewerTwo['public_id']],
        'comment' => 'edge approval multi-step',
    ], $rootHeaders);
    liveAssert($approvalCreate['status'] === 201, 'Approval multi-step create must return 201');
    $approvalPublicId = (string)($approvalCreate['payload']['data']['approval']['public_id'] ?? '');
    liveAssert($approvalPublicId !== '', 'Approval public_id is required');

    $approveByReviewerOne = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId . '/approve', [
        'comment' => 'first step approved',
    ], $reviewerOne['headers']);
    liveAssert($approveByReviewerOne['status'] === 200, 'First reviewer approve must return 200');
    liveAssert((string)($approveByReviewerOne['payload']['data']['approval']['status'] ?? '') === 'pending', 'Approval must stay pending after first reviewer');

    $repeatApproveByReviewerOne = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId . '/approve', [
        'comment' => 'double approve',
    ], $reviewerOne['headers']);
    liveAssert($repeatApproveByReviewerOne['status'] === 409, 'Repeated same-step approve must return 409');
    liveAssert((string)($repeatApproveByReviewerOne['payload']['code'] ?? '') === 'APPROVAL_STEP_ALREADY_PROCESSED', 'Repeated same-step approve code mismatch');

    $outsiderApprove = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId . '/approve', [
        'comment' => 'outsider',
    ], $outsiderReviewer['headers']);
    liveAssert($outsiderApprove['status'] === 403, 'Outsider reviewer approve must return 403');
    liveAssert((string)($outsiderApprove['payload']['code'] ?? '') === 'APPROVAL_REVIEWER_FORBIDDEN', 'Outsider reviewer code mismatch');

    $rejectByReviewerTwo = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId . '/reject', [
        'comment' => 'final reject',
    ], $reviewerTwo['headers']);
    liveAssert($rejectByReviewerTwo['status'] === 200, 'Second reviewer reject must return 200');
    liveAssert((string)($rejectByReviewerTwo['payload']['data']['approval']['status'] ?? '') === 'rejected', 'Approval must be rejected after second reviewer');

    $approveAfterFinalize = liveRequest('POST', 'api/v1/approvals/' . $approvalPublicId . '/approve', [
        'comment' => 'after finalize',
    ], $reviewerOne['headers']);
    liveAssert($approveAfterFinalize['status'] === 409, 'Approve after finalized request must return 409');
    liveAssert((string)($approveAfterFinalize['payload']['code'] ?? '') === 'APPROVAL_FINALIZED', 'Approve after finalized code mismatch');

    if ($taskPublicId !== '') {
        liveRequest('DELETE', 'api/v1/tasks/' . $taskPublicId, [], $rootHeaders);
    }
    if ($projectPublicId !== '') {
        liveRequest('DELETE', 'api/v1/projects/' . $projectPublicId, [], $rootHeaders);
    }
    if ($slaPolicyId !== '') {
        liveRequest('DELETE', 'api/v1/sla/policies/' . $slaPolicyId, [], $rootHeaders);
    }
    if ($workflowRuleId !== '') {
        liveRequest('DELETE', 'api/v1/workflow/rules/' . $workflowRuleId, [], $rootHeaders);
    }
    foreach ($createdUserIds as $userPublicId) {
        liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    }
    if ($rolePublicId !== '') {
        liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);
    }

    echo "[OK] advanced_workflow_sla_approval_negative_edges_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_workflow_sla_approval_negative_edges_live: ' . $e->getMessage() . "\n");
    exit(1);
}
