<?php
declare(strict_types=1);

/**
 * Organization scope performance/failure contract.
 *
 * This is an offline SQLite fixture: it exercises the actual scope migrations,
 * loads a representative two-workspace data set, and checks that the query
 * shapes used by the tenant boundary select the organization indexes. It also
 * proves a broken legacy schema fails closed before any partial scope work can
 * be treated as a successful update.
 */

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationScopeMigration.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationInvitationScopeMigration.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationSecondaryScopeMigration.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationRecycleBinScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationInvitationScopeMigration;
use Api\System\Library\Database\Migration\OrganizationRecycleBinScopeMigration;
use Api\System\Library\Database\Migration\OrganizationScopeMigration;
use Api\System\Library\Database\Migration\OrganizationSecondaryScopeMigration;

function queryPlanAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), slug VARCHAR(120), created_at DATETIME, updated_at DATETIME)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), is_root INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE organization_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, organization_id INTEGER, user_id INTEGER, role_code VARCHAR(32), created_at DATETIME)');
$pdo->exec('CREATE TABLE invitations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, invited_by_user_id INTEGER, token_hash VARCHAR(128), accepted_at DATETIME NULL)');
$pdo->exec('CREATE TABLE recycle_bin (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), payload TEXT, deleted_at DATETIME, restored_at DATETIME NULL)');

// Minimal legacy shapes are enough for the real migrations and keep this
// fixture independent of the current production schema's optional columns.
$tables = [
    'projects', 'tasks', 'comments', 'chat_messages', 'files',
    'import_jobs', 'export_jobs', 'ai_jobs',
];
foreach ($tables as $table) {
    $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE)");
}

$pdo->exec("INSERT INTO users (public_id, is_root) VALUES ('usr_root', 1)");
(new OrganizationScopeMigration())->up($pdo, 'sqlite');
(new OrganizationInvitationScopeMigration())->up($pdo, 'sqlite');
(new OrganizationSecondaryScopeMigration())->up($pdo, 'sqlite');
(new OrganizationRecycleBinScopeMigration())->up($pdo, 'sqlite');

$defaultOrg = (int)$pdo->query("SELECT id FROM organizations WHERE public_id = 'org_default_workspace'")->fetchColumn();
queryPlanAssert($defaultOrg > 0, 'Scope migrations must create the deterministic legacy workspace');

// Two organizations and enough rows to make SQLite choose the tenant indexes,
// mirroring the selectivity of the production list endpoints.
$pdo->exec("INSERT INTO organizations (public_id, title) VALUES ('org_beta', 'Beta')");
$betaOrg = (int)$pdo->query("SELECT id FROM organizations WHERE public_id = 'org_beta'")->fetchColumn();
$inserted = [];
foreach (['projects', 'tasks', 'comments', 'chat_messages', 'files', 'import_jobs', 'export_jobs', 'ai_jobs'] as $table) {
    $stmt = $pdo->prepare("INSERT INTO {$table} (public_id, organization_id) VALUES (?, ?)");
    for ($i = 0; $i < 1200; $i++) {
        $org = $i % 2 === 0 ? $defaultOrg : $betaOrg;
        $stmt->execute([$table . '_' . $i, $org]);
    }
    $inserted[$table] = 1200;
}

$planDetails = [];
$latencyP95 = [];
$planQueries = [
    'projects' => "SELECT id FROM projects WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'tasks' => "SELECT id FROM tasks WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'comments' => "SELECT id FROM comments WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'chat_messages' => "SELECT id FROM chat_messages WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'files' => "SELECT id FROM files WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'import_jobs' => "SELECT id FROM import_jobs WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'export_jobs' => "SELECT id FROM export_jobs WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
    'ai_jobs' => "SELECT id FROM ai_jobs WHERE organization_id = {$betaOrg} ORDER BY id DESC LIMIT 50",
];
foreach ($planQueries as $table => $sql) {
    $rows = $pdo->query('EXPLAIN QUERY PLAN ' . $sql)->fetchAll(PDO::FETCH_ASSOC);
    $detail = implode(' | ', array_map(static fn(array $row): string => (string)($row['detail'] ?? ''), $rows));
    $planDetails[$table] = $detail;
    queryPlanAssert(str_contains($detail, 'USING') && str_contains($detail, 'organization'), "{$table} tenant query must use its organization index: {$detail}");

    $samples = [];
    for ($sample = 0; $sample < 25; $sample++) {
        $started = microtime(true);
        $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $samples[] = (microtime(true) - $started) * 1000;
    }
    sort($samples);
    $latencyP95[$table] = $samples[min(count($samples) - 1, (int)floor(count($samples) * 0.95))];
    queryPlanAssert($latencyP95[$table] < 1000.0, "{$table} indexed query exceeded the 1s local failure threshold");
}

foreach ([
    'idx_invitations_organization_status',
    'idx_recycle_bin_organization_status',
] as $index) {
    queryPlanAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = '{$index}'")->fetchColumn() === 1, "{$index} must exist");
}

// Failure path: an old/corrupt installation without organizations must stop
// before the scope migration can claim success.
$broken = new PDO('sqlite::memory:');
$broken->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$failedClosed = false;
try {
    (new OrganizationScopeMigration())->up($broken, 'sqlite');
} catch (Throwable $e) {
    $failedClosed = str_contains($e->getMessage(), 'organizations table is required');
}
queryPlanAssert($failedClosed, 'Missing organizations table must fail closed with a deterministic migration error');

echo '[OK] organization scope query plans use tenant indexes for ' . count($planDetails) . ' scoped stores; migration failure path is fail-closed' . PHP_EOL;
echo '[OK] local indexed-query p95 ms: ' . json_encode($latencyP95, JSON_UNESCAPED_SLASHES) . PHP_EOL;
