<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);

    $webRoutes = require dirname(__DIR__, 3) . '/web/config/routes.php';
    assertTrue(is_array($webRoutes), 'web routes must be array');
    assertTrue(array_key_exists('departments', $webRoutes), 'web route departments must exist');

    $tpl = file_get_contents(dirname(__DIR__, 3) . '/web/view/template/page/departments.php');
    assertTrue(is_string($tpl) && str_contains($tpl, 'data-page="departments"'), 'departments template marker missing');

    $create = request('POST', '/api/v1/departments', [
        'title' => 'Dept ' . randomSuffix(),
        'code' => 'DEP' . random_int(100, 999),
        'status' => 'active',
    ], $rootHeaders);
    assertTrue($create['status'] === 201, 'Department create must return 201');
    $publicId = (string)($create['payload']['data']['department']['public_id'] ?? '');
    assertTrue($publicId !== '', 'Department public_id is required');

    $list = request('GET', '/api/v1/departments?limit=50', [], $rootHeaders);
    assertTrue($list['status'] === 200, 'Department list must return 200');

    $update = request('PATCH', '/api/v1/departments/' . $publicId, ['status' => 'archived'], $rootHeaders);
    assertTrue($update['status'] === 200, 'Department update must return 200');

    $restrictedRoleCode = 'dep_view_only_' . randomSuffix();
    $createRole = request('POST', '/api/v1/roles', [
        'code' => $restrictedRoleCode,
        'title' => 'Dept Restricted ' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($createRole['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($createRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Restricted role public_id is required');

    $suffix = randomSuffix();
    $login = 'dep.restricted.' . $suffix;
    $password = 'DepRestricted#2026!';
    $token = 'dep-restricted-token-' . $suffix;

    $createUser = request('POST', '/api/v1/users', [
        'login' => $login,
        'password' => $password,
        'email' => $login . '@crm.local',
        'full_name' => 'Department Restricted User',
        'token' => $token,
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($createUser['status'] === 201, 'Restricted user create must return 201');
    $userPublicId = (string)($createUser['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'Restricted user public_id is required');

    $auth = request('POST', '/api/v1/auth/login', [
        'login' => $login,
        'password' => $password,
        'token' => $token,
    ]);
    assertTrue($auth['status'] === 200, 'Restricted login must return 200');
    $restrictedHeaders = authHeaders((string)($auth['payload']['data']['access_token'] ?? ''));

    $forbidden = request('GET', '/api/v1/departments?limit=5', [], $restrictedHeaders);
    assertTrue($forbidden['status'] === 403, 'Restricted role without department.manage must get 403');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);
    $delete = request('DELETE', '/api/v1/departments/' . $publicId, [], $rootHeaders);
    assertTrue($delete['status'] === 200, 'Department delete must return 200');

    fwrite(STDOUT, "[OK] web_departments_page_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_departments_page_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

