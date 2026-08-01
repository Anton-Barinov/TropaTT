<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = gmdate('YmdHis') . '_' . bin2hex(random_bytes(3));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'neg_adv_' . strtolower($suffix),
        'title' => 'Negative Advanced ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'neg_adv_user_' . strtolower($suffix);
    $token = 'neg-adv-token-' . strtolower($suffix);
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'NegAdvPass123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'NegAdvPass123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token required');
    $userHeaders = ['Authorization' => 'Bearer ' . $userToken];

    $forbiddenRoutes = [
        'api/v1/template/tasks',
        'api/v1/recurring',
        'api/v1/custom-fields',
        'api/v1/workflow/rules',
        'api/v1/sla/policies',
        'api/v1/approvals',
        'api/v1/recycle-bin',
        'api/v1/import/jobs',
        'api/v1/export/jobs',
    ];

    foreach ($forbiddenRoutes as $route) {
        $response = liveRequest('GET', $route, [], $userHeaders);
        liveAssert($response['status'] === 403, 'Route must be forbidden without required permission: ' . $route);
    }

    if ($userPublicId !== '') {
        liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    }
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_permissions_negative_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_permissions_negative_live: ' . $e->getMessage() . "\n");
    exit(1);
}
