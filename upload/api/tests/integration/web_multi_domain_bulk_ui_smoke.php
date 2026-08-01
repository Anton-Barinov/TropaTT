<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders((string)$root['token']);
    $projectRoot = dirname(__DIR__, 3);

    $projectsTpl = file_get_contents($projectRoot . '/web/view/template/page/projects.php');
    assertTrue(is_string($projectsTpl) && str_contains($projectsTpl, 'id="projectsBulkActionsBar"'), 'projects bulk bar marker missing');
    $clientsTpl = file_get_contents($projectRoot . '/web/view/template/page/clients.php');
    assertTrue(is_string($clientsTpl) && str_contains($clientsTpl, 'id="clientsBulkActionsBar"'), 'clients bulk bar marker missing');
    $adminUsersTpl = file_get_contents($projectRoot . '/web/view/template/page/admin_users.php');
    assertTrue(is_string($adminUsersTpl) && str_contains($adminUsersTpl, 'id="adminUsersBulkActionsBar"'), 'admin-users bulk bar marker missing');

    $bindings = file_get_contents($projectRoot . '/web/assets/js/page-api-bindings.js');
    assertTrue(is_string($bindings) && str_contains($bindings, 'data-project-bulk-id'), 'projects bulk JS marker missing');
    assertTrue(is_string($bindings) && str_contains($bindings, 'data-client-bulk-id'), 'clients bulk JS marker missing');
    assertTrue(is_string($bindings) && str_contains($bindings, 'data-admin-user-bulk-id'), 'admin-users bulk JS marker missing');

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'Bulk project ' . randomSuffix(),
        'status' => 'new',
    ], $rootHeaders);
    assertTrue($projectCreate['status'] === 201, 'Project create must return 201');
    $projectId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectId !== '', 'Project id required');

    $projectPatch = request('PATCH', '/api/v1/projects/' . $projectId, [
        'status' => 'active',
    ], $rootHeaders);
    assertTrue($projectPatch['status'] === 200, 'Project patch must return 200');

    $clientCreate = request('POST', '/api/v1/clients', [
        'title' => 'Bulk client ' . randomSuffix(),
        'client_type' => 'individual',
        'status' => 'active',
    ], $rootHeaders);
    assertTrue($clientCreate['status'] === 201, 'Client create must return 201');
    $clientId = (string)($clientCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($clientId !== '', 'Client id required');

    $clientPatch = request('PATCH', '/api/v1/clients/' . $clientId, [
        'status' => 'inactive',
    ], $rootHeaders);
    assertTrue($clientPatch['status'] === 200, 'Client patch must return 200');

    $suffix = randomSuffix();
    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'bulk_users_role_' . $suffix,
        'title' => 'Bulk Users Role ' . $suffix,
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create must return 201');
    $roleId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');

    $userCreate = request('POST', '/api/v1/users', [
        'login' => 'bulk.user.' . $suffix,
        'password' => 'BulkUser#2026!',
        'email' => 'bulk.user.' . $suffix . '@crm.local',
        'full_name' => 'Bulk User',
        'token' => 'bulk-user-token-' . $suffix,
        'role_public_ids' => [$roleId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'User create must return 201');
    $userId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userId !== '', 'User id required');

    $userPatch = request('PATCH', '/api/v1/users/' . $userId, [
        'is_active' => '0',
    ], $rootHeaders);
    assertTrue($userPatch['status'] === 200, 'User patch must return 200');

    if ($userId !== '') {
        request('DELETE', '/api/v1/users/' . $userId, [], $rootHeaders);
    }
    if ($roleId !== '') {
        request('DELETE', '/api/v1/roles/' . $roleId, [], $rootHeaders);
    }
    if ($clientId !== '') {
        request('DELETE', '/api/v1/clients/' . $clientId, [], $rootHeaders);
    }
    if ($projectId !== '') {
        request('DELETE', '/api/v1/projects/' . $projectId, [], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] web_multi_domain_bulk_ui_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] web_multi_domain_bulk_ui_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
