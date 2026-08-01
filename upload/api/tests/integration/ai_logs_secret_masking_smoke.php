<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

use Api\System\Library\Logger\JsonLogger;

function assertDoesNotContainSecret(string $haystack, string $needle, string $message): void
{
    assertTrue($needle === '' || !str_contains($haystack, $needle), $message);
}

try {
    $rawSecret = 'log-mask-secret-' . randomSuffix();
    $rawCookie = 'crm_api_session=log-mask-cookie-' . randomSuffix();
    $logPath = sys_get_temp_dir() . '/crm-ai-log-mask-' . bin2hex(random_bytes(4)) . '.log';
    @unlink($logPath);

    $logger = new JsonLogger(['audit' => $logPath], ['password', 'token', 'authorization', 'secret', 'api_key', 'cookie', 'set-cookie']);
    $logger->audit([
        'action' => 'ai_log_masking_probe',
        'secret' => $rawSecret,
        'headers' => [
            'Authorization' => 'Bearer ' . $rawSecret,
            'Cookie' => $rawCookie,
            'Set-Cookie' => $rawCookie,
        ],
        'message' => 'authorization=Bearer ' . $rawSecret . ' cookie=' . $rawCookie . ' api_key=' . $rawSecret,
    ]);

    $rawLog = (string)file_get_contents($logPath);
    assertTrue($rawLog !== '', 'Generic logger must write audit probe');
    assertDoesNotContainSecret($rawLog, $rawSecret, 'Generic logger must mask raw secret values');
    assertDoesNotContainSecret($rawLog, $rawCookie, 'Generic logger must mask raw cookie values');
    @unlink($logPath);

    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'log_mask_' . randomSuffix(),
        'title' => 'AI Log Mask Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-log-mask',
        'provider_payload' => [
            'mock_models' => ['mock-log-mask'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $secretSet = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => $rawSecret,
    ], $rootHeaders);
    assertTrue($secretSet['status'] === 200, 'Provider secret set status must be 200');

    $requestLogs = request('GET', '/api/v1/logs/request?limit=50&method=PUT&request_route=api/v1/ai/providers/' . $providerPublicId . '/secret', [], $rootHeaders);
    assertTrue($requestLogs['status'] === 200, 'Request logs list for AI provider secret write must be 200');
    $requestItems = (array)($requestLogs['payload']['data']['items'] ?? []);
    assertTrue($requestItems !== [], 'Request logs must include AI provider secret write');
    foreach ($requestItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $payload = (string)($item['payload'] ?? '');
        assertDoesNotContainSecret($payload, $rawSecret, 'Request log payload must not contain raw provider secret');
        assertTrue(str_contains($payload, '_omitted_fields_count') || $payload === '[]' || $payload === '{}', 'Request log payload must omit non-allowlisted secret field');
    }

    $auditLogs = request('GET', '/api/v1/logs/audit?limit=50&entity_type=ai_provider&entity_public_id=' . $providerPublicId, [], $rootHeaders);
    assertTrue($auditLogs['status'] === 200, 'Audit logs list for AI provider must be 200');
    $auditItems = (array)($auditLogs['payload']['data']['items'] ?? []);
    assertTrue($auditItems !== [], 'Audit logs must include AI provider events');
    foreach ($auditItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        assertDoesNotContainSecret((string)($item['details'] ?? ''), $rawSecret, 'Audit log details must not contain raw provider secret');
    }

    $securityLogs = request('GET', '/api/v1/logs/security?limit=50&event_type=ai_provider_secret_update', [], $rootHeaders);
    assertTrue($securityLogs['status'] === 200, 'Security logs list for AI provider secret update must be 200');
    $securityItems = (array)($securityLogs['payload']['data']['items'] ?? []);
    assertTrue($securityItems !== [], 'Security logs must include provider secret update event');
    $foundProviderSecurityEvent = false;
    foreach ($securityItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $details = (string)($item['details'] ?? '');
        assertDoesNotContainSecret($details, $rawSecret, 'Security log details must not contain raw provider secret');
        if (str_contains($details, $providerPublicId)) {
            $foundProviderSecurityEvent = true;
        }
    }
    assertTrue($foundProviderSecurityEvent, 'Security logs must contain this provider secret update event');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_logs_secret_masking_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_logs_secret_masking_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
