<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'perm_consistency_' . $suffix,
        'title' => 'Permission Consistency ' . $suffix,
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
            'feature_flag.manage',
        ],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'perm_consistency_' . $suffix;
    $token = 'perm-consistency-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'PermConsist123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'PermConsist123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    // Route-level permission allows read of API clients.
    $apiClientsList = liveRequest('GET', 'api/v1/api-clients', [], $userHeaders);
    liveAssert($apiClientsList['status'] === 200, 'GET api-clients must be allowed for api_client.view');

    // Service-level root boundary must still block write despite route permission.
    $apiClientCreate = liveRequest('POST', 'api/v1/api-clients', [
        'title' => 'Should be blocked ' . $suffix,
        'scopes' => ['tasks.read'],
    ], $userHeaders);
    liveAssert($apiClientCreate['status'] === 403, 'POST api-clients must be blocked for non-root actor');
    liveAssert((string)($apiClientCreate['payload']['code'] ?? '') === 'FORBIDDEN', 'POST api-clients must return FORBIDDEN');

    // Route-level permission allows webhook list.
    $webhooksList = liveRequest('GET', 'api/v1/webhooks', [], $userHeaders);
    liveAssert($webhooksList['status'] === 200, 'GET webhooks must be allowed for webhook.manage');

    // Service-level root boundary must block webhook create.
    $webhookCreate = liveRequest('POST', 'api/v1/webhooks', [
        'title' => 'Should be blocked webhook ' . $suffix,
        'endpoint' => 'https://localhost/blocked-' . $suffix,
        'events' => ['task.created'],
    ], $userHeaders);
    liveAssert($webhookCreate['status'] === 403, 'POST webhooks must be blocked for non-root actor');
    liveAssert((string)($webhookCreate['payload']['code'] ?? '') === 'FORBIDDEN', 'POST webhooks must return FORBIDDEN');

    // Controller-level root boundary must override route permission for logs.
    $requestLogs = liveRequest('GET', 'api/v1/logs/request', [], $userHeaders);
    liveAssert($requestLogs['status'] === 403, 'GET logs/request must be blocked for non-root actor');
    liveAssert((string)($requestLogs['payload']['code'] ?? '') === 'FORBIDDEN', 'GET logs/request must return FORBIDDEN');

    // Operational diagnostics are root-only even when logs.view is present.
    $widgetsSystem = liveRequest('GET', 'api/v1/admin/widgets/system', [], $userHeaders);
    liveAssert($widgetsSystem['status'] === 403, 'GET admin/widgets/system must be blocked for non-root logs.view');
    liveAssert((string)($widgetsSystem['payload']['code'] ?? '') === 'FORBIDDEN', 'GET admin/widgets/system must return FORBIDDEN');

    // Feature-flag manage should work without hidden root-only restriction.
    $flags = liveRequest('GET', 'api/v1/feature-flags', [], $userHeaders);
    liveAssert($flags['status'] === 200, 'GET feature-flags must be allowed for feature_flag.manage');
    $flagPublicId = (string)($flags['payload']['data']['items'][0]['public_id'] ?? '');
    liveAssert($flagPublicId !== '', 'Feature flag public_id is required');

    $flagPatch = liveRequest('PATCH', 'api/v1/feature-flags/' . $flagPublicId, [
        'is_enabled' => true,
    ], $userHeaders);
    liveAssert($flagPatch['status'] === 200, 'PATCH feature-flag must be allowed for feature_flag.manage');

    // Organization manage should allow create + own management path.
    $orgCreate = liveRequest('POST', 'api/v1/organizations', [
        'title' => 'Perm Consistency Org ' . $suffix,
        'slug' => 'perm-consistency-org-' . $suffix,
    ], $userHeaders);
    liveAssert($orgCreate['status'] === 201, 'POST organizations must be allowed for organization.manage');
    $orgPublicId = (string)($orgCreate['payload']['data']['organization']['public_id'] ?? '');
    liveAssert($orgPublicId !== '', 'Organization public_id is required');

    $orgMembers = liveRequest('GET', 'api/v1/organizations/' . $orgPublicId . '/members', [], $userHeaders);
    liveAssert($orgMembers['status'] === 200, 'GET organization members must be allowed for owner actor');

    $orgDelete = liveRequest('DELETE', 'api/v1/organizations/' . $orgPublicId, [], $userHeaders);
    liveAssert($orgDelete['status'] === 200, 'DELETE own organization must be allowed for owner actor');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_permission_model_consistency_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_permission_model_consistency_live: ' . $e->getMessage() . "\n");
    exit(1);
}
