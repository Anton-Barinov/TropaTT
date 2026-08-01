<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $promptRoleCode = 'ai_manage_prompts_' . randomSuffix();
    $createPromptRole = request('POST', '/api/v1/roles', [
        'code' => $promptRoleCode,
        'title' => 'AI Manage Prompts Role',
    ], $rootHeaders);
    assertTrue($createPromptRole['status'] === 201, 'Prompt role create status must be 201');
    $promptRolePublicId = (string)($createPromptRole['payload']['data']['role']['public_id'] ?? '');
    assertTrue($promptRolePublicId !== '', 'Prompt role public_id is required');

    $setPromptPerms = request('PUT', '/api/v1/roles/' . $promptRolePublicId . '/permissions', [
        'permission_codes' => ['ai.manage_prompts'],
    ], $rootHeaders);
    assertTrue($setPromptPerms['status'] === 200, 'Prompt role permissions set must be 200');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Prompt Secret Separation Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-prompt-secret-separation',
        'provider_payload' => [
            'mock_models' => ['mock-prompt-secret-separation'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'prompt-secret-separation-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Root provider secret set must be 200');

    $userLogin = 'ai.manage.prompts.' . randomSuffix();
    $userPassword = 'AiManagePrompts#2026!';
    $userToken = 'ai-manage-prompts-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Manage Prompts User',
        'role_public_ids' => [$promptRolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'Prompt user create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'Prompt user public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'Prompt user login status must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $me = request('GET', '/api/v1/auth/me', [], $userHeaders);
    assertTrue($me['status'] === 200, 'Prompt user auth/me must be 200');
    $permissionCodes = (array)($me['payload']['data']['user']['permission_codes'] ?? []);
    assertTrue(in_array('ai.manage_prompts', $permissionCodes, true), 'Prompt user must keep ai.manage_prompts');
    assertTrue(!in_array('ai.admin', $permissionCodes, true), 'ai.manage_prompts must not automatically grant ai.admin');

    $providerList = request('GET', '/api/v1/ai/providers', [], $userHeaders);
    assertTrue($providerList['status'] === 403, 'ai.manage_prompts-only user must not access provider list');

    $secretPut = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'attempt-overwrite-secret',
    ], $userHeaders);
    assertTrue($secretPut['status'] === 403, 'ai.manage_prompts-only user must not set provider secret');

    $secretDelete = request('DELETE', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [], $userHeaders);
    assertTrue($secretDelete['status'] === 403, 'ai.manage_prompts-only user must not delete provider secret');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $promptRolePublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_manage_prompts_no_provider_secret_access_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_manage_prompts_no_provider_secret_access_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
