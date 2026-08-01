<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $matrix = request('GET', '/api/v1/admin/role-matrix', [], $headers);
    assertTrue($matrix['status'] === 200, 'Role matrix status must be 200');
    assertTrue(($matrix['payload']['code'] ?? '') === 'ADMIN_ROLE_MATRIX', 'Role matrix code mismatch');

    $roleCode = 'matrix_smoke_' . randomSuffix();
    $createRole = request('POST', '/api/v1/roles', [
        'code' => $roleCode,
        'title' => 'Matrix Smoke Role',
    ], $headers);
    assertTrue($createRole['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($createRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id required');

    $updateMatrix = request('PUT', '/api/v1/admin/role-matrix', [
        'roles' => [[
            'role_public_id' => $rolePublicId,
            'permission_codes' => ['project.manage', 'task.manage'],
        ]],
    ], $headers);
    assertTrue($updateMatrix['status'] === 200, 'Role matrix update status must be 200');
    assertTrue(($updateMatrix['payload']['code'] ?? '') === 'ADMIN_ROLE_MATRIX_UPDATED', 'Role matrix update code mismatch');

    $matrixAlias = request('GET', '/api/v1/admin/role-matrix/get', [], $headers);
    assertTrue($matrixAlias['status'] === 200, 'Role matrix alias status must be 200');

    $statusFrom = request('POST', '/api/v1/statuses', [
        'scope' => 'project',
        'code' => 'proj_smoke_from_' . randomSuffix(),
        'title' => 'Project smoke from',
    ], $headers);
    $statusTo = request('POST', '/api/v1/statuses', [
        'scope' => 'project',
        'code' => 'proj_smoke_to_' . randomSuffix(),
        'title' => 'Project smoke to',
    ], $headers);
    assertTrue($statusFrom['status'] === 201 && $statusTo['status'] === 201, 'Statuses create status must be 201');

    $statusFromPublicId = (string)($statusFrom['payload']['data']['status']['public_id'] ?? '');
    $statusToPublicId = (string)($statusTo['payload']['data']['status']['public_id'] ?? '');
    $statusFromCode = (string)($statusFrom['payload']['data']['status']['code'] ?? '');
    $statusToCode = (string)($statusTo['payload']['data']['status']['code'] ?? '');
    assertTrue($statusFromPublicId !== '' && $statusToPublicId !== '', 'Status public_id required');

    $project = request('POST', '/api/v1/projects', [
        'title' => 'Status remap smoke ' . randomSuffix(),
        'status' => $statusFromCode,
    ], $headers);
    assertTrue($project['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($project['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id required');

    $deleteInUse = request('DELETE', '/api/v1/statuses/' . $statusFromPublicId, [], $headers);
    assertTrue($deleteInUse['status'] === 409, 'Delete in-use status must be 409');
    assertTrue(($deleteInUse['payload']['code'] ?? '') === 'STATUS_IN_USE', 'Delete in-use code mismatch');

    $remapDelete = request('POST', '/api/v1/statuses/' . $statusFromPublicId . '/remap-delete', [
        'remap_to_public_id' => $statusToPublicId,
    ], $headers);
    assertTrue($remapDelete['status'] === 200, 'Remap delete status must be 200');
    assertTrue(($remapDelete['payload']['data']['remapped'] ?? false) === true, 'Remap flag must be true');

    $projectAfter = request('GET', '/api/v1/projects/' . $projectPublicId, [], $headers);
    assertTrue($projectAfter['status'] === 200, 'Project after remap status must be 200');
    assertTrue(($projectAfter['payload']['data']['project']['status_code'] ?? '') === $statusToCode, 'Project status must be remapped');

    $unauthorized = request('GET', '/api/v1/admin/role-matrix');
    assertTrue($unauthorized['status'] === 401, 'Role matrix unauthorized status must be 401');

    echo "[OK] Admin role-matrix + status remap smoke passed\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
