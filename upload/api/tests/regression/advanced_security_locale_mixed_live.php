<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/** @param mixed $value */
function assertNoCyrillicSecurity(mixed $value, string $context): void
{
    if (is_string($value)) {
        liveAssert(!preg_match('/\p{Cyrillic}/u', $value), $context . ': value contains Cyrillic');
        return;
    }

    if (is_array($value)) {
        foreach ($value as $k => $v) {
            assertNoCyrillicSecurity($v, $context . '.' . (string)$k);
        }
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'security_locale_' . $suffix,
        'title' => 'Security Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $actorLogin = 'security_actor_' . $suffix;
    $actorToken = 'security-actor-token-' . $suffix;
    $actorCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $actorLogin,
        'password' => 'SecurityActor123!',
        'token' => $actorToken,
        'email' => $actorLogin . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($actorCreate['status'] === 201, 'Actor user create must return 201');
    $actorPublicId = (string)($actorCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($actorPublicId !== '', 'Actor user public_id is required');

    $actorLoginResponse = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $actorLogin,
        'password' => 'SecurityActor123!',
        'token' => $actorToken,
    ]);
    liveAssert($actorLoginResponse['status'] === 200, 'Actor login must return 200');
    $actorAccessToken = (string)($actorLoginResponse['payload']['data']['access_token'] ?? '');
    liveAssert($actorAccessToken !== '', 'Actor access token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $actorAccessToken,
        'X-Locale' => 'ru-ru',
    ];

    $profile = liveRequest('GET', 'api/v1/profile/get', [], $headers);
    liveAssert($profile['status'] === 200, 'Profile get must return 200');
    liveAssert((string)($profile['payload']['message'] ?? '') === 'Current user profile', 'Profile get message mismatch');

    $profileUpdate = liveRequest('PATCH', 'api/v1/profile/update', [
        'full_name' => 'Security Actor ' . $suffix,
        'timezone' => 'UTC',
    ], $headers);
    liveAssert($profileUpdate['status'] === 200, 'Profile update must return 200');
    liveAssert((string)($profileUpdate['payload']['message'] ?? '') === 'Profile updated', 'Profile update message mismatch');

    $preferencesValidation = liveRequest('PATCH', 'api/v1/profile/preferences/set', [
        'preferences' => 'invalid',
    ], $headers);
    liveAssert($preferencesValidation['status'] === 422, 'Profile preferences validation must return 422');
    liveAssert((string)($preferencesValidation['payload']['message'] ?? '') === 'Validation error', 'Profile preferences validation message mismatch');

    $preferencesSet = liveRequest('PATCH', 'api/v1/profile/preferences/set', [
        'preferences' => ['density' => 'compact', 'start_page' => 'dashboard'],
    ], $headers);
    liveAssert($preferencesSet['status'] === 200, 'Profile preferences set must return 200');
    liveAssert((string)($preferencesSet['payload']['message'] ?? '') === 'User preferences updated', 'Profile preferences set message mismatch');

    $preferencesGet = liveRequest('GET', 'api/v1/profile/preferences/get', [], $headers);
    liveAssert($preferencesGet['status'] === 200, 'Profile preferences get must return 200');
    liveAssert((string)($preferencesGet['payload']['message'] ?? '') === 'User preferences', 'Profile preferences get message mismatch');

    $changePasswordValidation = liveRequest('POST', 'api/v1/profile/password/change', [
        'current_password' => 'SecurityActor123!',
        'new_password' => 'short',
    ], $headers);
    liveAssert($changePasswordValidation['status'] === 422, 'Profile change password validation must return 422');
    liveAssert((string)($changePasswordValidation['payload']['message'] ?? '') === 'Validation error', 'Profile change password validation message mismatch');

    $sessions = liveRequest('GET', 'api/v1/security/sessions/list', [], $headers);
    liveAssert($sessions['status'] === 200, 'Session list alias must return 200');
    liveAssert((string)($sessions['payload']['message'] ?? '') === 'Session list', 'Session list message mismatch');

    $invitationList = liveRequest('GET', 'api/v1/security/invitations/list', [], $headers);
    liveAssert($invitationList['status'] === 200, 'Invitation list alias must return 200');
    liveAssert((string)($invitationList['payload']['message'] ?? '') === 'Invitation list', 'Invitation list message mismatch');

    $invitationCreateValidation = liveRequest('POST', 'api/v1/security/invitations/create', [
        'email' => 'not-an-email',
    ], $headers);
    liveAssert($invitationCreateValidation['status'] === 422, 'Invitation create validation must return 422');
    liveAssert((string)($invitationCreateValidation['payload']['message'] ?? '') === 'Validation error', 'Invitation create validation message mismatch');

    $invitationCreate = liveRequest('POST', 'api/v1/security/invitations/create', [
        'email' => 'invite_' . $suffix . '@crm.local',
        'role_public_id' => $rolePublicId,
    ], $headers);
    liveAssert($invitationCreate['status'] === 201, 'Invitation create must return 201');
    liveAssert((string)($invitationCreate['payload']['message'] ?? '') === 'Invitation created', 'Invitation create message mismatch');
    $invitationPublicId = (string)($invitationCreate['payload']['data']['invitation']['public_id'] ?? '');
    liveAssert($invitationPublicId !== '', 'Invitation public_id is required');

    $invitationGet = liveRequest('GET', 'api/v1/security/invitations/get/' . $invitationPublicId, [], $headers);
    liveAssert($invitationGet['status'] === 200, 'Invitation get alias must return 200');
    liveAssert((string)($invitationGet['payload']['message'] ?? '') === 'Invitation details', 'Invitation get message mismatch');

    $twoFactorStatus = liveRequest('GET', 'api/v1/security/2fa/status', [], $headers);
    liveAssert($twoFactorStatus['status'] === 200, 'Two-factor status must return 200');
    liveAssert((string)($twoFactorStatus['payload']['message'] ?? '') === '2FA status', 'Two-factor status message mismatch');

    $twoFactorEnable = liveRequest('POST', 'api/v1/security/2fa/enable', [
        'current_password' => 'SecurityActor123!',
    ], $headers);
    liveAssert($twoFactorEnable['status'] === 200, 'Two-factor enable must return 200');
    liveAssert((string)($twoFactorEnable['payload']['message'] ?? '') === '2FA enabled', 'Two-factor enable message mismatch');

    $twoFactorDisable = liveRequest('POST', 'api/v1/security/2fa/disable', [
        'current_password' => 'SecurityActor123!',
    ], $headers);
    liveAssert($twoFactorDisable['status'] === 200, 'Two-factor disable must return 200');
    liveAssert((string)($twoFactorDisable['payload']['message'] ?? '') === '2FA disabled', 'Two-factor disable message mismatch');

    $targetLogin = 'security_target_' . $suffix;
    $targetToken = 'security-target-token-' . $suffix;
    $targetCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $targetLogin,
        'password' => 'SecurityTarget123!',
        'token' => $targetToken,
        'email' => $targetLogin . '@crm.local',
        'locale' => 'ru-ru',
        'role_public_ids' => [$rolePublicId],
    ], $headers);
    liveAssert($targetCreate['status'] === 201, 'Target user create must return 201');
    $targetPublicId = (string)($targetCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($targetPublicId !== '', 'Target user public_id is required');

    $impersonationStart = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $targetPublicId,
        'reason' => 'locale regression ' . $suffix,
    ], $headers);
    liveAssert($impersonationStart['status'] === 200, 'Impersonation start must return 200');
    liveAssert((string)($impersonationStart['payload']['message'] ?? '') === 'Impersonation started', 'Impersonation start message mismatch');

    $impersonationStatus = liveRequest('GET', 'api/v1/security/impersonation/status', [], $headers);
    liveAssert($impersonationStatus['status'] === 200, 'Impersonation status must return 200');
    liveAssert((string)($impersonationStatus['payload']['message'] ?? '') === 'Impersonation status', 'Impersonation status message mismatch');

    $auditPublicId = (string)($impersonationStart['payload']['data']['audit']['public_id'] ?? '');
    liveAssert($auditPublicId !== '', 'Impersonation audit public_id is required');

    $impersonationStop = liveRequest('POST', 'api/v1/security/impersonation/stop', [
        'audit_public_id' => $auditPublicId,
    ], $headers);
    liveAssert($impersonationStop['status'] === 200, 'Impersonation stop must return 200');
    liveAssert((string)($impersonationStop['payload']['message'] ?? '') === 'Impersonation stopped', 'Impersonation stop message mismatch');

    $changePassword = liveRequest('POST', 'api/v1/profile/password/change', [
        'current_password' => 'SecurityActor123!',
        'new_password' => 'SecurityActor456!',
    ], $headers);
    liveAssert($changePassword['status'] === 200, 'Profile change password must return 200');
    liveAssert((string)($changePassword['payload']['message'] ?? '') === 'Password changed successfully', 'Profile change password message mismatch');

    assertNoCyrillicSecurity($preferencesValidation['payload']['errors'] ?? [], 'security.preferences.validation.errors');
    assertNoCyrillicSecurity($changePasswordValidation['payload']['errors'] ?? [], 'security.change_password.validation.errors');
    assertNoCyrillicSecurity($invitationCreateValidation['payload']['errors'] ?? [], 'security.invitation.validation.errors');

    liveRequest('DELETE', 'api/v1/users/' . $targetPublicId, [], $headers);
    liveRequest('DELETE', 'api/v1/users/' . $actorPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_security_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_security_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
