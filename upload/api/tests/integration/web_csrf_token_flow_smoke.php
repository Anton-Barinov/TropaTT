<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    $csrf = (string)($root['csrf_token'] ?? '');
    assertTrue($token !== '', 'Root token is required');
    assertTrue($csrf !== '', 'Root csrf token from login is required');

    $cookies = ['crm_api_session' => $token];

    $meViaCookie = request('GET', '/api/v1/auth/me', [], [], [], $cookies);
    assertTrue($meViaCookie['status'] === 200, 'Auth me via cookie must return 200');
    $csrfFromMe = (string)($meViaCookie['payload']['data']['csrf_token'] ?? '');
    assertTrue($csrfFromMe !== '', 'Auth me via cookie must return csrf_token');

    $projectPostNoCsrf = request('POST', '/api/v1/projects', [
        'title' => 'CSRF Flow Project (no csrf) ' . randomSuffix(),
    ], [], [], $cookies);
    assertTrue($projectPostNoCsrf['status'] === 403, 'POST without CSRF must be 403');
    assertTrue((string)($projectPostNoCsrf['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'POST without CSRF must return CSRF_TOKEN_INVALID');

    $projectPost = request('POST', '/api/v1/projects', [
        'title' => 'CSRF Flow Project ' . randomSuffix(),
    ], [
        'X-CSRF-Token' => $csrfFromMe,
    ], [], $cookies);
    assertTrue($projectPost['status'] === 201, 'POST with CSRF must return 201');
    $projectPublicId = (string)($projectPost['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Created project public_id is required');

    $projectPatchNoCsrf = request('PATCH', '/api/v1/projects/' . $projectPublicId, [
        'title' => 'CSRF Patch No Token ' . randomSuffix(),
    ], [], [], $cookies);
    assertTrue($projectPatchNoCsrf['status'] === 403, 'PATCH without CSRF must be 403');
    assertTrue((string)($projectPatchNoCsrf['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'PATCH without CSRF must return CSRF_TOKEN_INVALID');

    $projectPatch = request('PATCH', '/api/v1/projects/' . $projectPublicId, [
        'title' => 'CSRF Patch OK ' . randomSuffix(),
    ], [
        'X-CSRF-Token' => $csrfFromMe,
    ], [], $cookies);
    assertTrue($projectPatch['status'] === 200, 'PATCH with CSRF must return 200');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'CSRF PUT Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-csrf-model',
        'is_default' => 0,
        'is_active' => 1,
    ], [
        'X-CSRF-Token' => $csrfFromMe,
    ], [], $cookies);
    assertTrue($providerCreate['status'] === 201, 'Provider create with CSRF must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Created provider public_id is required');

    $providerPutNoCsrf = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'csrf-put-secret-' . randomSuffix(),
    ], [], [], $cookies);
    assertTrue($providerPutNoCsrf['status'] === 403, 'PUT without CSRF must be 403');
    assertTrue((string)($providerPutNoCsrf['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'PUT without CSRF must return CSRF_TOKEN_INVALID');

    $providerPut = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'csrf-put-secret-' . randomSuffix(),
    ], [
        'X-CSRF-Token' => $csrfFromMe,
    ], [], $cookies);
    assertTrue($providerPut['status'] === 200, 'PUT with CSRF must return 200');

    $projectDeleteNoCsrf = request('DELETE', '/api/v1/projects/' . $projectPublicId, [], [], [], $cookies);
    assertTrue($projectDeleteNoCsrf['status'] === 403, 'DELETE without CSRF must be 403');
    assertTrue((string)($projectDeleteNoCsrf['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'DELETE without CSRF must return CSRF_TOKEN_INVALID');

    $projectDelete = request('DELETE', '/api/v1/projects/' . $projectPublicId, [], [
        'X-CSRF-Token' => $csrfFromMe,
    ], [], $cookies);
    assertTrue(in_array($projectDelete['status'], [200, 204], true), 'DELETE with CSRF must return 200/204');

    $rootHeaders = authHeaders($token);
    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] web_csrf_token_flow_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_csrf_token_flow_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
