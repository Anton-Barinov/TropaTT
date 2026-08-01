<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $create = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'secret_unreadable_' . randomSuffix(),
        'title' => 'Secret Unreadable Provider',
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-secret-unreadable',
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($create['status'] === 201, 'Provider create must return 201');

    $providerPublicId = (string)($create['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $oldSecret = 'old-secret-' . randomSuffix();
    $setOld = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', ['secret' => $oldSecret], $rootHeaders);
    assertTrue($setOld['status'] === 200, 'First secret set must return 200');

    $newSecret = 'new-secret-' . randomSuffix();
    $setNew = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', ['secret' => $newSecret], $rootHeaders);
    assertTrue($setNew['status'] === 200, 'Secret rotation must return 200');

    $credentialLast4 = (string)($setNew['payload']['data']['credential']['credential_last4'] ?? '');
    assertTrue($credentialLast4 === substr($newSecret, -4), 'Rotation response must expose only last4 of current secret');

    $providerGet = request('GET', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    assertTrue($providerGet['status'] === 200, 'Provider get must return 200 after rotation');

    $providerList = request('GET', '/api/v1/ai/providers', [], $rootHeaders);
    assertTrue($providerList['status'] === 200, 'Provider list must return 200 after rotation');

    $errorResponse = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', ['secret' => '   '], $rootHeaders);
    assertTrue($errorResponse['status'] === 422, 'Validation error path must return 422');

    $getJson = json_encode($providerGet['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $listJson = json_encode($providerList['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $errorJson = json_encode($errorResponse['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    assertTrue(is_string($getJson) && !str_contains($getJson, $oldSecret), 'Old secret must not leak in provider GET response');
    assertTrue(is_string($listJson) && !str_contains($listJson, $oldSecret), 'Old secret must not leak in provider LIST response');
    assertTrue(is_string($errorJson) && !str_contains($errorJson, $oldSecret), 'Old secret must not leak in error/debug response');

    assertTrue(is_string($getJson) && !str_contains($getJson, $newSecret), 'Current secret must not leak in provider GET response');
    assertTrue(is_string($listJson) && !str_contains($listJson, $newSecret), 'Current secret must not leak in provider LIST response');
    assertTrue(is_string($errorJson) && !str_contains($errorJson, $newSecret), 'Current secret must not leak in error/debug response');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_provider_secret_rotation_old_secret_unreadable_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_provider_secret_rotation_old_secret_unreadable_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
