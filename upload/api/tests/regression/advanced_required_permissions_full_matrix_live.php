<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * @param array<string,string> $headers
 */
function assertStatus(string $method, string $route, int $expected, array $headers, array $payload = []): void
{
    $response = liveRequest($method, $route, $payload, $headers);
    liveAssert($response['status'] === $expected, sprintf('%s %s expected %d, got %d', $method, $route, $expected, $response['status']));
}

/**
 * @param int[] $allowed
 * @param array<string,string> $headers
 */
function assertStatusIn(string $method, string $route, array $allowed, array $headers, array $payload = []): void
{
    $response = liveRequest($method, $route, $payload, $headers);
    liveAssert(in_array($response['status'], $allowed, true), sprintf(
        '%s %s expected one of [%s], got %d',
        $method,
        $route,
        implode(', ', $allowed),
        $response['status']
    ));
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    // High-permission role to validate route-level ACL and extra service/controller boundaries.
    $roleHighCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'perm_full_high_' . $suffix,
        'title' => 'Perm Full High ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleHighCreate['status'] === 201, 'High role create must return 201');
    $roleHighPublicId = (string)($roleHighCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($roleHighPublicId !== '', 'High role public_id is required');

    $highPerms = [
        'user.view',
        'role.view',
        'role.manage',
        'team.manage',
        'department.manage',
        'company.manage',
        'client.manage',
        'contact.manage',
        'project.manage',
        'task.manage',
        'settings.manage',
        'feature_flag.manage',
        'organization.manage',
        'import.manage',
        'export.manage',
        'recycle_bin.manage',
        'api_client.view',
        'api_client.manage',
        'webhook.manage',
        'logs.view',
    ];
    $setHighPerms = liveRequest('PUT', 'api/v1/roles/' . $roleHighPublicId . '/permissions', [
        'permission_codes' => $highPerms,
    ], $rootHeaders);
    liveAssert($setHighPerms['status'] === 200, 'High role permissions set must return 200');

    $highLogin = 'perm_full_high_' . $suffix;
    $highTokenFactor = 'perm-full-high-token-' . $suffix;
    $highUserCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $highLogin,
        'password' => 'PermFullHigh123!',
        'token' => $highTokenFactor,
        'email' => $highLogin . '@crm.local',
        'role_public_ids' => [$roleHighPublicId],
    ], $rootHeaders);
    liveAssert($highUserCreate['status'] === 201, 'High user create must return 201');
    $highUserPublicId = (string)($highUserCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($highUserPublicId !== '', 'High user public_id is required');

    $highUserLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $highLogin,
        'password' => 'PermFullHigh123!',
        'token' => $highTokenFactor,
    ]);
    liveAssert($highUserLogin['status'] === 200, 'High user login must return 200');
    $highAccessToken = (string)($highUserLogin['payload']['data']['access_token'] ?? '');
    liveAssert($highAccessToken !== '', 'High user access token is required');
    $highHeaders = ['Authorization' => 'Bearer ' . $highAccessToken];

    // Low-permission role to validate deny-by-default with required_permissions.
    $roleLowCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'perm_full_low_' . $suffix,
        'title' => 'Perm Full Low ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleLowCreate['status'] === 201, 'Low role create must return 201');
    $roleLowPublicId = (string)($roleLowCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($roleLowPublicId !== '', 'Low role public_id is required');

    $setLowPerms = liveRequest('PUT', 'api/v1/roles/' . $roleLowPublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    liveAssert($setLowPerms['status'] === 200, 'Low role permissions set must return 200');

    $lowLogin = 'perm_full_low_' . $suffix;
    $lowTokenFactor = 'perm-full-low-token-' . $suffix;
    $lowUserCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $lowLogin,
        'password' => 'PermFullLow123!',
        'token' => $lowTokenFactor,
        'email' => $lowLogin . '@crm.local',
        'role_public_ids' => [$roleLowPublicId],
    ], $rootHeaders);
    liveAssert($lowUserCreate['status'] === 201, 'Low user create must return 201');
    $lowUserPublicId = (string)($lowUserCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($lowUserPublicId !== '', 'Low user public_id is required');

    $lowUserLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $lowLogin,
        'password' => 'PermFullLow123!',
        'token' => $lowTokenFactor,
    ]);
    liveAssert($lowUserLogin['status'] === 200, 'Low user login must return 200');
    $lowAccessToken = (string)($lowUserLogin['payload']['data']['access_token'] ?? '');
    liveAssert($lowAccessToken !== '', 'Low user access token is required');
    $lowHeaders = ['Authorization' => 'Bearer ' . $lowAccessToken];

    // High-permission matrix (route-level required_permissions should pass).
    $highAllowRoutes = [
        'api/v1/users',
        'api/v1/roles',
        'api/v1/teams',
        'api/v1/departments',
        'api/v1/companies',
        'api/v1/clients',
        'api/v1/contacts',
        'api/v1/projects',
        'api/v1/tasks',
        'api/v1/statuses',
        'api/v1/priorities',
        'api/v1/tags',
        'api/v1/settings',
        'api/v1/retention/metadata',
        'api/v1/feature-flags',
        'api/v1/organizations',
        'api/v1/import/jobs',
        'api/v1/export/jobs',
        'api/v1/recycle-bin',
        'api/v1/api-clients',
        'api/v1/webhooks',
        // alias family checks
        'api/v1/project/list',
        'api/v1/task/list',
        'api/v1/organization/list',
        'api/v1/recycle-bin/list',
        'api/v1/webhook/list',
        'api/v1/api-client/list',
        'api/v1/retention/get',
    ];
    foreach ($highAllowRoutes as $route) {
        assertStatus('GET', $route, 200, $highHeaders);
    }
    // For alias setting/get endpoint, validate that ACL allows access (controller may return 200/404/422).
    assertStatusIn('GET', 'api/v1/setting/get/system.locale', [200, 404], $highHeaders);

    // Guard overrides: permissions exist, but service/controller still deny non-root writes/reads.
    $logsRequest = liveRequest('GET', 'api/v1/logs/request', [], $highHeaders);
    liveAssert($logsRequest['status'] === 403, 'logs/request must be forbidden for non-root even with logs.view');
    liveAssert((string)($logsRequest['payload']['code'] ?? '') === 'FORBIDDEN', 'logs/request code mismatch');

    $roleCreateDenied = liveRequest('POST', 'api/v1/roles', [
        'code' => 'perm_full_non_root_' . $suffix,
        'title' => 'Perm Full Non Root ' . $suffix,
    ], $highHeaders);
    liveAssert($roleCreateDenied['status'] === 403, 'roles create must be forbidden for non-root even with role.manage');
    liveAssert((string)($roleCreateDenied['payload']['code'] ?? '') === 'FORBIDDEN', 'roles create forbidden code mismatch');

    $roleSetPermissionsDenied = liveRequest('PUT', 'api/v1/roles/' . $roleLowPublicId . '/permissions', [
        'permission_codes' => ['user.view', 'role.view'],
    ], $highHeaders);
    liveAssert($roleSetPermissionsDenied['status'] === 403, 'roles permissions set must be forbidden for non-root even with role.manage');
    liveAssert((string)($roleSetPermissionsDenied['payload']['code'] ?? '') === 'FORBIDDEN', 'roles permissions set forbidden code mismatch');

    $roleUpdateDenied = liveRequest('PATCH', 'api/v1/roles/' . $roleLowPublicId, [
        'title' => 'Denied non-root role update ' . $suffix,
    ], $highHeaders);
    liveAssert($roleUpdateDenied['status'] === 403, 'roles update must be forbidden for non-root even with role.manage');
    liveAssert((string)($roleUpdateDenied['payload']['code'] ?? '') === 'FORBIDDEN', 'roles update forbidden code mismatch');

    $roleDeleteDenied = liveRequest('DELETE', 'api/v1/roles/' . $roleLowPublicId, [], $highHeaders);
    liveAssert($roleDeleteDenied['status'] === 403, 'roles delete must be forbidden for non-root even with role.manage');
    liveAssert((string)($roleDeleteDenied['payload']['code'] ?? '') === 'FORBIDDEN', 'roles delete forbidden code mismatch');

    assertStatus('GET', 'api/v1/admin/widgets/system', 403, $highHeaders);

    $apiClientCreate = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Guard deny client ' . $suffix,
        'scopes' => ['tasks.read'],
    ], $highHeaders);
    liveAssert($apiClientCreate['status'] === 403, 'api-clients create must be forbidden for non-root');

    $webhookCreate = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Guard deny webhook ' . $suffix,
        'endpoint' => 'https://localhost/guard-deny-' . $suffix,
        'events' => ['task.created'],
    ], $highHeaders);
    liveAssert($webhookCreate['status'] === 403, 'webhooks create must be forbidden for non-root');

    // Low-permission matrix (deny-by-default required_permissions).
    assertStatus('GET', 'api/v1/users', 200, $lowHeaders); // only user.view

    $lowForbiddenRoutes = [
        'api/v1/roles',
        'api/v1/projects',
        'api/v1/tasks',
        'api/v1/settings',
        'api/v1/import/jobs',
        'api/v1/export/jobs',
        'api/v1/recycle-bin',
        'api/v1/organizations',
        'api/v1/feature-flags',
        'api/v1/webhooks',
        'api/v1/api-clients',
        // alias routes
        'api/v1/project/list',
        'api/v1/task/list',
        'api/v1/setting/get/system.locale',
        'api/v1/recycle-bin/list',
        'api/v1/organization/list',
    ];
    foreach ($lowForbiddenRoutes as $route) {
        assertStatus('GET', $route, 403, $lowHeaders);
    }

    // Cleanup.
    liveRequest('DELETE', 'api/v1/users/' . $highUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $lowUserPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $roleHighPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $roleLowPublicId, [], $rootHeaders);

    echo "[OK] advanced_required_permissions_full_matrix_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_required_permissions_full_matrix_live: ' . $e->getMessage() . "\n");
    exit(1);
}
