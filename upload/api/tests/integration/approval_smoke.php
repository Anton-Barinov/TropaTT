<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runApprovalSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $create = request('POST', '/api/v1/approvals', [
        'entity_type' => 'task',
        'entity_public_id' => 'tsk_approval_' . $suffix,
        'reviewer_public_ids' => [$auth['user_public_id']],
        'comment' => 'Need approval',
    ], $headers);
    assertTrue($create['status'] === 201, 'Approval create status must be 201');
    $approvalPublicId = (string)($create['payload']['data']['approval']['public_id'] ?? '');
    assertTrue($approvalPublicId !== '', 'Approval public_id is required');

    $list = request('GET', '/api/v1/approvals?search=' . rawurlencode($suffix), [], $headers);
    assertTrue($list['status'] === 200, 'Approval list status must be 200');

    $get = request('GET', '/api/v1/approvals/' . $approvalPublicId, [], $headers);
    assertTrue($get['status'] === 200, 'Approval get status must be 200');

    $approve = request('POST', '/api/v1/approvals/' . $approvalPublicId . '/approve', [
        'comment' => 'Approved',
    ], $headers);
    assertTrue($approve['status'] === 200, 'Approval approve status must be 200');
    assertTrue(($approve['payload']['data']['approval']['status'] ?? '') === 'approved', 'Approval status must be approved');

    $createReject = request('POST', '/api/v1/approval/request', [
        'entity_type' => 'project',
        'entity_public_id' => 'prj_approval_' . $suffix,
        'reviewer_public_ids' => [$auth['user_public_id']],
    ], $headers);
    assertTrue($createReject['status'] === 201, 'Approval alias request status must be 201');
    $rejectPublicId = (string)($createReject['payload']['data']['approval']['public_id'] ?? '');
    assertTrue($rejectPublicId !== '', 'Approval reject public_id is required');

    $reject = request('POST', '/api/v1/approval/reject/' . $rejectPublicId, [
        'comment' => 'Rejected',
    ], $headers);
    assertTrue($reject['status'] === 200, 'Approval reject status must be 200');
    assertTrue(($reject['payload']['data']['approval']['status'] ?? '') === 'rejected', 'Approval status must be rejected');

    $aliasList = request('GET', '/api/v1/approval/list?search=' . rawurlencode($suffix), [], $headers);
    assertTrue($aliasList['status'] === 200, 'Approval alias list status must be 200');

    $unauthorized = request('GET', '/api/v1/approvals');
    assertTrue($unauthorized['status'] === 401, 'Approval list without token must return 401');

    $invalid = request('POST', '/api/v1/approvals', [
        'entity_type' => 'task',
        'entity_public_id' => 'x',
        'reviewer_public_ids' => [],
    ], $headers);
    assertTrue($invalid['status'] === 422, 'Approval validation status must be 422');
}

runApprovalSmoke();
echo "[OK] approval_smoke\n";
