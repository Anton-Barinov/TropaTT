<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param list<string> $allowed */
function assertCodeIn(string $actual, array $allowed, string $message): void
{
    if (!in_array($actual, $allowed, true)) {
        throw new RuntimeException($message . ' got=' . $actual);
    }
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $blockedLocal = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'ssrf_local_' . randomSuffix(),
        'title' => 'SSRF Local Provider',
        'base_url' => 'http://127.0.0.1:1234',
        'default_model' => 'mock-ssrf',
    ], $rootHeaders);
    assertTrue($blockedLocal['status'] === 422, 'Local/private base_url must be rejected');
    assertCodeIn((string)($blockedLocal['payload']['code'] ?? ''), ['AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN', 'AI_PROVIDER_URL_SCHEME_NOT_ALLOWED'], 'Local/private base_url code mismatch');

    $blockedFile = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'ssrf_file_' . randomSuffix(),
        'title' => 'SSRF File Provider',
        'base_url' => 'file:///etc/passwd',
        'default_model' => 'mock-ssrf',
    ], $rootHeaders);
    assertTrue($blockedFile['status'] === 422, 'file:// base_url must be rejected');
    assertCodeIn((string)($blockedFile['payload']['code'] ?? ''), ['AI_PROVIDER_URL_SCHEME_NOT_ALLOWED', 'AI_PROVIDER_URL_INVALID'], 'file:// base_url code mismatch');

    $blockedData = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'ssrf_data_' . randomSuffix(),
        'title' => 'SSRF Data Provider',
        'base_url' => 'data:text/plain;base64,SGVsbG8=',
        'default_model' => 'mock-ssrf',
    ], $rootHeaders);
    assertTrue($blockedData['status'] === 422, 'data: base_url must be rejected');
    assertCodeIn((string)($blockedData['payload']['code'] ?? ''), ['AI_PROVIDER_URL_SCHEME_NOT_ALLOWED', 'AI_PROVIDER_URL_INVALID'], 'data: base_url code mismatch');

    $blockedHttpPublic = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'http_public_' . randomSuffix(),
        'title' => 'HTTP Public Provider',
        'base_url' => 'http://example.com',
        'default_model' => 'mock-http',
    ], $rootHeaders);
    assertTrue($blockedHttpPublic['status'] === 422, 'http:// public base_url must be rejected in secure mode');
    assertTrue((string)($blockedHttpPublic['payload']['code'] ?? '') === 'AI_PROVIDER_URL_SCHEME_NOT_ALLOWED', 'http:// public code must be AI_PROVIDER_URL_SCHEME_NOT_ALLOWED');

    $validHttps = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'https_valid_' . randomSuffix(),
        'title' => 'HTTPS Valid Provider',
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-https',
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($validHttps['status'] === 201, 'https:// base_url must be accepted');
    $providerPublicId = (string)($validHttps['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Valid https provider public_id is required');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    $openAiClientSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/OpenAiCompatibleProviderClient.php');
    assertTrue(str_contains($openAiClientSource, 'CURLOPT_FOLLOWLOCATION, false'), 'OpenAI-compatible client must keep redirect-follow disabled');
    assertTrue(str_contains($openAiClientSource, 'CURLOPT_MAXREDIRS, 0'), 'OpenAI-compatible client must keep max redirects at 0');

    $customClientSource = (string)file_get_contents(__DIR__ . '/../../system/library/service/CustomHttpProviderClient.php');
    assertTrue(str_contains($customClientSource, 'CURLOPT_FOLLOWLOCATION, false'), 'Custom HTTP client must keep redirect-follow disabled');
    assertTrue(str_contains($customClientSource, 'CURLOPT_MAXREDIRS, 0'), 'Custom HTTP client must keep max redirects at 0');

    fwrite(STDOUT, "[OK] ai_provider_ssrf_scheme_redirect_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_provider_ssrf_scheme_redirect_guard_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
