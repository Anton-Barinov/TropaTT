<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/service/AiProviderClientInterface.php';
require_once __DIR__ . '/../../system/library/service/MockAiProviderClient.php';

use Api\System\Library\Service\MockAiProviderClient;

function unitAssertProviderMap(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $client = new MockAiProviderClient();

    $timeout = $client->testConnection([
        'provider_payload' => ['simulate_test_error' => 'timeout'],
    ], 'secret');
    unitAssertProviderMap((bool)($timeout['ok'] ?? true) === false, 'Timeout testConnection must fail');
    unitAssertProviderMap((string)($timeout['code'] ?? '') === 'AI_PROVIDER_TIMEOUT', 'Timeout testConnection code mismatch');
    unitAssertProviderMap((int)($timeout['http_status'] ?? 0) === 504, 'Timeout testConnection status mismatch');

    $auth = $client->testConnection([
        'provider_payload' => ['simulate_test_error' => 'auth'],
    ], 'secret');
    unitAssertProviderMap((bool)($auth['ok'] ?? true) === false, 'Auth testConnection must fail');
    unitAssertProviderMap((string)($auth['code'] ?? '') === 'AI_PROVIDER_AUTH_FAILED', 'Auth testConnection code mismatch');
    unitAssertProviderMap((int)($auth['http_status'] ?? 0) === 401, 'Auth testConnection status mismatch');

    $generic = $client->testConnection([
        'provider_payload' => ['simulate_test_error' => 'network'],
    ], 'secret');
    unitAssertProviderMap((bool)($generic['ok'] ?? true) === false, 'Generic testConnection must fail');
    unitAssertProviderMap((string)($generic['code'] ?? '') === 'AI_PROVIDER_CONNECTION_FAILED', 'Generic testConnection code mismatch');
    unitAssertProviderMap((int)($generic['http_status'] ?? -1) === 0, 'Generic testConnection status mismatch');

    $modelsTimeout = $client->listModels([
        'provider_payload' => ['simulate_models_error' => 'timeout'],
    ], 'secret');
    unitAssertProviderMap((bool)($modelsTimeout['ok'] ?? true) === false, 'Timeout listModels must fail');
    unitAssertProviderMap((string)($modelsTimeout['code'] ?? '') === 'AI_PROVIDER_TIMEOUT', 'Timeout listModels code mismatch');

    $modelsAuth = $client->listModels([
        'provider_payload' => ['simulate_models_error' => 'auth_failed'],
    ], 'secret');
    unitAssertProviderMap((bool)($modelsAuth['ok'] ?? true) === false, 'Auth listModels must fail');
    unitAssertProviderMap((string)($modelsAuth['code'] ?? '') === 'AI_PROVIDER_AUTH_FAILED', 'Auth listModels code mismatch');

    echo "[OK] ai_provider_adapter_error_mapping_unit\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_provider_adapter_error_mapping_unit: ' . $e->getMessage() . "\n");
    exit(1);
}

