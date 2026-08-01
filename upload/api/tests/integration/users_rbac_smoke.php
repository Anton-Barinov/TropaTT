<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $permissions = request('GET', '/api/v1/permissions', [], $headers);
    assertTrue($permissions['status'] === 200, 'Permissions list status must be 200');

    $rolesList = request('GET', '/api/v1/roles', [], $headers);
    assertTrue($rolesList['status'] === 200, 'Roles list status must be 200');

    $roleCode = 'smk_' . randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => $roleCode,
        'title' => 'Smoke Role ' . randomSuffix(),
    ], $headers);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setPermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $headers);
    assertTrue($setPermissions['status'] === 200, 'Role permission set status must be 200');

    $roleUpdate = request('PATCH', '/api/v1/roles/' . $rolePublicId, [
        'title' => 'Smoke Role Updated ' . randomSuffix(),
    ], $headers);
    assertTrue($roleUpdate['status'] === 200, 'Role update status must be 200');

    $userLogin = 'smoke_user_' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => 'SmkPass123!',
        'email' => $userLogin . '@crm.local',
        'full_name' => 'Smoke User',
        'token' => 'smoke-token-' . randomSuffix(),
        'role_public_ids' => [$rolePublicId],
    ], $headers);
    assertTrue($userCreate['status'] === 201, 'User create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'User public_id is required');

    $usersList = request('GET', '/api/v1/users', [], $headers);
    assertTrue($usersList['status'] === 200, 'Users list status must be 200');

    $userGet = request('GET', '/api/v1/users/' . $userPublicId, [], $headers);
    assertTrue($userGet['status'] === 200, 'User get status must be 200');

    $userUpdate = request('PATCH', '/api/v1/users/' . $userPublicId, [
        'full_name' => 'Smoke User Updated',
        'is_active' => 1,
        'role_public_ids' => [],
    ], $headers);
    assertTrue($userUpdate['status'] === 200, 'User update status must be 200');

    $userDelete = request('DELETE', '/api/v1/users/' . $userPublicId, [], $headers);
    assertTrue($userDelete['status'] === 200, 'User delete status must be 200');

    $roleDelete = request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $headers);
    assertTrue($roleDelete['status'] === 200, 'Role delete status must be 200');

    echo "Users/RBAC smoke: OK\n";
    echo "role_public_id={$rolePublicId}\n";
    echo "user_public_id={$userPublicId}\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Users/RBAC smoke FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
