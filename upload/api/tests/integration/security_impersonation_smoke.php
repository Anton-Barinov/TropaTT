<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runSecurityImpersonationSmoke(): void
{
    $auth = loginRoot();
    $adminHeaders = authHeaders($auth['token']);

    $suffix = randomSuffix();
    $create = request('POST', '/api/v1/users', [
        'login' => 'imp_' . $suffix,
        'email' => 'imp_' . $suffix . '@crm.local',
        'password' => 'Imperson123!',
        'full_name' => 'Impersonation Smoke ' . $suffix,
        'locale' => 'ru-ru',
    ], $adminHeaders);
    assertTrue($create['status'] === 201, 'Impersonation target user create must be 201');
    $targetPublicId = (string)($create['payload']['data']['user']['public_id'] ?? '');
    assertTrue($targetPublicId !== '', 'Impersonation target user public_id is required');

    $statusBefore = request('GET', '/api/v1/security/impersonation/status', [], $adminHeaders);
    assertTrue($statusBefore['status'] === 200, 'Impersonation status before start must be 200');
    assertTrue(($statusBefore['payload']['data']['current']['active'] ?? false) === false, 'Current impersonation must be inactive before start');

    $start = request('POST', '/api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'integration smoke',
    ], $adminHeaders);
    assertTrue($start['status'] === 200, 'Impersonation start must be 200');
    $impToken = (string)($start['payload']['data']['impersonation_access_token'] ?? '');
    $auditPublicId = (string)($start['payload']['data']['audit']['public_id'] ?? '');
    assertTrue($impToken !== '', 'Impersonation access token is required');
    assertTrue($auditPublicId !== '', 'Impersonation audit public_id is required');

    $impHeaders = authHeaders($impToken);
    $meImpersonated = request('GET', '/api/v1/auth/me', [], $impHeaders);
    assertTrue($meImpersonated['status'] === 200, 'Impersonated auth/me must be 200');
    $meTargetPublicId = (string)($meImpersonated['payload']['data']['user']['public_id'] ?? '');
    assertTrue($meTargetPublicId === $targetPublicId, 'Impersonated auth/me must return target user');

    $statusDuring = request('GET', '/api/v1/security/impersonation/status', [], $impHeaders);
    assertTrue($statusDuring['status'] === 200, 'Impersonation status during run must be 200');
    assertTrue(($statusDuring['payload']['data']['current']['active'] ?? false) === true, 'Current impersonation must be active during run');

    $stop = request('POST', '/api/v1/security/impersonation/stop', [], $impHeaders);
    assertTrue($stop['status'] === 200, 'Impersonation stop by impersonated session must be 200');

    $statusAfter = request('GET', '/api/v1/security/impersonation/status', [], $impHeaders);
    assertTrue($statusAfter['status'] === 401, 'Impersonated token must be revoked after stop');

    $statusAdminAfter = request('GET', '/api/v1/security/impersonation/status', [], $adminHeaders);
    assertTrue($statusAdminAfter['status'] === 200, 'Admin impersonation status after stop must be 200');
    $activeByMe = (array)($statusAdminAfter['payload']['data']['active_started_by_me'] ?? []);
    assertTrue(count($activeByMe) === 0, 'No active impersonation sessions should remain after stop');
}

runSecurityImpersonationSmoke();
echo "[OK] security_impersonation_smoke\n";
