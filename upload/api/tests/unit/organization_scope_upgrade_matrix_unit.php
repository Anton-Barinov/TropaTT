<?php
declare(strict_types=1);

/**
 * Organizations 2.0 upgrade matrix.
 *
 * This is intentionally a fixture-level test instead of a live demo test: the
 * updater must be able to run the same migration against clean legacy copies,
 * partially upgraded copies, and a retry after an interrupted request.  The
 * updater's backup/restore rollback is tested by the updater integration
 * suite; this test proves that the migration itself is safe to retry and does
 * not rewrite already scoped data.
 *
 * Run: php upload/api/tests/unit/organization_scope_upgrade_matrix_unit.php
 */

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationScopeMigration;

function organizationUpgradeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/** @return list<string> */
function organizationUpgradeScopedTables(): array
{
    return [
        'projects', 'tasks', 'clients', 'companies', 'contacts',
        'counterparties', 'teams', 'chats', 'files', 'import_jobs',
        'export_jobs', 'ai_jobs',
    ];
}

function organizationUpgradeBaseSchema(PDO $pdo, bool $withExistingScope = false): void
{
    $pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), slug VARCHAR(120), created_at DATETIME, updated_at DATETIME)');
    $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, is_root INTEGER DEFAULT 0)');
    $pdo->exec('CREATE TABLE organization_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, organization_id INTEGER, user_id INTEGER, role_code VARCHAR(32), created_at DATETIME)');

    foreach (organizationUpgradeScopedTables() as $table) {
        $scope = $withExistingScope && $table === 'projects' ? ', organization_id INTEGER NULL' : '';
        $pdo->exec("CREATE TABLE {$table} (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE{$scope})");
    }
}

function organizationUpgradeRun(PDO $pdo): void
{
    (new OrganizationScopeMigration())->up($pdo, 'sqlite');
}

// Matrix A: a populated pre-Organizations installation.  There is no
// organization row, no scope columns, and all legacy business rows must land
// in one deterministic workspace on the first run.
$legacy = new PDO('sqlite::memory:');
$legacy->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
organizationUpgradeBaseSchema($legacy);
$legacy->exec('INSERT INTO users (is_root) VALUES (1), (0)');
foreach (organizationUpgradeScopedTables() as $index => $table) {
    $legacy->exec("INSERT INTO {$table} (public_id) VALUES ('legacy-{$index}')");
}
organizationUpgradeRun($legacy);

$legacyOrgId = (int)$legacy->query('SELECT id FROM organizations ORDER BY id LIMIT 1')->fetchColumn();
organizationUpgradeAssert($legacyOrgId > 0, 'Legacy fixture must receive a default organization');
organizationUpgradeAssert((string)$legacy->query('SELECT public_id FROM organizations LIMIT 1')->fetchColumn() === 'org_default_workspace', 'Default workspace public_id must be stable');
foreach (organizationUpgradeScopedTables() as $table) {
    $column = $legacy->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    organizationUpgradeAssert((bool)array_filter($column, static fn(array $row): bool => ($row['name'] ?? '') === 'organization_id'), "{$table}.organization_id must be added");
    organizationUpgradeAssert((int)$legacy->query("SELECT organization_id FROM {$table} LIMIT 1")->fetchColumn() === $legacyOrgId, "{$table} legacy row must be backfilled");
}
organizationUpgradeAssert((int)$legacy->query('SELECT COUNT(*) FROM organization_memberships')->fetchColumn() === 2, 'Legacy users must receive exactly one membership each');
organizationUpgradeAssert((int)$legacy->query("SELECT COUNT(*) FROM organization_memberships WHERE role_code = 'owner'")->fetchColumn() === 1, 'Legacy upgrade must create exactly one owner');

// A retry after a request interruption must converge.  A row written by the
// legacy path between attempts is still NULL and must be picked up, while the
// existing organization, memberships, and indexes must not be duplicated.
$legacy->exec("INSERT INTO projects (public_id, organization_id) VALUES ('late-legacy-row', NULL)");
organizationUpgradeRun($legacy);
organizationUpgradeAssert((int)$legacy->query('SELECT COUNT(*) FROM organizations')->fetchColumn() === 1, 'Retry must not duplicate workspace');
organizationUpgradeAssert((int)$legacy->query('SELECT COUNT(*) FROM organization_memberships')->fetchColumn() === 2, 'Retry must not duplicate memberships');
organizationUpgradeAssert((int)$legacy->query("SELECT organization_id FROM projects WHERE public_id = 'late-legacy-row'")->fetchColumn() === $legacyOrgId, 'Retry must backfill rows added after the first attempt');
organizationUpgradeAssert((int)$legacy->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_projects_organization'")->fetchColumn() === 1, 'Retry must not duplicate indexes');

// Matrix B: an Organizations-aware installation with an existing workspace
// and a mixture of old/new tables. Existing scope values must be preserved;
// only missing columns/NULL values are upgraded.
$partial = new PDO('sqlite::memory:');
$partial->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
organizationUpgradeBaseSchema($partial, true);
$partial->exec("INSERT INTO organizations (public_id, title, slug) VALUES ('org_existing', 'Existing workspace', 'existing')");
$partial->exec('INSERT INTO users (is_root) VALUES (1), (0)');
$partial->exec("INSERT INTO organization_memberships (public_id, organization_id, user_id, role_code) VALUES ('orgm_existing', 1, 1, 'owner')");
$partial->exec("INSERT INTO projects (public_id, organization_id) VALUES ('already-scoped', 999)");
$partial->exec("INSERT INTO tasks (public_id) VALUES ('partial-task')");
organizationUpgradeRun($partial);

organizationUpgradeAssert((int)$partial->query('SELECT COUNT(*) FROM organizations')->fetchColumn() === 1, 'Existing workspace must be reused');
organizationUpgradeAssert((string)$partial->query('SELECT public_id FROM organizations LIMIT 1')->fetchColumn() === 'org_existing', 'Existing workspace identity must remain unchanged');
organizationUpgradeAssert((int)$partial->query("SELECT organization_id FROM projects WHERE public_id = 'already-scoped'")->fetchColumn() === 999, 'Already scoped value must never be overwritten');
organizationUpgradeAssert((int)$partial->query("SELECT organization_id FROM tasks WHERE public_id = 'partial-task'")->fetchColumn() === 1, 'Missing table scope must be backfilled');
organizationUpgradeAssert((int)$partial->query('SELECT COUNT(*) FROM organization_memberships')->fetchColumn() === 2, 'Existing memberships plus one missing membership expected');
organizationUpgradeAssert((int)$partial->query("SELECT COUNT(*) FROM organization_memberships WHERE role_code = 'owner'")->fetchColumn() === 1, 'Existing owner must remain unique');

// Matrix C: an empty clean installation has no legacy rows. The migration is
// still valid and creates the deterministic workspace without inventing users
// or business rows.
$clean = new PDO('sqlite::memory:');
$clean->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
organizationUpgradeBaseSchema($clean);
organizationUpgradeRun($clean);
organizationUpgradeAssert((int)$clean->query('SELECT COUNT(*) FROM organizations')->fetchColumn() === 1, 'Clean install must receive one workspace');
organizationUpgradeAssert((int)$clean->query('SELECT COUNT(*) FROM organization_memberships')->fetchColumn() === 0, 'Clean install must not invent memberships');

echo "[OK] organization scope upgrade matrix: legacy, partial, retry, and clean fixtures converge safely\n";
