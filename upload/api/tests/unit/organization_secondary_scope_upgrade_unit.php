<?php
declare(strict_types=1);

/** Upgrade contract for dependent business stores added after the first scope release. */

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationSecondaryScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationSecondaryScopeMigration;

function secondaryScopeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), title VARCHAR(255))');
$pdo->exec("INSERT INTO organizations (public_id,title) VALUES ('org_default_workspace','Default')");
$pdo->exec('CREATE TABLE comments (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), body TEXT)');
$pdo->exec('CREATE TABLE chat_messages (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), text TEXT)');
$pdo->exec("INSERT INTO comments (public_id,body) VALUES ('c_legacy','legacy')");
$pdo->exec("INSERT INTO chat_messages (public_id,text) VALUES ('m_legacy','legacy')");

$migration = new OrganizationSecondaryScopeMigration();
secondaryScopeAssert($migration->key() === '20260918_000003_organization_secondary_scope', 'Unexpected secondary scope key');
$migration->up($pdo, 'sqlite');

foreach (['comments', 'chat_messages'] as $table) {
    $columns = $pdo->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
    secondaryScopeAssert((bool)array_filter($columns, static fn(array $row): bool => ($row['name'] ?? '') === 'organization_id'), "{$table}.organization_id must be added");
    secondaryScopeAssert((int)$pdo->query("SELECT organization_id FROM {$table} LIMIT 1")->fetchColumn() === 1, "{$table} legacy row must be backfilled");
}

// A resumed update must preserve explicit scope and avoid duplicate indexes.
$pdo->exec("UPDATE comments SET organization_id = 99 WHERE public_id = 'c_legacy'");
$migration->up($pdo, 'sqlite');
secondaryScopeAssert((int)$pdo->query("SELECT organization_id FROM comments WHERE public_id = 'c_legacy'")->fetchColumn() === 99, 'Retry must preserve explicit scope');
secondaryScopeAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_comments_organization'")->fetchColumn() === 1, 'Retry must not duplicate indexes');

echo "[OK] secondary organization scope upgrade is idempotent and backfills legacy stores\n";
