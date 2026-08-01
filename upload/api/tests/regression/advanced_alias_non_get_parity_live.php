<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $missing = 'missing_' . strtolower(gmdate('YmdHis')) . '_' . bin2hex(random_bytes(2));

    $cases = [
        [
            'method' => 'POST',
            'canonical' => 'api/v1/workflow/rules',
            'alias' => 'api/v1/workflow/rule/create',
            'payload' => [
                'title' => 'NonGet parity',
                'trigger_code' => 'task_created',
                'action_code' => 'send_notification',
            ],
        ],
        [
            'method' => 'PATCH',
            'canonical' => 'api/v1/workflow/rules/wfr_' . $missing,
            'alias' => 'api/v1/workflow/rule/update/wfr_' . $missing,
            'payload' => ['title' => 'Updated'],
        ],
        [
            'method' => 'POST',
            'canonical' => 'api/v1/sla/policies',
            'alias' => 'api/v1/sla/create',
            'payload' => [
                'title' => 'NonGet SLA',
                'response_minutes' => 10,
                'resolve_minutes' => 30,
            ],
        ],
        [
            'method' => 'PATCH',
            'canonical' => 'api/v1/sla/policies/sla_' . $missing,
            'alias' => 'api/v1/sla/update/sla_' . $missing,
            'payload' => ['title' => 'SLA updated'],
        ],
        [
            'method' => 'POST',
            'canonical' => 'api/v1/approvals/apr_' . $missing . '/approve',
            'alias' => 'api/v1/approval/approve/apr_' . $missing,
            'payload' => ['comment' => 'approve'],
        ],
        [
            'method' => 'POST',
            'canonical' => 'api/v1/recycle-bin/rcb_' . $missing . '/restore',
            'alias' => 'api/v1/recycle-bin/restore/rcb_' . $missing,
            'payload' => [],
        ],
        [
            'method' => 'DELETE',
            'canonical' => 'api/v1/recycle-bin/rcb_' . $missing . '/purge',
            'alias' => 'api/v1/recycle-bin/purge/rcb_' . $missing,
            'payload' => [],
        ],
        [
            'method' => 'POST',
            'canonical' => 'api/v1/import/jobs',
            'alias' => 'api/v1/import/create',
            'payload' => [
                'entity_type' => 'tasks',
                'rows' => [['title' => 'Import sample']],
            ],
        ],
        [
            'method' => 'POST',
            'canonical' => 'api/v1/export/jobs',
            'alias' => 'api/v1/export/create',
            'payload' => [
                'entity_type' => 'tasks',
                'format' => 'csv',
            ],
        ],
        [
            'method' => 'PATCH',
            'canonical' => 'api/v1/feature-flags/ff_' . $missing,
            'alias' => 'api/v1/feature-flags/update/ff_' . $missing,
            'payload' => ['enabled' => false],
        ],
        [
            'method' => 'PATCH',
            'canonical' => 'api/v1/organizations/org_' . $missing,
            'alias' => 'api/v1/organization/update/org_' . $missing,
            'payload' => ['title' => 'Updated org'],
        ],
        [
            'method' => 'PATCH',
            'canonical' => 'api/v1/retention/metadata',
            'alias' => 'api/v1/retention/set',
            'payload' => ['days_request_logs' => 45],
        ],
    ];

    foreach ($cases as $case) {
        $canonicalUnauthorized = liveRequest((string)$case['method'], (string)$case['canonical'], (array)($case['payload'] ?? []));
        $aliasUnauthorized = liveRequest((string)$case['method'], (string)$case['alias'], (array)($case['payload'] ?? []));

        liveAssert($canonicalUnauthorized['status'] === 401, 'Canonical must return 401 without auth: ' . $case['canonical']);
        liveAssert($aliasUnauthorized['status'] === 401, 'Alias must return 401 without auth: ' . $case['alias']);
        liveAssert((string)($canonicalUnauthorized['payload']['code'] ?? '') === 'UNAUTHORIZED', 'Canonical unauthorized code mismatch: ' . $case['canonical']);
        liveAssert((string)($aliasUnauthorized['payload']['code'] ?? '') === 'UNAUTHORIZED', 'Alias unauthorized code mismatch: ' . $case['alias']);
    }

    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'neg_non_get_' . $suffix,
        'title' => 'Negative Non-GET Parity ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'neg_non_get_' . $suffix;
    $token = 'neg-non-get-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'NegNonGet123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'NegNonGet123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    foreach ($cases as $case) {
        $canonicalForbidden = liveRequest((string)$case['method'], (string)$case['canonical'], (array)($case['payload'] ?? []), $userHeaders);
        $aliasForbidden = liveRequest((string)$case['method'], (string)$case['alias'], (array)($case['payload'] ?? []), $userHeaders);

        liveAssert($canonicalForbidden['status'] === 403, 'Canonical must return 403 without permission: ' . $case['method'] . ' ' . $case['canonical']);
        liveAssert($aliasForbidden['status'] === 403, 'Alias must return 403 without permission: ' . $case['method'] . ' ' . $case['alias']);

        $canonicalCode = (string)($canonicalForbidden['payload']['code'] ?? '');
        $aliasCode = (string)($aliasForbidden['payload']['code'] ?? '');
        liveAssert($canonicalCode !== '', 'Canonical forbidden code must be non-empty: ' . $case['canonical']);
        liveAssert($aliasCode !== '', 'Alias forbidden code must be non-empty: ' . $case['alias']);
        liveAssert($canonicalCode === $aliasCode, 'Canonical/alias forbidden code mismatch: ' . $case['canonical'] . ' vs ' . $case['alias']);
    }

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_alias_non_get_parity_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_alias_non_get_parity_live: ' . $e->getMessage() . "\n");
    exit(1);
}

