<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $roleCode = 'uid_' . randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => $roleCode,
        'title' => 'User Identity Role ' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setPerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view', 'user.manage'],
    ], $rootHeaders);
    assertTrue($setPerms['status'] === 200, 'Role permission set status must be 200');

    $loginA = 'uid_a_' . randomSuffix();
    $tokenA = 'uid-token-a-' . randomSuffix();
    $userA = request('POST', '/api/v1/users', [
        'login' => $loginA,
        'password' => 'UidPassA123!',
        'token' => $tokenA,
        'email' => $loginA . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userA['status'] === 201, 'User A create status must be 201');
    $userAPublicId = (string)($userA['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userAPublicId !== '', 'User A public_id is required');

    $loginAResp = request('POST', '/api/v1/auth/login', [
        'login' => $loginA,
        'password' => 'UidPassA123!',
        'token' => $tokenA,
    ]);
    assertTrue($loginAResp['status'] === 200, 'User A login must be 200');
    $aHeaders = authHeaders((string)$loginAResp['payload']['data']['access_token']);

    $tokenInfo = request('GET', '/api/v1/users/' . $userAPublicId . '/tokens', [], $aHeaders);
    assertTrue($tokenInfo['status'] === 200, 'Token info must be 200');
    assertTrue(isset($tokenInfo['payload']['data']['token_factor']['has_token_factor']), 'Token factor info is required');

    $rotate = request('POST', '/api/v1/users/' . $userAPublicId . '/tokens/rotate', [], $aHeaders);
    assertTrue($rotate['status'] === 200, 'Token rotate must be 200');
    $newToken = (string)($rotate['payload']['data']['plain_token'] ?? '');
    assertTrue($newToken !== '', 'Rotated plain_token is required');

    $relogin = request('POST', '/api/v1/auth/login', [
        'login' => $loginA,
        'password' => 'UidPassA123!',
        'token' => $newToken,
    ]);
    assertTrue($relogin['status'] === 200, 'Relogin with rotated token must be 200');
    $reloginHeaders = authHeaders((string)$relogin['payload']['data']['access_token']);

    $activity = request('GET', '/api/v1/users/' . $userAPublicId . '/activity', [], $aHeaders);
    assertTrue($activity['status'] === 200, 'User activity must be 200');
    assertTrue(array_key_exists('request_logs', (array)($activity['payload']['data'] ?? [])), 'request_logs key is required');
    assertTrue(array_key_exists('security_logs', (array)($activity['payload']['data'] ?? [])), 'security_logs key is required');
    assertTrue(array_key_exists('audit_logs', (array)($activity['payload']['data'] ?? [])), 'audit_logs key is required');

    $revoke = request('DELETE', '/api/v1/users/' . $userAPublicId . '/tokens', [], $aHeaders);
    assertTrue($revoke['status'] === 200, 'Token revoke must be 200');

    $loginB = 'uid_b_' . randomSuffix();
    $tokenB = 'uid-token-b-' . randomSuffix();
    $userB = request('POST', '/api/v1/users', [
        'login' => $loginB,
        'password' => 'UidPassB123!',
        'token' => $tokenB,
        'email' => $loginB . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $aHeaders);
    assertTrue($userB['status'] === 201, 'User B create status must be 201');
    $userBPublicId = (string)($userB['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userBPublicId !== '', 'User B public_id is required');

    $loginBResp = request('POST', '/api/v1/auth/login', [
        'login' => $loginB,
        'password' => 'UidPassB123!',
        'token' => $tokenB,
    ]);
    assertTrue($loginBResp['status'] === 200, 'User B login must be 200');
    $bHeaders = authHeaders((string)$loginBResp['payload']['data']['access_token']);

    $bReadA = request('GET', '/api/v1/users/' . $userAPublicId . '/tokens', [], $bHeaders);
    assertTrue($bReadA['status'] === 403, 'Descendant must not read ancestor token info');

    $bActivityA = request('GET', '/api/v1/users/' . $userAPublicId . '/activity', [], $bHeaders);
    assertTrue($bActivityA['status'] === 403, 'Descendant must not read ancestor activity');

    request('POST', '/api/v1/auth/logout', [], $bHeaders);
    request('POST', '/api/v1/auth/logout', [], $reloginHeaders);
    request('POST', '/api/v1/auth/logout', [], $aHeaders);
    request('DELETE', '/api/v1/users/' . $userBPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/users/' . $userAPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);
    request('POST', '/api/v1/auth/logout', [], $rootHeaders);

    echo "User identity tokens/activity smoke: OK\n";
    echo "user_a_public_id={$userAPublicId}\n";
    echo "user_b_public_id={$userBPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "User identity tokens/activity smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
