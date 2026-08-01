<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];

    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));
    $email = 'recovery.e2e.' . $suffix . '@crm.local';
    $login = 'recovery_e2e_' . $suffix;
    $initialPassword = 'RecoveryInit123!';
    $resetPassword = 'RecoveryReset123!';
    $acceptedUserPublicId = '';
    $userTokenFactor = '';
    $activeUserHeaders = [];

    $invitationCreate = liveRequest('POST', 'api/v1/security/invitations', [
        'email' => $email,
    ], $rootHeaders);
    liveAssert($invitationCreate['status'] === 201, 'Invitation create must return 201');
    liveAssert((string)($invitationCreate['payload']['code'] ?? '') === 'INVITATION_CREATED', 'Invitation create code mismatch');
    $invitationToken = (string)($invitationCreate['payload']['data']['accept_token'] ?? '');
    liveAssert($invitationToken !== '', 'Invitation accept_token is required');

    $accept = liveRequest('POST', 'api/v1/security/invitations/accept', [
        'invitation_token' => $invitationToken,
        'login' => $login,
        'full_name' => 'Recovery E2E ' . $suffix,
        'password' => $initialPassword,
        'locale' => 'en-gb',
    ]);
    liveAssert($accept['status'] === 201, 'Invitation accept must return 201');
    liveAssert((string)($accept['payload']['code'] ?? '') === 'INVITATION_ACCEPTED', 'Invitation accept code mismatch');
    $acceptedUserPublicId = (string)($accept['payload']['data']['user']['public_id'] ?? '');
    $userTokenFactor = (string)($accept['payload']['data']['user_token'] ?? '');
    liveAssert($acceptedUserPublicId !== '', 'Accepted user public_id is required');
    liveAssert($userTokenFactor !== '', 'Accepted user token-factor is required');

    $resetRequest = liveRequest('POST', 'api/v1/security/password-reset', [
        'identifier' => $login,
    ]);
    liveAssert($resetRequest['status'] === 200, 'Password reset request must return 200');
    liveAssert((string)($resetRequest['payload']['code'] ?? '') === 'PASSWORD_RESET_REQUESTED', 'Password reset request code mismatch');
    $resetToken = (string)($resetRequest['payload']['data']['reset_token'] ?? '');
    liveAssert($resetToken !== '', 'Password reset token is required');

    $resetConfirm = liveRequest('POST', 'api/v1/security/password-reset/confirm', [
        'reset_token' => $resetToken,
        'new_password' => $resetPassword,
    ]);
    liveAssert($resetConfirm['status'] === 200, 'Password reset confirm must return 200');
    liveAssert((string)($resetConfirm['payload']['code'] ?? '') === 'PASSWORD_RESET_COMPLETED', 'Password reset confirm code mismatch');

    $loginPrimary = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => $resetPassword,
        'token' => $userTokenFactor,
    ]);
    liveAssert($loginPrimary['status'] === 200, 'Primary login after reset must return 200');
    $primaryAccessToken = (string)($loginPrimary['payload']['data']['access_token'] ?? '');
    liveAssert($primaryAccessToken !== '', 'Primary access token is required');
    $primaryHeaders = ['Authorization' => 'Bearer ' . $primaryAccessToken];
    $activeUserHeaders = $primaryHeaders;

    $twoFactorEnable = liveRequest('POST', 'api/v1/security/2fa/enable', [
        'current_password' => $resetPassword,
    ], $primaryHeaders);
    liveAssert($twoFactorEnable['status'] === 200, '2FA enable must return 200');
    liveAssert((string)($twoFactorEnable['payload']['code'] ?? '') === 'TWO_FACTOR_ENABLED', '2FA enable code mismatch');
    liveAssert((string)($twoFactorEnable['payload']['data']['setup_code'] ?? '') !== '', '2FA setup_code is required');
    liveAssert(count((array)($twoFactorEnable['payload']['data']['recovery_codes'] ?? [])) >= 1, '2FA recovery codes are required');

    $twoFactorStatusPrimary = liveRequest('GET', 'api/v1/security/2fa/status', [], $primaryHeaders);
    liveAssert($twoFactorStatusPrimary['status'] === 200, '2FA status after enable must return 200');
    liveAssert((bool)($twoFactorStatusPrimary['payload']['data']['enabled'] ?? false) === true, '2FA must be enabled after enable');

    $loginSecondary = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => $resetPassword,
        'token' => $userTokenFactor,
    ]);
    liveAssert($loginSecondary['status'] === 200, 'Secondary login must return 200');
    $secondaryAccessToken = (string)($loginSecondary['payload']['data']['access_token'] ?? '');
    liveAssert($secondaryAccessToken !== '', 'Secondary access token is required');
    $secondaryHeaders = ['Authorization' => 'Bearer ' . $secondaryAccessToken];

    $sessionsBeforeRevoke = liveRequest('GET', 'api/v1/security/sessions', [], $primaryHeaders);
    liveAssert($sessionsBeforeRevoke['status'] === 200, 'Session list before revoke must return 200');
    liveAssert(count((array)($sessionsBeforeRevoke['payload']['data']['items'] ?? [])) >= 2, 'Recovery chain must create at least two active sessions');

    $revokeOthers = liveRequest('POST', 'api/v1/security/sessions/revoke-others', [], $primaryHeaders);
    liveAssert($revokeOthers['status'] === 200, 'Revoke other sessions must return 200');
    liveAssert((string)($revokeOthers['payload']['code'] ?? '') === 'SESSION_REVOKE_OTHERS', 'Revoke other sessions code mismatch');
    liveAssert((int)($revokeOthers['payload']['data']['revoked_count'] ?? 0) >= 1, 'Revoke other sessions must revoke at least one session');

    $secondaryStatusAfterRevoke = liveRequest('GET', 'api/v1/security/2fa/status', [], $secondaryHeaders);
    liveAssert($secondaryStatusAfterRevoke['status'] === 401, 'Secondary session must be unauthorized after revoke-others');

    $primaryStatusAfterRevoke = liveRequest('GET', 'api/v1/security/2fa/status', [], $primaryHeaders);
    liveAssert($primaryStatusAfterRevoke['status'] === 200, 'Primary session must remain active after revoke-others');
    liveAssert((bool)($primaryStatusAfterRevoke['payload']['data']['enabled'] ?? false) === true, '2FA must stay enabled on primary session');

    $relogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => $resetPassword,
        'token' => $userTokenFactor,
    ]);
    liveAssert($relogin['status'] === 200, 'Relogin after revoke-others must return 200');
    $reloginAccessToken = (string)($relogin['payload']['data']['access_token'] ?? '');
    liveAssert($reloginAccessToken !== '', 'Relogin access token is required');
    $reloginHeaders = ['Authorization' => 'Bearer ' . $reloginAccessToken];
    $activeUserHeaders = $reloginHeaders;

    $reloginStatus = liveRequest('GET', 'api/v1/security/2fa/status', [], $reloginHeaders);
    liveAssert($reloginStatus['status'] === 200, '2FA status after relogin must return 200');
    liveAssert((bool)($reloginStatus['payload']['data']['enabled'] ?? false) === true, '2FA must remain enabled after relogin');

    $sessionsAfterRelogin = liveRequest('GET', 'api/v1/security/sessions', [], $reloginHeaders);
    liveAssert($sessionsAfterRelogin['status'] === 200, 'Session list after relogin must return 200');
    liveAssert(count((array)($sessionsAfterRelogin['payload']['data']['items'] ?? [])) >= 2, 'Session list after relogin must include current and preserved session');

    $twoFactorDisable = liveRequest('POST', 'api/v1/security/2fa/disable', [
        'current_password' => $resetPassword,
    ], $reloginHeaders);
    liveAssert($twoFactorDisable['status'] === 200, '2FA disable in cleanup must return 200');
    liveAssert((string)($twoFactorDisable['payload']['code'] ?? '') === 'TWO_FACTOR_DISABLED', '2FA disable code mismatch');

    $activeUserHeaders = [];
    liveRequest('DELETE', 'api/v1/users/' . $acceptedUserPublicId, [], $rootHeaders);

    echo "[OK] security_recovery_e2e_live\n";
} catch (Throwable $e) {
    if (isset($activeUserHeaders) && $activeUserHeaders !== []) {
        liveRequest('POST', 'api/v1/security/2fa/disable', [
            'current_password' => $resetPassword ?? '',
        ], $activeUserHeaders);
    }

    if (($acceptedUserPublicId ?? '') !== '') {
        try {
            $root = $root ?? liveLoginRoot();
            liveRequest('DELETE', 'api/v1/users/' . $acceptedUserPublicId, [], [
                'Authorization' => 'Bearer ' . $root['token'],
            ]);
        }catch (Throwable $e) { error_log('[SecurityRecoveryE2E] ' . $e->getMessage()); }
    }

    fwrite(STDERR, '[FAIL] security_recovery_e2e_live: ' . $e->getMessage() . "\n");
    exit(1);
}
