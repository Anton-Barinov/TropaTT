<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $rootPublicId = (string)$root['user_public_id'];
    liveAssert($rootPublicId !== '', 'Root public_id is required');

    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'mixed_escalation_' . $suffix,
        'title' => 'Mixed Escalation ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view', 'user.manage', 'logs.view'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'mixed_escalation_' . $suffix;
    $token = 'mixed-escalation-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'MixedEsc123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'MixedEsc123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    // Allowed within assigned scope.
    $usersList = liveRequest('GET', 'api/v1/users', [], $userHeaders);
    liveAssert($usersList['status'] === 200, 'users list must be allowed for user.view');

    $invitationsList = liveRequest('GET', 'api/v1/security/invitations', [], $userHeaders);
    liveAssert($invitationsList['status'] === 200, 'invitations list must be allowed for user.manage');

    // Cross-module boundary: must stay forbidden.
    $forbiddenCases = [
        ['method' => 'GET', 'route' => 'api/v1/feature-flags', 'payload' => []],
        ['method' => 'POST', 'route' => 'api/v1/workflow/rules', 'payload' => [
            'title' => 'Forbidden workflow',
            'trigger_code' => 'task_created',
            'action_code' => 'send_notification',
        ]],
        ['method' => 'GET', 'route' => 'api/v1/logs/security', 'payload' => []],
        ['method' => 'POST', 'route' => 'api/v1/organizations', 'payload' => [
            'title' => 'Forbidden org',
            'code' => 'forbidden-org-' . $suffix,
        ]],
        ['method' => 'POST', 'route' => 'api/v1/api-clients', 'payload' => [
            'title' => 'Forbidden API client',
            'scopes' => ['tasks.read'],
        ]],
        ['method' => 'POST', 'route' => 'api/v1/webhooks', 'payload' => [
            'title' => 'Forbidden webhook',
            'endpoint' => 'https://localhost/forbidden-webhook',
            'events' => ['task_created'],
        ]],
        ['method' => 'PATCH', 'route' => 'api/v1/retention/metadata', 'payload' => [
            'days_request_logs' => 33,
        ]],
    ];

    foreach ($forbiddenCases as $case) {
        $response = liveRequest((string)$case['method'], (string)$case['route'], (array)$case['payload'], $userHeaders);
        liveAssert($response['status'] === 403, 'Cross-module action must be forbidden: ' . $case['method'] . ' ' . $case['route']);
    }

    // Escalation path: trying to operate on root must stay protected.
    $deleteRoot = liveRequest('DELETE', 'api/v1/users/' . $rootPublicId, [], $userHeaders);
    liveAssert($deleteRoot['status'] === 403, 'Deleting root must be forbidden');
    liveAssert((string)($deleteRoot['payload']['code'] ?? '') === 'FORBIDDEN_ROOT_PROTECTED', 'Deleting root code mismatch');

    $impersonateRoot = liveRequest('POST', 'api/v1/security/impersonation/start', [
        'target_user_public_id' => $rootPublicId,
        'reason' => 'Escalation attempt',
    ], $userHeaders);
    liveAssert($impersonateRoot['status'] === 403, 'Impersonating root must be forbidden');
    liveAssert((string)($impersonateRoot['payload']['code'] ?? '') === 'FORBIDDEN_ROOT_PROTECTED', 'Impersonating root code mismatch');

    // Escalation path: no role.manage -> cannot edit role-permission matrix.
    $rolePermUpdate = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view', 'role.manage', 'settings.manage'],
    ], $userHeaders);
    liveAssert($rolePermUpdate['status'] === 403, 'Role-permission update must be forbidden without role.manage');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_mixed_role_escalation_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_mixed_role_escalation_live: ' . $e->getMessage() . "\n");
    exit(1);
}
