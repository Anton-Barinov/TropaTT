<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'non_auth_locale_' . $suffix,
        'title' => 'Non Auth Locale ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => [
            'settings.manage',
            'approval.manage',
            'import.manage',
            'export.manage',
            'webhook.manage',
        ],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'non_auth_locale_' . $suffix;
    $token = 'non-auth-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'NonAuthLocale123!',
        'token' => $token,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'NonAuthLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $checks = [
        ['GET', 'api/v1/workflow/rules', 200, 'Workflow rules list'],
        ['GET', 'api/v1/workflow/runs', 200, 'Workflow runs list'],
        ['GET', 'api/v1/sla/policies', 200, 'SLA policy list'],
        ['GET', 'api/v1/sla/report', 200, 'SLA report'],
        ['GET', 'api/v1/approvals', 200, 'Approval requests list'],
        ['GET', 'api/v1/import/jobs', 200, 'Import jobs list'],
        ['GET', 'api/v1/export/jobs', 200, 'Export jobs list'],
        ['GET', 'api/v1/webhooks', 200, 'Webhook subscriptions list'],
        ['GET', 'api/v1/settings', 200, 'Settings list'],
        ['GET', 'api/v1/retention/metadata', 200, 'Retention metadata'],
    ];

    foreach ($checks as [$method, $route, $expectedStatus, $expectedMessage]) {
        $response = liveRequest($method, $route, [], $headers);
        liveAssert($response['status'] === $expectedStatus, $route . ' must return ' . $expectedStatus);
        liveAssert((string)($response['payload']['message'] ?? '') === $expectedMessage, $route . ' message must remain in authenticated locale (en-gb)');
    }

    $importValidation = liveRequest('POST', 'api/v1/import/jobs', [
        'type' => 'tasks',
        'format' => 'csv',
    ], $headers);
    liveAssert($importValidation['status'] === 422, 'Import validation must return 422');
    liveAssert((string)($importValidation['payload']['message'] ?? '') === 'Validation error', 'Validation message must stay in English');
    liveAssert((string)($importValidation['payload']['errors']['content'][0] ?? '') === 'Provide rows or content_base64', 'Validation error details must stay in English');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_non_auth_modules_locale_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_non_auth_modules_locale_live: ' . $e->getMessage() . "\n");
    exit(1);
}
