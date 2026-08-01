<?php
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../../system/library/support/Autoloader.php';

$autoloader = new Api\System\Library\Support\Autoloader(dirname(__DIR__, 2));
$autoloader->register();

use Api\System\Library\Database\Migration\AiIndexCoverageMigration;

/** @return array<int,string> */
function sqliteIndexNames(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table");
    $stmt->execute(['table' => $table]);
    $rows = $stmt->fetchAll() ?: [];
    return array_map(static fn(array $row): string => (string)$row['name'], $rows);
}

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $migrationUp = request('POST', '/internal/migration/up', [], $headers);
    assertTrue($migrationUp['status'] === 200, 'Migration up must be 200');

    $status = request('GET', '/internal/migration/status', [], $headers);
    assertTrue($status['status'] === 200, 'Migration status must be 200');
    $applied = (array)($status['payload']['data']['migration_status']['applied'] ?? []);
    assertTrue(in_array('20260427_000034_ai_index_coverage', $applied, true), 'AI index coverage migration must be applied');

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec('CREATE TABLE ai_suggestions (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), intent_code VARCHAR(128), entity_type VARCHAR(64), entity_public_id VARCHAR(64), status VARCHAR(32), created_by_user_id INTEGER, created_at DATETIME)');
    $pdo->exec('CREATE TABLE ai_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), intent_code VARCHAR(128), scope_type VARCHAR(64), scope_public_id VARCHAR(64), status VARCHAR(32), requested_by_user_id INTEGER, created_at DATETIME)');
    $pdo->exec('CREATE TABLE ai_usage_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), intent_code VARCHAR(128), status VARCHAR(32), user_id INTEGER, created_at DATETIME)');
    $pdo->exec('CREATE TABLE ai_providers (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64))');

    $migration = new AiIndexCoverageMigration();
    $migration->up($pdo, 'sqlite');

    $suggestionIndexes = sqliteIndexNames($pdo, 'ai_suggestions');
    assertTrue(in_array('idx_ai_suggestions_public_id', $suggestionIndexes, true), 'ai_suggestions public_id index is required');
    assertTrue(in_array('idx_ai_suggestions_entity_created', $suggestionIndexes, true), 'ai_suggestions entity scope index is required');
    assertTrue(in_array('idx_ai_suggestions_status_created', $suggestionIndexes, true), 'ai_suggestions status_created index is required');

    $jobIndexes = sqliteIndexNames($pdo, 'ai_jobs');
    assertTrue(in_array('idx_ai_jobs_public_id', $jobIndexes, true), 'ai_jobs public_id index is required');
    assertTrue(in_array('idx_ai_jobs_intent_created', $jobIndexes, true), 'ai_jobs intent_created index is required');
    assertTrue(in_array('idx_ai_jobs_scope_created', $jobIndexes, true), 'ai_jobs scope_created index is required');

    $usageIndexes = sqliteIndexNames($pdo, 'ai_usage_logs');
    assertTrue(in_array('idx_ai_usage_logs_public_id', $usageIndexes, true), 'ai_usage_logs public_id index is required');
    assertTrue(in_array('idx_ai_usage_logs_intent_created', $usageIndexes, true), 'ai_usage_logs intent_created index is required');
    assertTrue(in_array('idx_ai_usage_logs_status_created', $usageIndexes, true), 'ai_usage_logs status_created index is required');

    $providerIndexes = sqliteIndexNames($pdo, 'ai_providers');
    assertTrue(in_array('idx_ai_providers_public_id', $providerIndexes, true), 'ai_providers public_id index is required');

    fwrite(STDOUT, "[OK] ai_index_coverage_smoke\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ' . $e->getMessage() . "\n");
    exit(1);
}
