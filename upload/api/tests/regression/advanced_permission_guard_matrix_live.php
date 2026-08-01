<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    // Role with wide permissions to validate route-level ACL vs service/controller guards.
    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'guard_matrix_' . $suffix,
        'title' => 'Guard Matrix ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => [
            'logs.view',
            'api_client.view',
            'api_client.manage',
            'webhook.manage',
            'organization.manage',
            'settings.manage',
            'import.manage',
            'export.manage',
            'recycle_bin.manage',
        ],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'guard_matrix_' . $suffix;
    $tokenFactor = 'guard-matrix-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'GuardMatrix123!',
        'token' => $tokenFactor,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'GuardMatrix123!',
        'token' => $tokenFactor,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    // 1) logs.view route permission exists, but logs/* remains controller-level root-only.
    $logsRequest = liveRequest('GET', 'api/v1/logs/request', [], $userHeaders);
    liveAssert($logsRequest['status'] === 403, 'logs/request must be forbidden for non-root');
    liveAssert((string)($logsRequest['payload']['code'] ?? '') === 'FORBIDDEN', 'logs/request forbidden code mismatch');

    // Operational diagnostics are root-only even when logs.view is present.
    $widgetsSystem = liveRequest('GET', 'api/v1/admin/widgets/system', [], $userHeaders);
    liveAssert($widgetsSystem['status'] === 403, 'admin/widgets/system must be blocked for non-root logs.view');
    liveAssert((string)($widgetsSystem['payload']['code'] ?? '') === 'FORBIDDEN', 'admin/widgets/system forbidden code mismatch');

    // 2) api_client.* route permissions vs service-level root boundary.
    $apiClientsList = liveRequest('GET', 'api/v1/api-clients', [], $userHeaders);
    liveAssert($apiClientsList['status'] === 200, 'api-clients list must be allowed with api_client.view');

    $apiClientCreate = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Matrix API client ' . $suffix,
        'scopes' => ['tasks.read'],
    ], $userHeaders);
    liveAssert($apiClientCreate['status'] === 403, 'api-clients create must be blocked for non-root');
    liveAssert((string)($apiClientCreate['payload']['code'] ?? '') === 'FORBIDDEN', 'api-clients create code mismatch');

    // 3) webhook.manage route permission vs service-level root boundary (canonical+alias parity).
    $webhooksList = liveRequest('GET', 'api/v1/webhooks', [], $userHeaders);
    liveAssert($webhooksList['status'] === 200, 'webhooks list must be allowed with webhook.manage');

    $webhookCreateCanonical = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Matrix webhook c ' . $suffix,
        'endpoint' => 'https://localhost/matrix-c-' . $suffix,
        'events' => ['task.created'],
    ], $userHeaders);
    $webhookCreateAlias = liveRequest('POST', 'api/v1/webhook/create', [
        'title' => 'Matrix webhook a ' . $suffix,
        'endpoint' => 'https://localhost/matrix-a-' . $suffix,
        'events' => ['task.created'],
    ], $userHeaders);
    liveAssert($webhookCreateCanonical['status'] === 403, 'webhook create canonical must be blocked for non-root');
    liveAssert($webhookCreateAlias['status'] === 403, 'webhook create alias must be blocked for non-root');
    liveAssert((string)($webhookCreateCanonical['payload']['code'] ?? '') === 'FORBIDDEN', 'webhook create canonical code mismatch');
    liveAssert((string)($webhookCreateAlias['payload']['code'] ?? '') === 'FORBIDDEN', 'webhook create alias code mismatch');

    // 4) organization.manage ownership boundary.
    $rootOrg = liveRequest('POST', 'api/v1/organizations', [
        'title' => 'Root Org Matrix ' . $suffix,
        'slug' => 'root-org-matrix-' . $suffix,
    ], $rootHeaders);
    liveAssert($rootOrg['status'] === 201, 'Root organization create must return 201');
    $rootOrgPublicId = (string)($rootOrg['payload']['data']['organization']['public_id'] ?? '');
    liveAssert($rootOrgPublicId !== '', 'Root org public_id is required');

    $userGetRootOrg = liveRequest('GET', 'api/v1/organizations/' . $rootOrgPublicId, [], $userHeaders);
    liveAssert($userGetRootOrg['status'] === 404, 'Non-member must not access root-owned organization');

    $userOrgCreate = liveRequest('POST', 'api/v1/organizations', [
        'title' => 'User Org Matrix ' . $suffix,
        'slug' => 'user-org-matrix-' . $suffix,
    ], $userHeaders);
    liveAssert($userOrgCreate['status'] === 201, 'User organization create must return 201');
    $userOrgPublicId = (string)($userOrgCreate['payload']['data']['organization']['public_id'] ?? '');
    liveAssert($userOrgPublicId !== '', 'User org public_id is required');

    $userOrgDelete = liveRequest('DELETE', 'api/v1/organizations/' . $userOrgPublicId, [], $userHeaders);
    liveAssert($userOrgDelete['status'] === 200, 'User must be able to delete own organization');

    // 5) retention permission boundary and alias parity.
    $retentionGet = liveRequest('GET', 'api/v1/retention/metadata', [], $userHeaders);
    liveAssert($retentionGet['status'] === 200, 'Retention metadata must be accessible with settings.manage');

    $retentionSetCanonical = liveRequest('PATCH', 'api/v1/retention/metadata', [
        'request_logs_days' => 91,
    ], $userHeaders);
    $retentionSetAlias = liveRequest('PATCH', 'api/v1/retention/set', [
        'request_logs_days' => 92,
    ], $userHeaders);
    liveAssert($retentionSetCanonical['status'] === 200, 'Retention canonical set must return 200');
    liveAssert($retentionSetAlias['status'] === 200, 'Retention alias set must return 200');

    // 6) import/export ownership invariants on canonical+alias.
    $rootExport = liveRequest('POST', 'api/v1/export/jobs', [
        'type' => 'tasks',
        'filters' => ['search' => $suffix],
    ], $rootHeaders);
    liveAssert($rootExport['status'] === 201, 'Root export create must return 201');
    $rootExportPublicId = (string)($rootExport['payload']['data']['job']['public_id'] ?? '');

    $rootImport = liveRequest('POST', 'api/v1/import/jobs', [
        'type' => 'tasks',
        'rows' => [
            ['title' => 'Root import matrix ' . $suffix, 'status' => 'new', 'priority' => 'normal'],
        ],
    ], $rootHeaders);
    liveAssert($rootImport['status'] === 201, 'Root import create must return 201');
    $rootImportPublicId = (string)($rootImport['payload']['data']['job']['public_id'] ?? '');

    $userRootExportCanonical = liveRequest('GET', 'api/v1/export/jobs/' . $rootExportPublicId, [], $userHeaders);
    $userRootExportAlias = liveRequest('GET', 'api/v1/export/status/' . $rootExportPublicId, [], $userHeaders);
    liveAssert($userRootExportCanonical['status'] === 404, 'User must not access root export canonical');
    liveAssert($userRootExportAlias['status'] === 404, 'User must not access root export alias');

    $userRootImportCanonical = liveRequest('GET', 'api/v1/import/jobs/' . $rootImportPublicId, [], $userHeaders);
    $userRootImportAlias = liveRequest('GET', 'api/v1/import/status/' . $rootImportPublicId, [], $userHeaders);
    liveAssert($userRootImportCanonical['status'] === 404, 'User must not access root import canonical');
    liveAssert($userRootImportAlias['status'] === 404, 'User must not access root import alias');

    // User-owned jobs should be readable on canonical and alias.
    $userExportAliasCreate = liveRequest('POST', 'api/v1/export/create', [
        'type' => 'tasks',
        'filters' => ['search' => 'matrix-' . $suffix],
    ], $userHeaders);
    liveAssert($userExportAliasCreate['status'] === 201, 'User export alias create must return 201');
    $userExportPublicId = (string)($userExportAliasCreate['payload']['data']['job']['public_id'] ?? '');

    $userExportCanonicalGet = liveRequest('GET', 'api/v1/export/jobs/' . $userExportPublicId, [], $userHeaders);
    $userExportAliasGet = liveRequest('GET', 'api/v1/export/status/' . $userExportPublicId, [], $userHeaders);
    liveAssert($userExportCanonicalGet['status'] === 200, 'User export canonical get must return 200');
    liveAssert($userExportAliasGet['status'] === 200, 'User export alias get must return 200');

    $userImportCanonicalCreate = liveRequest('POST', 'api/v1/import/jobs', [
        'type' => 'tasks',
        'rows' => [
            ['title' => 'User import matrix ' . $suffix, 'status' => 'new', 'priority' => 'normal'],
        ],
    ], $userHeaders);
    liveAssert($userImportCanonicalCreate['status'] === 201, 'User import canonical create must return 201');
    $userImportPublicId = (string)($userImportCanonicalCreate['payload']['data']['job']['public_id'] ?? '');

    $userImportCanonicalGet = liveRequest('GET', 'api/v1/import/jobs/' . $userImportPublicId, [], $userHeaders);
    $userImportAliasGet = liveRequest('GET', 'api/v1/import/status/' . $userImportPublicId, [], $userHeaders);
    liveAssert($userImportCanonicalGet['status'] === 200, 'User import canonical get must return 200');
    liveAssert($userImportAliasGet['status'] === 200, 'User import alias get must return 200');

    // Cleanup.
    liveRequest('DELETE', 'api/v1/organizations/' . $rootOrgPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_permission_guard_matrix_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_permission_guard_matrix_live: ' . $e->getMessage() . "\n");
    exit(1);
}
