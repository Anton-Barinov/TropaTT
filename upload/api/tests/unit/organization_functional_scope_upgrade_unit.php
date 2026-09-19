<?php
declare(strict_types=1);

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationFunctionalScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationFunctionalScopeMigration;

function functionalScopeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), title VARCHAR(255))');
$pdo->exec("INSERT INTO organizations (public_id,title) VALUES ('org_default_workspace','Default')");
$pdo->exec('CREATE TABLE tasks (id INTEGER PRIMARY KEY, organization_id INTEGER NULL)');
$pdo->exec('CREATE TABLE task_activity_events (id INTEGER PRIMARY KEY, task_id INTEGER)');
$pdo->exec('CREATE TABLE project_modules (id INTEGER PRIMARY KEY)');
$pdo->exec('CREATE TABLE project_module_tasks (id INTEGER PRIMARY KEY, module_id INTEGER)');
$pdo->exec('CREATE TABLE knowledge_spaces (id INTEGER PRIMARY KEY, organization_id INTEGER NULL)');
$pdo->exec('CREATE TABLE knowledge_pages (id INTEGER PRIMARY KEY, organization_id INTEGER NULL)');
$pdo->exec('CREATE TABLE knowledge_page_versions (id INTEGER PRIMARY KEY, page_id INTEGER)');
$pdo->exec('INSERT INTO tasks (id, organization_id) VALUES (10, 7)');
$pdo->exec('INSERT INTO task_activity_events (id, task_id) VALUES (1, 10)');
$pdo->exec('INSERT INTO project_modules (id) VALUES (20)');
$pdo->exec('INSERT INTO project_module_tasks (id, module_id) VALUES (2, 20)');
$pdo->exec('INSERT INTO knowledge_pages (id, organization_id) VALUES (30, 8)');
$pdo->exec('INSERT INTO knowledge_page_versions (id, page_id) VALUES (3, 30)');

$migration = new OrganizationFunctionalScopeMigration();
functionalScopeAssert($migration->key() === '20260919_000007_organization_functional_scope', 'Unexpected functional scope key');
$migration->up($pdo, 'sqlite');

functionalScopeAssert((int)$pdo->query('SELECT organization_id FROM task_activity_events WHERE id = 1')->fetchColumn() === 7, 'Task history must inherit task workspace');
functionalScopeAssert((int)$pdo->query('SELECT organization_id FROM project_module_tasks WHERE id = 2')->fetchColumn() === 1, 'Module child must receive safe default when parent had no scope');
functionalScopeAssert((int)$pdo->query('SELECT organization_id FROM knowledge_page_versions WHERE id = 3')->fetchColumn() === 8, 'Knowledge version must inherit page workspace');

$migration->up($pdo, 'sqlite');
functionalScopeAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_task_activity_events_organization'")->fetchColumn() === 1, 'Retry must not duplicate indexes');
echo "[OK] functional feature stores are scoped, parent backfills are preserved, and migration is idempotent\n";
