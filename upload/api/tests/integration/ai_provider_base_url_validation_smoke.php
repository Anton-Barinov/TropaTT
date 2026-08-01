<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $invalidUrl = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'invalid_url_' . randomSuffix(),
        'title' => 'Invalid URL Provider',
        'base_url' => 'not-a-url',
        'default_model' => 'mock-url-invalid',
    ], $rootHeaders);
    assertTrue($invalidUrl['status'] === 422, 'Invalid base_url must return 422');
    assertTrue((string)($invalidUrl['payload']['code'] ?? '') === 'AI_PROVIDER_URL_INVALID', 'Invalid base_url code must be AI_PROVIDER_URL_INVALID');

    $invalidScheme = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'invalid_scheme_' . randomSuffix(),
        'title' => 'Invalid Scheme Provider',
        'base_url' => 'ftp://example.com',
        'default_model' => 'mock-url-invalid',
    ], $rootHeaders);
    assertTrue($invalidScheme['status'] === 422, 'Invalid base_url scheme must return 422');
    assertTrue((string)($invalidScheme['payload']['code'] ?? '') === 'AI_PROVIDER_URL_SCHEME_NOT_ALLOWED', 'Invalid scheme code must be AI_PROVIDER_URL_SCHEME_NOT_ALLOWED');

    $validProvider = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'valid_url_' . randomSuffix(),
        'title' => 'Valid URL Provider',
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-url-valid',
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($validProvider['status'] === 201, 'Valid https base_url must be accepted');
    $providerPublicId = (string)($validProvider['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Valid provider public_id is required');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_provider_base_url_validation_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_provider_base_url_validation_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
