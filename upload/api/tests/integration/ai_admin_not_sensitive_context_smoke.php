<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param array<int,array<string,mixed>> $items
 */
function findRoleByCode727(array $items, string $code): ?array
{
    foreach ($items as $item) {
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    return null;
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $rolesList = request('GET', '/api/v1/roles', [], $rootHeaders);
    assertTrue($rolesList['status'] === 200, 'Roles list status must be 200');
    $roles = (array)($rolesList['payload']['data']['items'] ?? []);

    $cleanupRolePublicIds = [];
    $cleanupUserPublicIds = [];

    $adminRole = findRoleByCode727($roles, 'admin');
    if ($adminRole === null) {
        $createAdminRole = request('POST', '/api/v1/roles', [
            'code' => 'admin',
            'title' => 'Admin',
        ], $rootHeaders);
        assertTrue($createAdminRole['status'] === 201, 'Admin role create status must be 201 when missing');
        $adminRole = (array)($createAdminRole['payload']['data']['role'] ?? []);
        $cleanupRolePublicIds[] = (string)($adminRole['public_id'] ?? '');
    }

    $adminRolePublicId = (string)($adminRole['public_id'] ?? '');
    assertTrue($adminRolePublicId !== '', 'Admin role public_id is required');

    $aiAdminRoleCode = 'ai_admin_no_sensitive_' . randomSuffix();
    $createAiAdminRole = request('POST', '/api/v1/roles', [
        'code' => $aiAdminRoleCode,
        'title' => 'AI Admin Without Sensitive Context',
    ], $rootHeaders);
    assertTrue($createAiAdminRole['status'] === 201, 'AI admin role create status must be 201');
    $aiAdminRolePublicId = (string)($createAiAdminRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($aiAdminRolePublicId !== '', 'AI admin role public_id is required');
    $cleanupRolePublicIds[] = $aiAdminRolePublicId;

    $setAiAdminPerms = request('PUT', '/api/v1/roles/' . $aiAdminRolePublicId . '/permissions', [
        'permission_codes' => ['ai.admin'],
    ], $rootHeaders);
    assertTrue($setAiAdminPerms['status'] === 200, 'AI admin permissions set must be 200');

    $createUser = static function (array $payload) use ($rootHeaders, &$cleanupUserPublicIds): array {
        $response = request('POST', '/api/v1/users', $payload, $rootHeaders);
        assertTrue($response['status'] === 201, 'User create status must be 201');
        $publicId = (string)($response['payload']['data']['user']['public_id'] ?? '');
        assertTrue($publicId !== '', 'Created user public_id is required');
        $cleanupUserPublicIds[] = $publicId;

        return $response;
    };

    $loginUser = static function (string $login, string $password, string $token): array {
        $auth = request('POST', '/api/v1/auth/login', [
            'login' => $login,
            'password' => $password,
            'token' => $token,
        ]);
        assertTrue($auth['status'] === 200, 'User login status must be 200');
        $accessToken = (string)($auth['payload']['data']['access_token'] ?? '');
        assertTrue($accessToken !== '', 'User access token is required');

        return authHeaders($accessToken);
    };

    $suffix = randomSuffix();

    $adminRoleLogin = 'admin.no.sensitive.' . $suffix;
    $adminRolePassword = 'AdminNoSensitive#2026!';
    $adminRoleToken = 'admin-no-sensitive-token-' . $suffix;
    $createUser([
        'login' => $adminRoleLogin,
        'password' => $adminRolePassword,
        'token' => $adminRoleToken,
        'email' => $adminRoleLogin . '@crm.local',
        'full_name' => 'Admin Role Without Sensitive Context',
        'role_public_ids' => [$adminRolePublicId],
    ]);
    $adminRoleHeaders = $loginUser($adminRoleLogin, $adminRolePassword, $adminRoleToken);

    $aiAdminLogin = 'ai.admin.no.sensitive.' . $suffix;
    $aiAdminPassword = 'AiAdminNoSensitive#2026!';
    $aiAdminToken = 'ai-admin-no-sensitive-token-' . $suffix;
    $createUser([
        'login' => $aiAdminLogin,
        'password' => $aiAdminPassword,
        'token' => $aiAdminToken,
        'email' => $aiAdminLogin . '@crm.local',
        'full_name' => 'AI Admin Without Sensitive Context',
        'role_public_ids' => [$aiAdminRolePublicId],
    ]);
    $aiAdminHeaders = $loginUser($aiAdminLogin, $aiAdminPassword, $aiAdminToken);

    $adminRoleMe = request('GET', '/api/v1/auth/me', [], $adminRoleHeaders);
    assertTrue($adminRoleMe['status'] === 200, 'Admin role auth/me must be 200');
    $adminRolePermissions = (array)($adminRoleMe['payload']['data']['user']['permission_codes'] ?? []);
    assertTrue(!in_array('ai.use_sensitive_context', $adminRolePermissions, true), 'Admin role must not automatically receive ai.use_sensitive_context');

    $adminRoleProviders = request('GET', '/api/v1/ai/providers', [], $adminRoleHeaders);
    assertTrue($adminRoleProviders['status'] === 200, 'Admin role must still access ai.admin endpoints');

    $aiAdminMe = request('GET', '/api/v1/auth/me', [], $aiAdminHeaders);
    assertTrue($aiAdminMe['status'] === 200, 'ai.admin auth/me must be 200');
    $aiAdminPermissions = (array)($aiAdminMe['payload']['data']['user']['permission_codes'] ?? []);
    assertTrue(in_array('ai.admin', $aiAdminPermissions, true), 'ai.admin user must retain ai.admin permission');
    assertTrue(!in_array('ai.use_sensitive_context', $aiAdminPermissions, true), 'ai.admin must not automatically receive ai.use_sensitive_context');

    $aiAdminProviders = request('GET', '/api/v1/ai/providers', [], $aiAdminHeaders);
    assertTrue($aiAdminProviders['status'] === 200, 'ai.admin user must still access ai.admin endpoints');

    foreach ($cleanupUserPublicIds as $publicId) {
        if ($publicId === '') {
            continue;
        }
        request('DELETE', '/api/v1/users/' . $publicId, [], $rootHeaders);
    }

    foreach ($cleanupRolePublicIds as $publicId) {
        if ($publicId === '') {
            continue;
        }
        request('DELETE', '/api/v1/roles/' . $publicId, [], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] ai_admin_not_sensitive_context_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_admin_not_sensitive_context_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
