<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runSecurity2faSmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $currentPassword = testRootPasswordUsed();

    $disableBefore = request('POST', '/api/v1/security/2fa/disable', [
        'current_password' => $currentPassword,
    ], $headers);
    if (($disableBefore['status'] ?? 0) !== 200) {
        assertTrue(($disableBefore['status'] ?? 0) === 409, 'Disable before enable must be 409 or 200');
    }

    $statusBefore = request('GET', '/api/v1/security/2fa/status', [], $headers);
    assertTrue($statusBefore['status'] === 200, '2FA status before enable must be 200');
    assertTrue(($statusBefore['payload']['data']['enabled'] ?? true) === false, '2FA must be disabled initially');

    $enable = request('POST', '/api/v1/security/2fa/enable', [
        'current_password' => $currentPassword,
    ], $headers);
    assertTrue($enable['status'] === 200, '2FA enable must be 200');
    $setupCode = (string)($enable['payload']['data']['setup_code'] ?? '');
    $recoveryCodes = (array)($enable['payload']['data']['recovery_codes'] ?? []);
    assertTrue($setupCode !== '', '2FA setup_code is required');
    assertTrue(count($recoveryCodes) >= 1, '2FA recovery codes are required');

    $statusEnabled = request('GET', '/api/v1/security/2fa/status', [], $headers);
    assertTrue($statusEnabled['status'] === 200, '2FA status after enable must be 200');
    assertTrue(($statusEnabled['payload']['data']['enabled'] ?? false) === true, '2FA must be enabled');

    $disable = request('POST', '/api/v1/security/2fa/disable', [
        'current_password' => $currentPassword,
    ], $headers);
    assertTrue($disable['status'] === 200, '2FA disable must be 200');

    $statusDisabled = request('GET', '/api/v1/security/2fa/status', [], $headers);
    assertTrue($statusDisabled['status'] === 200, '2FA status after disable must be 200');
    assertTrue(($statusDisabled['payload']['data']['enabled'] ?? true) === false, '2FA must be disabled after disable');

    $unauthorized = request('GET', '/api/v1/security/2fa/status');
    assertTrue($unauthorized['status'] === 401, '2FA status without token must be 401');
}

runSecurity2faSmoke();
echo "[OK] security_2fa_smoke\n";
