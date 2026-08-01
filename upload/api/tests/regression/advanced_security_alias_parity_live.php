<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * @param array{status:int,payload:array<string,mixed>} $canonical
 * @param array{status:int,payload:array<string,mixed>} $alias
 */
function assertParity(array $canonical, array $alias, string $label): void
{
    liveAssert($canonical['status'] === $alias['status'], $label . ': status mismatch');
    liveAssert((string)($canonical['payload']['code'] ?? '') === (string)($alias['payload']['code'] ?? ''), $label . ': code mismatch');
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];

    // Unauthenticated parity
    $sessionsUnauthCanonical = liveRequest('GET', 'api/v1/security/sessions');
    $sessionsUnauthAlias = liveRequest('GET', 'api/v1/security/sessions/list');
    assertParity($sessionsUnauthCanonical, $sessionsUnauthAlias, 'sessions unauth');

    $invitationsUnauthCanonical = liveRequest('GET', 'api/v1/security/invitations');
    $invitationsUnauthAlias = liveRequest('GET', 'api/v1/security/invitations/list');
    assertParity($invitationsUnauthCanonical, $invitationsUnauthAlias, 'invitations list unauth');

    // Authenticated parity (root)
    $sessionsCanonical = liveRequest('GET', 'api/v1/security/sessions', [], $rootHeaders);
    $sessionsAlias = liveRequest('GET', 'api/v1/security/sessions/list', [], $rootHeaders);
    assertParity($sessionsCanonical, $sessionsAlias, 'sessions list root');

    $invitationsCanonical = liveRequest('GET', 'api/v1/security/invitations', [], $rootHeaders);
    $invitationsAlias = liveRequest('GET', 'api/v1/security/invitations/list', [], $rootHeaders);
    assertParity($invitationsCanonical, $invitationsAlias, 'invitations list root');

    $nonExistingPublicId = 'usr_NOT_EXISTING_ALIAS_PARITY';
    $sessionRevokeCanonical = liveRequest('DELETE', 'api/v1/security/sessions/' . $nonExistingPublicId, [], $rootHeaders);
    $sessionRevokeAlias = liveRequest('DELETE', 'api/v1/security/sessions/revoke/' . $nonExistingPublicId, [], $rootHeaders);
    assertParity($sessionRevokeCanonical, $sessionRevokeAlias, 'sessions revoke missing');

    $invGetCanonical = liveRequest('GET', 'api/v1/security/invitations/' . $nonExistingPublicId, [], $rootHeaders);
    $invGetAlias = liveRequest('GET', 'api/v1/security/invitations/get/' . $nonExistingPublicId, [], $rootHeaders);
    assertParity($invGetCanonical, $invGetAlias, 'invitations get missing');

    // Password reset aliases (public)
    $resetBadCanonical = liveRequest('POST', 'api/v1/security/password-reset', []);
    $resetBadAlias = liveRequest('POST', 'api/v1/security/password-reset/request', []);
    assertParity($resetBadCanonical, $resetBadAlias, 'password-reset validation');

    // Profile aliases (auth)
    $profileGetCanonical = liveRequest('GET', 'api/v1/profile/me', [], $rootHeaders);
    $profileGetAlias = liveRequest('GET', 'api/v1/profile/get', [], $rootHeaders);
    assertParity($profileGetCanonical, $profileGetAlias, 'profile get');

    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(2)));
    $profileUpdatePayload = ['full_name' => 'Root Alias ' . $suffix];
    $profileUpdateCanonical = liveRequest('PATCH', 'api/v1/profile/me', $profileUpdatePayload, $rootHeaders);
    $profileUpdateAlias = liveRequest('PATCH', 'api/v1/profile/update', $profileUpdatePayload, $rootHeaders);
    assertParity($profileUpdateCanonical, $profileUpdateAlias, 'profile update');

    $prefsGetCanonical = liveRequest('GET', 'api/v1/profile/preferences', [], $rootHeaders);
    $prefsGetAlias = liveRequest('GET', 'api/v1/profile/preferences/get', [], $rootHeaders);
    assertParity($prefsGetCanonical, $prefsGetAlias, 'profile preferences get');

    $prefsPayload = ['preferences' => ['dashboard_compact' => true]];
    $prefsSetCanonical = liveRequest('PATCH', 'api/v1/profile/preferences', $prefsPayload, $rootHeaders);
    $prefsSetAlias = liveRequest('PATCH', 'api/v1/profile/preferences/set', $prefsPayload, $rootHeaders);
    assertParity($prefsSetCanonical, $prefsSetAlias, 'profile preferences set');

    $changePwdPayload = [
        'current_password' => 'wrong-password',
        'new_password' => 'AliasParityPass123!',
    ];
    $changePwdCanonical = liveRequest('POST', 'api/v1/profile/change-password', $changePwdPayload, $rootHeaders);
    $changePwdAlias = liveRequest('POST', 'api/v1/profile/password/change', $changePwdPayload, $rootHeaders);
    assertParity($changePwdCanonical, $changePwdAlias, 'profile change-password invalid');

    // Permission parity: user without user.manage must get same forbidden for canonical/alias invitations list.
    $userSuffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(2)));
    $userLogin = 'sec_alias_' . $userSuffix;
    $userToken = 'sec-alias-token-' . $userSuffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $userLogin,
        'password' => 'SecAlias123!',
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'security alias parity: user create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'security alias parity: user public_id is required');

    $userLoginResponse = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $userLogin,
        'password' => 'SecAlias123!',
        'token' => $userToken,
    ]);
    liveAssert($userLoginResponse['status'] === 200, 'security alias parity: user login must return 200');
    $userAccessToken = (string)($userLoginResponse['payload']['data']['access_token'] ?? '');
    liveAssert($userAccessToken !== '', 'security alias parity: user access token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userAccessToken];

    $forbiddenCanonical = liveRequest('GET', 'api/v1/security/invitations', [], $userHeaders);
    $forbiddenAlias = liveRequest('GET', 'api/v1/security/invitations/list', [], $userHeaders);
    assertParity($forbiddenCanonical, $forbiddenAlias, 'invitations forbidden parity');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);

    echo "[OK] advanced_security_alias_parity_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_security_alias_parity_live: ' . $e->getMessage() . "\n");
    exit(1);
}

