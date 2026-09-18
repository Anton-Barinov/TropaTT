<?php
declare(strict_types=1);

/**
 * Organization scope migration: legacy default backfill and repeatability.
 * Run: php upload/api/tests/unit/organization_scope_migration_unit.php
 */

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationScopeMigration;
function organizationScopeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, title VARCHAR(255), slug VARCHAR(120), created_at DATETIME, updated_at DATETIME)');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, is_root INTEGER DEFAULT 0)');
$pdo->exec('CREATE TABLE organization_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, organization_id INTEGER, user_id INTEGER, role_code VARCHAR(32), created_at DATETIME)');
$pdo->exec('INSERT INTO users (is_root) VALUES (1), (0)');
$pdo->exec('CREATE TABLE projects (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE)');
$pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, organization_id INTEGER NULL)');
$pdo->exec('INSERT INTO projects (public_id) VALUES ("legacy-project")');
$pdo->exec('INSERT INTO tasks (public_id, organization_id) VALUES ("legacy-task", NULL)');

$migration = new OrganizationScopeMigration();
organizationScopeAssert($migration->key() === '20260918_000001_organization_scope', 'Unexpected migration key');
$migration->up($pdo, 'sqlite');

$orgId = (int)$pdo->query('SELECT id FROM organizations ORDER BY id LIMIT 1')->fetchColumn();
organizationScopeAssert($orgId > 0, 'Default organization must be created');
organizationScopeAssert((int)$pdo->query('SELECT organization_id FROM projects LIMIT 1')->fetchColumn() === $orgId, 'Project must be backfilled');
organizationScopeAssert((int)$pdo->query('SELECT organization_id FROM tasks LIMIT 1')->fetchColumn() === $orgId, 'Task must be backfilled');
organizationScopeAssert((int)$pdo->query('SELECT COUNT(*) FROM organization_memberships')->fetchColumn() === 2, 'All users need a default membership');
organizationScopeAssert((int)$pdo->query("SELECT COUNT(*) FROM organization_memberships WHERE role_code = 'owner'")->fetchColumn() === 1, 'Exactly one owner is expected');

// A second run must not create rows, duplicate indexes, or overwrite scope.
$migration->up($pdo, 'sqlite');
organizationScopeAssert((int)$pdo->query('SELECT COUNT(*) FROM organizations')->fetchColumn() === 1, 'Migration must not duplicate default organization');
organizationScopeAssert((int)$pdo->query('SELECT COUNT(*) FROM organization_memberships')->fetchColumn() === 2, 'Migration must not duplicate memberships');
organizationScopeAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_projects_organization'")->fetchColumn() === 1, 'Organization index must exist');

$managerSource = (string)file_get_contents(__DIR__ . '/../../system/library/database/migration/MigrationManager.php');
organizationScopeAssert(str_contains($managerSource, 'new OrganizationScopeMigration()'), 'Migration must be registered in MigrationManager');

echo "[OK] organization scope migration creates default workspace, backfills legacy rows, and is idempotent\n";
