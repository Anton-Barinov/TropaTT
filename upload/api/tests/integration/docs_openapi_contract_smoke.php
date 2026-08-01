<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $unauthorized = request('GET', '/api/v1/docs/openapi');
    assertTrue($unauthorized['status'] === 401, 'OpenAPI docs endpoint must require auth');

    $roleCode = 'docs_view_denied_' . randomSuffix();
    $createRole = request('POST', '/api/v1/roles', [
        'code' => $roleCode,
        'title' => 'Docs denied role ' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($createRole['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($createRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setPerms = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['task.manage'],
    ], $rootHeaders);
    assertTrue($setPerms['status'] === 200, 'Role permission set must return 200');

    $suffix = randomSuffix();
    $userLogin = 'docs.denied.' . $suffix;
    $userPassword = 'DocsDenied#2026!';
    $userToken = 'docs-denied-token-' . $suffix;

    $createUser = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'Docs Denied User',
        'token' => $userToken,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($createUser['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($createUser['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'User public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'Restricted user login must return 200');
    $userAccessToken = (string)($userAuth['payload']['data']['access_token'] ?? '');
    assertTrue($userAccessToken !== '', 'Restricted user access token is required');

    $forbidden = request('GET', '/api/v1/docs/openapi', [], authHeaders($userAccessToken));
    assertTrue($forbidden['status'] === 403, 'OpenAPI docs endpoint must require logs.view permission');

    $docs = request('GET', '/api/v1/docs/openapi', [], $rootHeaders);
    assertTrue($docs['status'] === 200, 'Root must access docs endpoint');
    assertTrue((bool)($docs['payload']['success'] ?? false) === true, 'OpenAPI docs payload success must be true');

    $spec = (array)($docs['payload']['data']['spec'] ?? []);
    assertTrue((string)($spec['openapi'] ?? '') !== '', 'OpenAPI version must exist');
    $paths = (array)($spec['paths'] ?? []);
    assertTrue($paths !== [], 'OpenAPI paths must not be empty');
    assertTrue(array_key_exists('/api/v1/auth/login', $paths), 'OpenAPI spec must include /api/v1/auth/login');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] docs_openapi_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] docs_openapi_contract_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
