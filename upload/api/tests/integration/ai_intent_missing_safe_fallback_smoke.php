<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlag(array $items, string $code): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Feature flag not found: ' . $code);
}

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

    $flags = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flags['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);

    $aiEnabledFlag = findFlag($flagItems, 'ai.enabled');
    $aiEnabledPublicId = (string)($aiEnabledFlag['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    $aiEnabledOriginal = (bool)($aiEnabledFlag['is_enabled'] ?? false);

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Intent Missing Fallback Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-intent-fallback',
        'provider_payload' => [
            'mock_models' => ['mock-intent-fallback'],
        ],
        'is_active' => 1,
        'is_default' => 0,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create must be 201');

    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'intent-fallback-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Set provider secret must be 200');

    $pdo = dbPdoFromConfig(loadDbConfig());
    $deleteIntentStmt = $pdo->prepare('DELETE FROM ai_intent_settings WHERE intent_code = :intent_code');
    assertTrue($deleteIntentStmt !== false, 'Delete intent setting statement must be prepared');
    $deleteIntentStmt->execute(['intent_code' => 'task_summary']);

    $action = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_missing_intent_' . randomSuffix(),
    ], $rootHeaders);

    $status = (int)($action['status'] ?? 0);
    $code = (string)($action['payload']['code'] ?? '');
    assertTrue($status !== 500, 'Missing intent must not produce INTERNAL_ERROR/500');
    assertTrue($code !== 'INTERNAL_ERROR', 'Missing intent must not return INTERNAL_ERROR code');
    assertTrue($status === 200, 'Missing intent should use safe fallback with successful action response');

    $reseed = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($reseed['status'] === 200, 'Intent settings list must return 200 and re-seed missing intent');

    $reseedItems = (array)($reseed['payload']['data']['items'] ?? []);
    $taskSummaryFound = false;
    foreach ($reseedItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryFound = true;
            break;
        }
    }
    assertTrue($taskSummaryFound, 'task_summary intent must be restored by baseline seed');

    request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => $aiEnabledOriginal ? 1 : 0], $rootHeaders);
    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_intent_missing_safe_fallback_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_intent_missing_safe_fallback_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
