<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runRbacHierarchyFeatureLive(): void
{
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'live_rbac_' . strtolower($suffix),
        'title' => 'Live RBAC ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view', 'user.manage'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must be 200');

    $loginA = 'live_a_' . strtolower($suffix);
    $tokenA = 'live-token-a-' . strtolower($suffix);
    $userA = liveRequest('POST', 'api/v1/users', [
        'login' => $loginA,
        'password' => 'LivePassA123!',
        'token' => $tokenA,
        'email' => $loginA . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userA['status'] === 201, 'User A create must be 201');
    $userAPublicId = (string)($userA['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userAPublicId !== '', 'User A public_id is required');

    $loginAResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $loginA,
        'password' => 'LivePassA123!',
        'token' => $tokenA,
    ]);
    liveAssert($loginAResp['status'] === 200, 'User A login must be 200');
    $aToken = (string)($loginAResp['payload']['data']['access_token'] ?? '');
    liveAssert($aToken !== '', 'User A token is required');
    $aHeaders = ['Authorization' => 'Bearer ' . $aToken];

    $loginB = 'live_b_' . strtolower($suffix);
    $tokenB = 'live-token-b-' . strtolower($suffix);
    $userB = liveRequest('POST', 'api/v1/users', [
        'login' => $loginB,
        'password' => 'LivePassB123!',
        'token' => $tokenB,
        'email' => $loginB . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $aHeaders);
    liveAssert($userB['status'] === 201, 'User B create by user A must be 201');
    $userBPublicId = (string)($userB['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userBPublicId !== '', 'User B public_id is required');

    $loginBResp = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $loginB,
        'password' => 'LivePassB123!',
        'token' => $tokenB,
    ]);
    liveAssert($loginBResp['status'] === 200, 'User B login must be 200');
    $bToken = (string)($loginBResp['payload']['data']['access_token'] ?? '');
    liveAssert($bToken !== '', 'User B token is required');
    $bHeaders = ['Authorization' => 'Bearer ' . $bToken];

    $bReadATokens = liveRequest('GET', 'api/v1/users/' . $userAPublicId . '/tokens', [], $bHeaders);
    liveAssert($bReadATokens['status'] === 403, 'Descendant must not access ancestor tokens');
    liveAssert((string)($bReadATokens['payload']['code'] ?? '') === 'FORBIDDEN_HIERARCHY', 'Hierarchy deny code must be FORBIDDEN_HIERARCHY');

    liveRequest('DELETE', 'api/v1/users/' . $userBPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $userAPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);
}

runRbacHierarchyFeatureLive();
echo "[OK] rbac_hierarchy_feature_live\n";
