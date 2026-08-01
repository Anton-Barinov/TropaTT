<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $roleCode = 'web_restricted_' . randomSuffix();
    $createRole = request('POST', '/api/v1/roles', [
        'code' => $roleCode,
        'title' => 'Web restricted role ' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($createRole['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($createRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setPerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    assertTrue($setPerms['status'] === 200, 'Role permissions set must return 200');

    $suffix = randomSuffix();
    $login = 'web.restricted.' . $suffix;
    $password = 'WebRestricted#2026!';
    $token = 'web-restricted-token-' . $suffix;

    $createUser = request('POST', '/api/v1/users', [
        'login' => $login,
        'password' => $password,
        'email' => $login . '@crm.local',
        'full_name' => 'Web Restricted User',
        'token' => $token,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($createUser['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($createUser['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'User public_id is required');

    $auth = request('POST', '/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'token' => $token,
    ]);
    assertTrue($auth['status'] === 200, 'Restricted user login must return 200');
    $accessToken = (string)($auth['payload']['data']['access_token'] ?? '');
    assertTrue($accessToken !== '', 'Restricted user access token is required');

    $restrictedHeaders = authHeaders($accessToken);

    $tasksList = request('GET', '/api/v1/tasks', [], $restrictedHeaders);
    assertTrue($tasksList['status'] === 200, 'Restricted role with task.manage must access tasks list');

    $aiProvidersForbidden = request('GET', '/api/v1/ai/providers', [], $restrictedHeaders);
    assertTrue($aiProvidersForbidden['status'] === 403, 'Restricted role without ai.admin must get 403 on admin-ai providers');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] web_role_restricted_access_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_role_restricted_access_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
