<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function runSecurityRecoverySmoke(): void
{
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);
    $suffix = randomSuffix();

    $invite = request('POST', '/api/v1/security/invitations', [
        'email' => 'invite_' . $suffix . '@example.test',
    ], $headers);
    assertTrue($invite['status'] === 201, 'Invitation create status must be 201');
    $invitationPublicId = (string)($invite['payload']['data']['invitation']['public_id'] ?? '');
    $invitationToken = (string)($invite['payload']['data']['accept_token'] ?? '');
    assertTrue($invitationPublicId !== '', 'Invitation public_id required');
    assertTrue($invitationToken !== '', 'Invitation token required');

    $inviteList = request('GET', '/api/v1/security/invitations?search=' . rawurlencode($invitationPublicId), [], $headers);
    assertTrue($inviteList['status'] === 200, 'Invitation list status must be 200');

    $accept = request('POST', '/api/v1/security/invitations/accept', [
        'invitation_token' => $invitationToken,
        'login' => 'invite_' . $suffix,
        'full_name' => 'Invited ' . $suffix,
        'password' => 'InvitePass123!',
    ]);
    assertTrue($accept['status'] === 201, 'Invitation accept status must be 201');
    $userToken = (string)($accept['payload']['data']['user_token'] ?? '');
    assertTrue($userToken !== '', 'Accepted user token required');

    $invitedLogin = request('POST', '/api/v1/auth/login', [
        'login' => 'invite_' . $suffix,
        'password' => 'InvitePass123!',
        'token' => $userToken,
    ]);
    assertTrue($invitedLogin['status'] === 200, 'Invited user login must be 200');

    $resetRequest = request('POST', '/api/v1/security/password-reset', [
        'login' => 'invite_' . $suffix,
    ]);
    assertTrue($resetRequest['status'] === 200, 'Password reset request must be 200');
    assertTrue(($resetRequest['payload']['data']['accepted'] ?? false) === true, 'Password reset request must return accepted=true');
    assertTrue(!isset($resetRequest['payload']['data']['reset_token']), 'Password reset request must not return reset_token');

    $resetRequestUnknown = request('POST', '/api/v1/security/password-reset', [
        'login' => 'missing_' . $suffix,
    ]);
    assertTrue($resetRequestUnknown['status'] === 200, 'Password reset request for unknown login must be 200');
    assertTrue(($resetRequestUnknown['payload']['data']['accepted'] ?? false) === true, 'Unknown login reset must also return accepted=true');
    assertTrue(!isset($resetRequestUnknown['payload']['data']['reset_token']), 'Unknown login reset must not return reset_token');

    $unauthorized = request('GET', '/api/v1/security/invitations');
    assertTrue($unauthorized['status'] === 401, 'Invitation list without token must be 401');
}

runSecurityRecoverySmoke();
echo "[OK] security_recovery_smoke\n";
