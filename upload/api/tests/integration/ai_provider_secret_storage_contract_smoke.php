<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

function loadDbConfig(): array
{
    $base = require __DIR__ . '/../../config/database.php';
    $localPath = __DIR__ . '/../../config/database.local.php';
    if (is_file($localPath)) {
        $local = require $localPath;
        if (is_array($local)) {
            $base = array_replace_recursive($base, $local);
        }
    }

    return is_array($base) ? $base : [];
}

function dbPdoFromConfig(array $dbConfig): PDO
{
    $default = (string)($dbConfig['default'] ?? '');
    $connections = is_array($dbConfig['connections'] ?? null) ? (array)$dbConfig['connections'] : [];
    $conn = is_array($connections[$default] ?? null) ? (array)$connections[$default] : [];
    $driver = strtolower((string)($conn['driver'] ?? ''));

    if ($driver === 'sqlite') {
        $database = (string)($conn['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('SQLite database path is empty');
        }
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    throw new RuntimeException('Unsupported DB driver for this smoke: ' . $driver);
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'secret_storage_' . randomSuffix(),
        'title' => 'Secret Storage Provider',
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-secret-storage',
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $rawSecret = 'raw-storage-secret-' . randomSuffix();
    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => $rawSecret,
    ], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Set provider secret must return 200');

    $pdo = dbPdoFromConfig(loadDbConfig());
    $providerStmt = $pdo->prepare('SELECT id FROM ai_providers WHERE public_id = :public_id LIMIT 1');
    assertTrue($providerStmt !== false, 'Provider lookup statement must be prepared');
    $providerStmt->execute(['public_id' => $providerPublicId]);
    $providerRow = $providerStmt->fetch(PDO::FETCH_ASSOC);
    assertTrue(is_array($providerRow), 'Provider row must exist');
    $providerId = (int)($providerRow['id'] ?? 0);
    assertTrue($providerId > 0, 'Provider integer id is required for storage check');

    $secretStmt = $pdo->prepare('SELECT secret_encrypted FROM ai_provider_secrets WHERE provider_id = :provider_id LIMIT 1');
    assertTrue($secretStmt !== false, 'Provider secret statement must be prepared');
    $secretStmt->execute(['provider_id' => $providerId]);
    $secretRow = $secretStmt->fetch(PDO::FETCH_ASSOC);
    assertTrue(is_array($secretRow), 'Provider secret row must exist');

    $encrypted = trim((string)($secretRow['secret_encrypted'] ?? ''));
    assertTrue($encrypted !== '', 'Encrypted secret must be stored');
    assertTrue($encrypted !== $rawSecret, 'Stored encrypted secret must not equal raw secret');
    assertTrue(!str_contains($encrypted, $rawSecret), 'Stored encrypted secret must not contain raw secret substring');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_provider_secret_storage_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_provider_secret_storage_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
