<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

/**
 * @param array<string,mixed> $payload
 */
function assertNoCyrillic(array $payload, string $context): void
{
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        $json = '';
    }

    liveAssert(!preg_match('/\p{Cyrillic}/u', $json), $context . ': response must not contain Cyrillic symbols for en-gb locale');
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'mixed_locale_' . $suffix,
        'title' => 'Mixed Locale ' . $suffix,
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

    $login = 'mixed_locale_' . $suffix;
    $token = 'mixed-locale-token-' . $suffix;
    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'MixedLocale123!',
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
        'password' => 'MixedLocale123!',
        'token' => $token,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $userToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($userToken !== '', 'User token is required');

    $headers = [
        'Authorization' => 'Bearer ' . $userToken,
        'X-Locale' => 'ru-ru',
    ];

    $successChecks = [
        ['GET', 'api/v1/workflow/rules', [], 200, 'Workflow rules list'],
        ['GET', 'api/v1/sla/policies', [], 200, 'SLA policy list'],
        ['GET', 'api/v1/approvals', [], 200, 'Approval requests list'],
        ['GET', 'api/v1/import/jobs', [], 200, 'Import jobs list'],
        ['GET', 'api/v1/export/jobs', [], 200, 'Export jobs list'],
        ['GET', 'api/v1/webhooks', [], 200, 'Webhook subscriptions list'],
        ['GET', 'api/v1/settings', [], 200, 'Settings list'],
        ['GET', 'api/v1/retention/metadata', [], 200, 'Retention metadata'],
    ];

    foreach ($successChecks as [$method, $route, $payload, $status, $message]) {
        $resp = liveRequest($method, $route, $payload, $headers);
        liveAssert($resp['status'] === $status, $route . ' must return ' . $status);
        liveAssert((string)($resp['payload']['message'] ?? '') === $message, $route . ' message mismatch');
        assertNoCyrillic($resp['payload'], $route);
    }

    $errorChecks = [
        ['POST', 'api/v1/workflow/rules', ['title' => 'x', 'trigger_code' => 'bad', 'action_code' => 'bad'], 422, 'Validation error'],
        ['POST', 'api/v1/sla/policies', ['title' => 'SLA X', 'response_minutes' => 0, 'resolve_minutes' => 10], 422, 'Validation error'],
        ['POST', 'api/v1/approvals', ['entity_type' => 'task', 'entity_public_id' => 'tsk_dummy'], 422, 'Validation error'],
        ['POST', 'api/v1/import/jobs', ['type' => 'tasks', 'format' => 'csv'], 422, 'Validation error'],
        ['POST', 'api/v1/export/jobs', ['type' => 'bad'], 422, 'Validation error'],
        ['POST', 'api/v1/webhooks', ['title' => 'Only title'], 422, 'Validation error'],
        ['GET', 'api/v1/setting/get', [], 422, 'Validation error'],
        ['POST', 'api/v1/retention/metadata', ['enabled' => 'nope'], 422, 'Validation error'],
    ];

    foreach ($errorChecks as [$method, $route, $payload, $status, $message]) {
        $resp = liveRequest($method, $route, $payload, $headers);
        liveAssert($resp['status'] === $status, $route . ' must return ' . $status);
        liveAssert((string)($resp['payload']['message'] ?? '') === $message, $route . ' error message mismatch');
        assertNoCyrillic($resp['payload'], $route);
    }

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_non_auth_modules_mixed_language_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_non_auth_modules_mixed_language_live: ' . $e->getMessage() . "\n");
    exit(1);
}
