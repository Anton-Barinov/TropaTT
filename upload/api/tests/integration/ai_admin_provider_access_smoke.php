<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param array<int,array<string,mixed>> $items
 */
function findRoleByCode(array $items, string $code): ?array
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

    $adminRole = findRoleByCode($roles, 'admin');
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

    $aiAdminRoleCode = 'ai_admin_access_' . randomSuffix();
    $createAiAdminRole = request('POST', '/api/v1/roles', [
        'code' => $aiAdminRoleCode,
        'title' => 'AI Admin Access Role',
    ], $rootHeaders);
    assertTrue($createAiAdminRole['status'] === 201, 'AI admin access role create status must be 201');
    $aiAdminRolePublicId = (string)($createAiAdminRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($aiAdminRolePublicId !== '', 'AI admin access role public_id is required');
    $cleanupRolePublicIds[] = $aiAdminRolePublicId;

    $setAiAdminPerms = request('PUT', '/api/v1/roles/' . $aiAdminRolePublicId . '/permissions', [
        'permission_codes' => ['ai.admin'],
    ], $rootHeaders);
    assertTrue($setAiAdminPerms['status'] === 200, 'AI admin access role permissions set must be 200');

    $regularRoleCode = 'ai_regular_access_' . randomSuffix();
    $createRegularRole = request('POST', '/api/v1/roles', [
        'code' => $regularRoleCode,
        'title' => 'AI Regular Access Role',
    ], $rootHeaders);
    assertTrue($createRegularRole['status'] === 201, 'Regular role create status must be 201');
    $regularRolePublicId = (string)($createRegularRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($regularRolePublicId !== '', 'Regular role public_id is required');
    $cleanupRolePublicIds[] = $regularRolePublicId;

    $setRegularPerms = request('PUT', '/api/v1/roles/' . $regularRolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRegularPerms['status'] === 200, 'Regular role permissions set must be 200');

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

    $regularLogin = 'ai.regular.' . $suffix;
    $regularPassword = 'AiRegularPass#2026!';
    $regularToken = 'ai-regular-token-' . $suffix;
    $createUser([
        'login' => $regularLogin,
        'password' => $regularPassword,
        'token' => $regularToken,
        'email' => $regularLogin . '@crm.local',
        'full_name' => 'AI Regular User',
        'role_public_ids' => [$regularRolePublicId],
    ]);
    $regularHeaders = $loginUser($regularLogin, $regularPassword, $regularToken);

    $adminRoleLogin = 'ai.adminrole.' . $suffix;
    $adminRolePassword = 'AiAdminRolePass#2026!';
    $adminRoleToken = 'ai-admin-role-token-' . $suffix;
    $createUser([
        'login' => $adminRoleLogin,
        'password' => $adminRolePassword,
        'token' => $adminRoleToken,
        'email' => $adminRoleLogin . '@crm.local',
        'full_name' => 'AI Admin Role User',
        'role_public_ids' => [$adminRolePublicId],
    ]);
    $adminRoleHeaders = $loginUser($adminRoleLogin, $adminRolePassword, $adminRoleToken);

    $aiAdminLogin = 'ai.permission.' . $suffix;
    $aiAdminPassword = 'AiPermissionPass#2026!';
    $aiAdminToken = 'ai-permission-token-' . $suffix;
    $createUser([
        'login' => $aiAdminLogin,
        'password' => $aiAdminPassword,
        'token' => $aiAdminToken,
        'email' => $aiAdminLogin . '@crm.local',
        'full_name' => 'AI Permission User',
        'role_public_ids' => [$aiAdminRolePublicId],
    ]);
    $aiAdminHeaders = $loginUser($aiAdminLogin, $aiAdminPassword, $aiAdminToken);

    $regularProviders = request('GET', '/api/v1/ai/providers', [], $regularHeaders);
    assertTrue($regularProviders['status'] === 403, 'Regular user without admin role/ai.admin must get 403 on provider list');

    $regularSettings = request('GET', '/api/v1/ai/settings', [], $regularHeaders);
    assertTrue($regularSettings['status'] === 403, 'Regular user without admin role/ai.admin must get 403 on ai settings');

    $adminRoleProviders = request('GET', '/api/v1/ai/providers', [], $adminRoleHeaders);
    assertTrue($adminRoleProviders['status'] === 200, 'User with admin role must access provider list');

    $adminRoleSettings = request('GET', '/api/v1/ai/settings', [], $adminRoleHeaders);
    assertTrue($adminRoleSettings['status'] === 200, 'User with admin role must access ai settings');

    $aiAdminProviders = request('GET', '/api/v1/ai/providers', [], $aiAdminHeaders);
    assertTrue($aiAdminProviders['status'] === 200, 'User with ai.admin must access provider list');

    $aiAdminSettings = request('GET', '/api/v1/ai/settings', [], $aiAdminHeaders);
    assertTrue($aiAdminSettings['status'] === 200, 'User with ai.admin must access ai settings');

    $rootProviders = request('GET', '/api/v1/ai/providers', [], $rootHeaders);
    assertTrue($rootProviders['status'] === 200, 'Root user must access provider list');

    $rootSettings = request('GET', '/api/v1/ai/settings', [], $rootHeaders);
    assertTrue($rootSettings['status'] === 200, 'Root user must access ai settings');

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

    fwrite(STDOUT, "[OK] ai_admin_provider_access_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_admin_provider_access_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
