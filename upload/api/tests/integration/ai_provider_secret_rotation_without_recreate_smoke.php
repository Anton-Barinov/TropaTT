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

    $create = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'rotate_secret_' . randomSuffix(),
        'title' => 'Secret Rotation Provider',
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-secret-rotation',
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($create['status'] === 201, 'Provider create must return 201');

    $providerPublicId = (string)($create['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $firstSecret = 'rotation-first-' . randomSuffix();
    $setFirst = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', ['secret' => $firstSecret], $rootHeaders);
    assertTrue($setFirst['status'] === 200, 'First secret set must return 200');

    $secondSecret = 'rotation-second-' . randomSuffix();
    $setSecond = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', ['secret' => $secondSecret], $rootHeaders);
    assertTrue($setSecond['status'] === 200, 'Second secret set (rotation) must return 200');

    $providerRead = request('GET', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
    assertTrue($providerRead['status'] === 200, 'Provider must remain readable after secret rotation');
    assertTrue((string)($providerRead['payload']['data']['provider']['public_id'] ?? '') === $providerPublicId, 'Provider public_id must stay unchanged after secret rotation');

    $pdo = dbPdoFromConfig(loadDbConfig());

    $providerCountStmt = $pdo->prepare('SELECT COUNT(*) FROM ai_providers WHERE public_id = :public_id');
    assertTrue($providerCountStmt !== false, 'Provider count statement must be prepared');
    $providerCountStmt->execute(['public_id' => $providerPublicId]);
    $providerCount = (int)$providerCountStmt->fetchColumn();
    assertTrue($providerCount === 1, 'Secret rotation must not recreate provider row');

    $providerIdStmt = $pdo->prepare('SELECT id FROM ai_providers WHERE public_id = :public_id LIMIT 1');
    assertTrue($providerIdStmt !== false, 'Provider id statement must be prepared');
    $providerIdStmt->execute(['public_id' => $providerPublicId]);
    $providerRow = $providerIdStmt->fetch(PDO::FETCH_ASSOC);
    assertTrue(is_array($providerRow), 'Provider row must exist after rotation');
    $providerId = (int)($providerRow['id'] ?? 0);
    assertTrue($providerId > 0, 'Provider id must be > 0');

    $secretRowCountStmt = $pdo->prepare('SELECT COUNT(*) FROM ai_provider_secrets WHERE provider_id = :provider_id');
    assertTrue($secretRowCountStmt !== false, 'Provider secret count statement must be prepared');
    $secretRowCountStmt->execute(['provider_id' => $providerId]);
    $secretRowCount = (int)$secretRowCountStmt->fetchColumn();
    assertTrue($secretRowCount === 1, 'Secret rotation must reuse existing provider secret row');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_provider_secret_rotation_without_recreate_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_provider_secret_rotation_without_recreate_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
