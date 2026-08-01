<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runPermissionsNegativeIntegrationsLive(): void
{
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'neg_int_' . strtolower($suffix),
        'title' => 'Negative Integrations ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'neg_int_user_' . strtolower($suffix);
    $token = 'neg-int-token-' . strtolower($suffix);
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'NegIntPass123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'NegIntPass123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    $apiClientsList = liveRequest('GET', 'api/v1/api-clients', [], $userHeaders);
    liveAssert($apiClientsList['status'] === 403, 'api-clients list must be forbidden without api_client.view');

    $webhooksList = liveRequest('GET', 'api/v1/webhooks', [], $userHeaders);
    liveAssert($webhooksList['status'] === 403, 'webhooks list must be forbidden without webhook.manage');

    $invitationsList = liveRequest('GET', 'api/v1/security/invitations', [], $userHeaders);
    liveAssert($invitationsList['status'] === 403, 'invitations list must be forbidden without user.manage');

    $businessCalendarList = liveRequest('GET', 'api/v1/calendar/business', [], $userHeaders);
    liveAssert($businessCalendarList['status'] === 403, 'calendar/business must be forbidden without settings.manage');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);
}

runPermissionsNegativeIntegrationsLive();
echo "[OK] permissions_negative_integrations_live\n";
