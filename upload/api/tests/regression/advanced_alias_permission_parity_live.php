<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $pairs = [
        ['method' => 'GET', 'canonical' => 'api/v1/template/tasks', 'alias' => 'api/v1/template/task/list'],
        ['method' => 'GET', 'canonical' => 'api/v1/template/projects', 'alias' => 'api/v1/template/project/list'],
        ['method' => 'GET', 'canonical' => 'api/v1/custom-fields', 'alias' => 'api/v1/custom-field/list'],
        ['method' => 'POST', 'canonical' => 'api/v1/custom-fields', 'alias' => 'api/v1/custom-field/create', 'payload' => [
            'scope' => 'task',
            'code' => 'perm_test',
            'title' => 'Perm test',
            'type' => 'text',
        ]],
        ['method' => 'GET', 'canonical' => 'api/v1/workflow/rules', 'alias' => 'api/v1/workflow/rule/list'],
        ['method' => 'POST', 'canonical' => 'api/v1/workflow/rules', 'alias' => 'api/v1/workflow/rule/create', 'payload' => [
            'title' => 'Permission test',
            'trigger_code' => 'task_created',
            'action_code' => 'send_notification',
        ]],
        ['method' => 'GET', 'canonical' => 'api/v1/sla/policies', 'alias' => 'api/v1/sla/list'],
        ['method' => 'POST', 'canonical' => 'api/v1/sla/policies', 'alias' => 'api/v1/sla/create', 'payload' => [
            'title' => 'Permission SLA',
            'response_minutes' => 15,
            'resolve_minutes' => 60,
        ]],
        ['method' => 'GET', 'canonical' => 'api/v1/approvals', 'alias' => 'api/v1/approval/list'],
        ['method' => 'POST', 'canonical' => 'api/v1/approvals', 'alias' => 'api/v1/approval/request', 'payload' => [
            'entity_type' => 'task',
            'entity_public_id' => 'tsk_perm_dummy',
            'reviewer_public_ids' => ['usr_perm_dummy'],
        ]],
        ['method' => 'GET', 'canonical' => 'api/v1/recycle-bin', 'alias' => 'api/v1/recycle-bin/list'],
        ['method' => 'POST', 'canonical' => 'api/v1/import/jobs', 'alias' => 'api/v1/import/create', 'payload' => [
            'entity_type' => 'tasks',
            'rows' => [['title' => 'Task import sample']],
        ]],
        ['method' => 'POST', 'canonical' => 'api/v1/export/jobs', 'alias' => 'api/v1/export/create', 'payload' => [
            'entity_type' => 'tasks',
            'format' => 'csv',
        ]],
        ['method' => 'GET', 'canonical' => 'api/v1/feature-flags', 'alias' => 'api/v1/feature-flags/list'],
        ['method' => 'GET', 'canonical' => 'api/v1/organizations', 'alias' => 'api/v1/organization/list'],
        ['method' => 'GET', 'canonical' => 'api/v1/retention/metadata', 'alias' => 'api/v1/retention/get'],
    ];

    foreach ($pairs as $pair) {
        $canonicalUnauthorized = liveRequest((string)$pair['method'], (string)$pair['canonical'], (array)($pair['payload'] ?? []));
        $aliasUnauthorized = liveRequest((string)$pair['method'], (string)$pair['alias'], (array)($pair['payload'] ?? []));

        liveAssert($canonicalUnauthorized['status'] === 401, 'Canonical must require auth: ' . $pair['canonical']);
        liveAssert($aliasUnauthorized['status'] === 401, 'Alias must require auth: ' . $pair['alias']);
        liveAssert((string)($canonicalUnauthorized['payload']['code'] ?? '') === 'UNAUTHORIZED', 'Canonical unauthorized code mismatch: ' . $pair['canonical']);
        liveAssert((string)($aliasUnauthorized['payload']['code'] ?? '') === 'UNAUTHORIZED', 'Alias unauthorized code mismatch: ' . $pair['alias']);
    }

    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'neg_alias_perm_' . strtolower($suffix),
        'title' => 'Negative Alias Permission ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'neg_alias_perm_' . strtolower($suffix);
    $token = 'neg-alias-perm-token-' . strtolower($suffix);
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'NegAliasPerm123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'NegAliasPerm123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User access token required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    foreach ($pairs as $pair) {
        $canonicalForbidden = liveRequest((string)$pair['method'], (string)$pair['canonical'], (array)($pair['payload'] ?? []), $userHeaders);
        $aliasForbidden = liveRequest((string)$pair['method'], (string)$pair['alias'], (array)($pair['payload'] ?? []), $userHeaders);

        liveAssert($canonicalForbidden['status'] === 403, 'Canonical must be forbidden: ' . $pair['method'] . ' ' . $pair['canonical']);
        liveAssert($aliasForbidden['status'] === 403, 'Alias must be forbidden: ' . $pair['method'] . ' ' . $pair['alias']);

        $canonicalCode = (string)($canonicalForbidden['payload']['code'] ?? '');
        $aliasCode = (string)($aliasForbidden['payload']['code'] ?? '');
        liveAssert($canonicalCode !== '', 'Canonical forbidden code is required: ' . $pair['canonical']);
        liveAssert($aliasCode !== '', 'Alias forbidden code is required: ' . $pair['alias']);
        liveAssert($canonicalCode === $aliasCode, 'Forbidden code mismatch between canonical and alias: ' . $pair['canonical'] . ' vs ' . $pair['alias']);
    }

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_alias_permission_parity_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_alias_permission_parity_live: ' . $e->getMessage() . "\n");
    exit(1);
}

