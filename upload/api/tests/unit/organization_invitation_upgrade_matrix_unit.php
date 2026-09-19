<?php
declare(strict_types=1);

/**
 * Upgrade matrix for the invitation scope migration.
 *
 * Covers the database shapes that are encountered when an installation moves
 * from the pre-Organizations invitations table to the scoped lifecycle:
 * legacy rows are backfilled, partially upgraded columns are preserved, and a
 * retry is idempotent.  Run with:
 *   php upload/api/tests/unit/organization_invitation_upgrade_matrix_unit.php
 */

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationInvitationScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationInvitationScopeMigration;

function invitationUpgradeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function invitationUpgradeSchema(PDO $pdo, bool $partial): void
{
    $pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), title VARCHAR(255), slug VARCHAR(120))');
    $pdo->exec('CREATE TABLE organization_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, organization_id INTEGER, user_id INTEGER, role_code VARCHAR(32))');
    $pdo->exec('CREATE TABLE invitations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, email VARCHAR(190), invited_by_user_id INTEGER, token_hash VARCHAR(255), expires_at DATETIME, accepted_at DATETIME NULL, created_at DATETIME' . ($partial ? ', organization_id INTEGER NULL, role_code VARCHAR(32) NULL' : '') . ')');
    $pdo->exec("INSERT INTO organizations (public_id,title,slug) VALUES ('org_legacy','Legacy workspace','legacy')");
    $pdo->exec('INSERT INTO organization_memberships (organization_id,user_id,role_code) VALUES (1,7,\'owner\')');
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
invitationUpgradeSchema($pdo, false);
$pdo->exec("INSERT INTO invitations (public_id,email,invited_by_user_id,token_hash,expires_at,created_at) VALUES ('inv_legacy','legacy@example.test',7,'hash-legacy','2030-01-01','2026-01-01')");

$migration = new OrganizationInvitationScopeMigration();
invitationUpgradeAssert($migration->key() === '20260918_000002_organization_invitation_scope', 'Unexpected invitation migration key');
$migration->up($pdo, 'sqlite');

$columns = $pdo->query('PRAGMA table_info(invitations)')->fetchAll(PDO::FETCH_ASSOC);
foreach (['organization_id', 'role_code', 'revoked_at'] as $column) {
    invitationUpgradeAssert((bool)array_filter($columns, static fn(array $row): bool => ($row['name'] ?? '') === $column), "{$column} must be added");
}
invitationUpgradeAssert((int)$pdo->query("SELECT organization_id FROM invitations WHERE public_id = 'inv_legacy'")->fetchColumn() === 1, 'Legacy invitation must be assigned to the inviter workspace');
invitationUpgradeAssert((string)$pdo->query("SELECT role_code FROM invitations WHERE public_id = 'inv_legacy'")->fetchColumn() === 'member', 'Legacy invitation role must default to member');

// A second run must not duplicate indexes or overwrite an explicit role and
// organization chosen by an administrator between update requests.
$pdo->exec("UPDATE invitations SET organization_id = 99, role_code = 'admin' WHERE public_id = 'inv_legacy'");
$migration->up($pdo, 'sqlite');
invitationUpgradeAssert((int)$pdo->query("SELECT organization_id FROM invitations WHERE public_id = 'inv_legacy'")->fetchColumn() === 99, 'Retry must preserve explicit organization');
invitationUpgradeAssert((string)$pdo->query("SELECT role_code FROM invitations WHERE public_id = 'inv_legacy'")->fetchColumn() === 'admin', 'Retry must preserve explicit role');
invitationUpgradeAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_invitations_organization_status'")->fetchColumn() === 1, 'Retry must not duplicate organization index');

// A partially upgraded row with an existing scope keeps its values while a
// missing role is filled in. This mirrors an interrupted old updater.
$partial = new PDO('sqlite::memory:');
$partial->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
invitationUpgradeSchema($partial, true);
$partial->exec("INSERT INTO invitations (public_id,email,invited_by_user_id,token_hash,expires_at,created_at,organization_id,role_code) VALUES ('inv_partial','partial@example.test',7,'hash-partial','2030-01-01','2026-01-01',1,NULL)");
$migration->up($partial, 'sqlite');
invitationUpgradeAssert((int)$partial->query("SELECT organization_id FROM invitations WHERE public_id = 'inv_partial'")->fetchColumn() === 1, 'Partial upgrade must preserve organization');
invitationUpgradeAssert((string)$partial->query("SELECT role_code FROM invitations WHERE public_id = 'inv_partial'")->fetchColumn() === 'member', 'Partial upgrade must fill missing role');

echo "[OK] invitation scope upgrade matrix: legacy, partial and retry fixtures converge safely\n";
