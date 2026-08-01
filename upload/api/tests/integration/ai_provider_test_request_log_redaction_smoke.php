<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function assertNoNeedle(string $haystack, string $needle, string $message): void
{
    assertTrue($needle === '' || !str_contains($haystack, $needle), $message);
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Provider Test Log Redaction ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-log-test',
        'extra_headers' => [
            'X-Workspace-Id' => 'workspace-' . randomSuffix(),
            'X-Integration-Value' => 'custom-header-secret-' . randomSuffix(),
        ],
        'provider_payload' => [
            'mock_models' => ['mock-log-test'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');

    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerGet = request('GET', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    assertTrue($providerGet['status'] === 200, 'Provider get status must be 200');
    $provider = (array)($providerGet['payload']['data']['provider'] ?? []);
    $extraHeaders = (array)($provider['extra_headers'] ?? []);
    $rawCustomHeaderSecret = (string)($extraHeaders['X-Integration-Value'] ?? '');
    assertTrue($rawCustomHeaderSecret !== '', 'Custom secret-like header value must be present in provider config for test coverage');

    $rawProviderSecret = 'provider-test-secret-' . randomSuffix();
    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => $rawProviderSecret,
    ], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Provider secret set status must be 200');

    $test = request('POST', '/api/v1/ai/providers/' . $providerPublicId . '/test', [], $rootHeaders);
    assertTrue($test['status'] === 200, 'Provider test status must be 200');

    $requestLogs = request('GET', '/api/v1/logs/request?limit=50&method=POST&request_route=api/v1/ai/providers/' . $providerPublicId . '/test', [], $rootHeaders);
    assertTrue($requestLogs['status'] === 200, 'Request logs read must be 200');
    $requestItems = (array)($requestLogs['payload']['data']['items'] ?? []);
    assertTrue($requestItems !== [], 'Request logs must include provider test request');

    foreach ($requestItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $payload = (string)($item['payload'] ?? '');
        assertNoNeedle($payload, $rawProviderSecret, 'Provider test request log payload must not contain raw provider secret');
        assertNoNeedle($payload, $rawCustomHeaderSecret, 'Provider test request log payload must not contain raw custom secret header value');
        assertTrue($payload === '[]' || $payload === '{}' || str_contains($payload, '_omitted_fields_count'), 'Provider test request log payload must remain sanitized');
    }

    $serializedRequestItems = json_encode($requestItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    assertTrue(is_string($serializedRequestItems), 'Request log items must serialize');
    assertNoNeedle($serializedRequestItems, $rawProviderSecret, 'Provider test request log entry must not expose raw provider secret');
    assertNoNeedle($serializedRequestItems, $rawCustomHeaderSecret, 'Provider test request log entry must not expose raw custom secret header value');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_provider_test_request_log_redaction_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_provider_test_request_log_redaction_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
