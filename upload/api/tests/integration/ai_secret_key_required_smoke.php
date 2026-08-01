<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @return string|false */
function getenvRaw(string $name)
{
    $value = getenv($name);
    return $value === false ? false : (string)$value;
}

function restoreEnvVar(string $name, string|false $value): void
{
    if ($value === false) {
        putenv($name);
        unset($_ENV[$name], $_SERVER[$name]);
        return;
    }

    putenv($name . '=' . $value);
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}

$keyPath = '';

try {
    $envSnapshot = [
        'AI_ENCRYPTION_KEY' => getenvRaw('AI_ENCRYPTION_KEY'),
        'APP_KEY' => getenvRaw('APP_KEY'),
        'WEBHOOK_SECRET_KEY' => getenvRaw('WEBHOOK_SECRET_KEY'),
    ];
    $rootDir = dirname(__DIR__, 3);
    $storageBase = (string)(getenv('CRM_STORAGE_BASE') ?: ($rootDir . '/../storage_api'));
    $keyPath = rtrim($storageBase, '/\\') . '/secrets/ai.key';
    $keyBackupPath = null;

    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Secret Key Guard Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-secret-key-model',
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    restoreEnvVar('AI_ENCRYPTION_KEY', false);
    restoreEnvVar('APP_KEY', false);
    restoreEnvVar('WEBHOOK_SECRET_KEY', false);
    if (is_file($keyPath)) {
        $keyBackupPath = $keyPath . '.bak.' . randomSuffix();
        @rename($keyPath, $keyBackupPath);
    }

    $setSecretWithoutKey = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'should-fail-without-key-' . randomSuffix(),
    ], $headers);
    assertTrue($setSecretWithoutKey['status'] === 422, 'Set secret without encryption key must return 422');
    assertTrue((string)($setSecretWithoutKey['payload']['code'] ?? '') === 'AI_SECRET_KEY_NOT_CONFIGURED', 'Set secret without encryption key must return AI_SECRET_KEY_NOT_CONFIGURED');

    restoreEnvVar('AI_ENCRYPTION_KEY', 'ai-encryption-key-' . randomSuffix());

    $setSecretWithKey = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'should-pass-with-key-' . randomSuffix(),
    ], $headers);
    assertTrue($setSecretWithKey['status'] === 200, 'Set secret with encryption key must return 200');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    if (is_string($keyBackupPath) && $keyBackupPath !== '' && is_file($keyBackupPath)) {
        @rename($keyBackupPath, $keyPath);
    }
    restoreEnvVar('AI_ENCRYPTION_KEY', $envSnapshot['AI_ENCRYPTION_KEY']);
    restoreEnvVar('APP_KEY', $envSnapshot['APP_KEY']);
    restoreEnvVar('WEBHOOK_SECRET_KEY', $envSnapshot['WEBHOOK_SECRET_KEY']);

    fwrite(STDOUT, "[OK] ai_secret_key_required_smoke\n");
    exit(0);
} catch (Throwable $e) {
    if (isset($keyBackupPath) && is_string($keyBackupPath) && $keyBackupPath !== '' && is_file($keyBackupPath)) {
        @rename($keyBackupPath, $keyPath);
    }
    if (isset($envSnapshot) && is_array($envSnapshot)) {
        restoreEnvVar('AI_ENCRYPTION_KEY', $envSnapshot['AI_ENCRYPTION_KEY'] ?? false);
        restoreEnvVar('APP_KEY', $envSnapshot['APP_KEY'] ?? false);
        restoreEnvVar('WEBHOOK_SECRET_KEY', $envSnapshot['WEBHOOK_SECRET_KEY'] ?? false);
    }
    fwrite(STDERR, '[FAIL] ai_secret_key_required_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
