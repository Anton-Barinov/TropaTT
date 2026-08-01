<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $targetLogin = 'imp_bound_' . $suffix;
    $targetToken = 'imp-bound-token-' . $suffix;

    $targetCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $targetLogin,
        'password' => 'ImpBound123!',
        'token' => $targetToken,
        'email' => $targetLogin . '@crm.local',
    ], $rootHeaders);
    liveAssert($targetCreate['status'] === 201, 'Target user create must return 201');
    $targetPublicId = (string)($targetCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($targetPublicId !== '', 'Target user public_id is required');

    // 1) repeat start/stop and access after stop
    $start1 = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'Impersonation session boundaries #1',
    ], $rootHeaders);
    liveAssert($start1['status'] === 200, 'First impersonation start must return 200');
    $impToken1 = (string)($start1['payload']['data']['impersonation_access_token'] ?? '');
    $audit1 = (string)($start1['payload']['data']['audit']['public_id'] ?? '');
    liveAssert($impToken1 !== '', 'First impersonation token is required');
    liveAssert($audit1 !== '', 'First impersonation audit id is required');

    $start1Again = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'Impersonation session boundaries #1 duplicate',
    ], $rootHeaders);
    liveAssert($start1Again['status'] === 409, 'Second impersonation start must return 409');
    liveAssert((string)($start1Again['payload']['code'] ?? '') === 'IMPERSONATION_ALREADY_ACTIVE', 'Second impersonation start code mismatch');

    $stop1 = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $audit1,
    ], $rootHeaders);
    liveAssert($stop1['status'] === 200, 'First impersonation stop must return 200');

    $impHeaders1 = ['Authorization' => 'Bearer ' . $impToken1];
    $statusAfterStop1 = liveRequest('GET', 'api/v1/security/impersonation/status', [], $impHeaders1);
    liveAssert($statusAfterStop1['status'] === 401, 'Impersonated token must be unauthorized after stop');

    $stop1Again = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $audit1,
    ], $rootHeaders);
    liveAssert($stop1Again['status'] === 409, 'Second impersonation stop must return 409');
    liveAssert((string)($stop1Again['payload']['code'] ?? '') === 'IMPERSONATION_ALREADY_STOPPED', 'Second impersonation stop code mismatch');

    // 2) race A: manual session revoke first, then stop by audit id
    $start2 = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'Impersonation session boundaries #2',
    ], $rootHeaders);
    liveAssert($start2['status'] === 200, 'Second impersonation start must return 200');
    $impToken2 = (string)($start2['payload']['data']['impersonation_access_token'] ?? '');
    $audit2 = (string)($start2['payload']['data']['audit']['public_id'] ?? '');
    $session2 = (string)($start2['payload']['data']['session_public_id'] ?? '');
    liveAssert($impToken2 !== '' && $audit2 !== '' && $session2 !== '', 'Second impersonation payload must contain token/audit/session');

    $manualRevoke2 = liveRequest('DELETE', 'api/v1/security/sessions/' . $session2, [], $rootHeaders);
    liveAssert($manualRevoke2['status'] === 200, 'Manual revoke of impersonated session must return 200');

    $stop2 = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $audit2,
    ], $rootHeaders);
    liveAssert($stop2['status'] === 200, 'Stop after manual session revoke must return 200');

    $impHeaders2 = ['Authorization' => 'Bearer ' . $impToken2];
    $statusAfterStop2 = liveRequest('GET', 'api/v1/security/impersonation/status', [], $impHeaders2);
    liveAssert($statusAfterStop2['status'] === 401, 'Impersonated token must be unauthorized after stop (race A)');

    // 3) race B: stop first, then manual session revoke
    $start3 = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'Impersonation session boundaries #3',
    ], $rootHeaders);
    liveAssert($start3['status'] === 200, 'Third impersonation start must return 200');
    $impToken3 = (string)($start3['payload']['data']['impersonation_access_token'] ?? '');
    $audit3 = (string)($start3['payload']['data']['audit']['public_id'] ?? '');
    $session3 = (string)($start3['payload']['data']['session_public_id'] ?? '');
    liveAssert($impToken3 !== '' && $audit3 !== '' && $session3 !== '', 'Third impersonation payload must contain token/audit/session');

    $stop3 = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $audit3,
    ], $rootHeaders);
    liveAssert($stop3['status'] === 200, 'Stop before manual session revoke must return 200');

    $manualRevoke3 = liveRequest('DELETE', 'api/v1/security/sessions/' . $session3, [], $rootHeaders);
    liveAssert($manualRevoke3['status'] === 404, 'Manual revoke after stop must return 404');
    liveAssert((string)($manualRevoke3['payload']['code'] ?? '') === 'SESSION_NOT_FOUND', 'Manual revoke after stop code must be SESSION_NOT_FOUND');

    $impHeaders3 = ['Authorization' => 'Bearer ' . $impToken3];
    $statusAfterStop3 = liveRequest('GET', 'api/v1/security/impersonation/status', [], $impHeaders3);
    liveAssert($statusAfterStop3['status'] === 401, 'Impersonated token must be unauthorized after stop (race B)');

    liveRequest('DELETE', 'api/v1/users/' . $targetPublicId, [], $rootHeaders);

    echo "[OK] advanced_impersonation_session_boundaries_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_impersonation_session_boundaries_live: ' . $e->getMessage() . "\n");
    exit(1);
}

