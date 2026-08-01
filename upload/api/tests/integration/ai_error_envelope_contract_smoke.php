<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array{status:int,payload:array<string,mixed>} $response */
function assertStandardErrorEnvelope(array $response, int $expectedStatus, string $message): void
{
    assertTrue($response['status'] === $expectedStatus, $message . ' (status mismatch)');

    $payload = $response['payload'];
    assertTrue(array_key_exists('success', $payload), $message . ' (missing success)');
    assertTrue((bool)$payload['success'] === false, $message . ' (success must be false)');
    assertTrue(array_key_exists('code', $payload) && trim((string)$payload['code']) !== '', $message . ' (missing code)');
    assertTrue(array_key_exists('message', $payload), $message . ' (missing message)');
    assertTrue(array_key_exists('errors', $payload), $message . ' (missing errors)');
    assertTrue(is_array($payload['errors']), $message . ' (errors must be array)');
    assertTrue(array_key_exists('meta', $payload), $message . ' (missing meta)');
    assertTrue(is_array($payload['meta']), $message . ' (meta must be array)');
    $meta = (array)$payload['meta'];
    assertTrue(trim((string)($meta['request_id'] ?? '')) !== '', $message . ' (missing meta.request_id)');
    assertTrue(trim((string)($meta['correlation_id'] ?? '')) !== '', $message . ' (missing meta.correlation_id)');
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $unauthorized = request('GET', '/api/v1/ai/action-types');
    assertStandardErrorEnvelope($unauthorized, 401, 'Unauthorized AI endpoint error envelope contract');

    $validation = request('PATCH', '/api/v1/ai/retention-policies/suggestions_ttl_days', [
        'days' => 0,
    ], $rootHeaders);
    assertStandardErrorEnvelope($validation, 422, 'Validation error envelope contract for AI retention update');

    $roleCreate = request('POST', '/api/v1/roles', [
        'code' => 'ai_err_env_' . randomSuffix(),
        'title' => 'AI Error Envelope Role',
    ], $rootHeaders);
    assertTrue($roleCreate['status'] === 201, 'Role create status must be 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    assertTrue($rolePublicId !== '', 'Role public_id is required');

    $setRolePermissions = request('PUT', '/api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['ai.use', 'task.manage'],
    ], $rootHeaders);
    assertTrue($setRolePermissions['status'] === 200, 'Set role permissions must be 200');

    $userLogin = 'ai.err.env.' . randomSuffix();
    $userPassword = 'AiErrEnvPass#2026!';
    $userToken = 'ai-err-env-token-' . randomSuffix();
    $userCreate = request('POST', '/api/v1/users', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
        'email' => $userLogin . '@crm.local',
        'full_name' => 'AI Error Envelope User',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    assertTrue($userCreate['status'] === 201, 'User create status must be 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'User public_id is required');

    $userAuth = request('POST', '/api/v1/auth/login', [
        'login' => $userLogin,
        'password' => $userPassword,
        'token' => $userToken,
    ]);
    assertTrue($userAuth['status'] === 200, 'User login status must be 200');
    $userHeaders = authHeaders((string)($userAuth['payload']['data']['access_token'] ?? ''));

    $forbidden = request('GET', '/api/v1/ai/providers', [], $userHeaders);
    assertStandardErrorEnvelope($forbidden, 403, 'Forbidden AI admin endpoint error envelope contract');

    request('DELETE', '/api/v1/users/' . $userPublicId, [], $rootHeaders);
    request('DELETE', '/api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_error_envelope_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_error_envelope_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
