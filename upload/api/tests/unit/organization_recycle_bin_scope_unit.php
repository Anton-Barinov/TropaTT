<?php
declare(strict_types=1);

/**
 * Regression guard for the recycle-bin tenant boundary. It proves legacy rows
 * are backfilled from their files, retries are idempotent, and repository /
 * service paths carry the active organization while retaining no-context
 * compatibility.
 */

require_once __DIR__ . '/../../system/library/database/IndexHelper.php';
require_once __DIR__ . '/../../system/library/database/migration/MigrationInterface.php';
require_once __DIR__ . '/../../system/library/database/migration/OrganizationRecycleBinScopeMigration.php';

use Api\System\Library\Database\Migration\OrganizationRecycleBinScopeMigration;

function recycleScopeAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE organizations (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), title VARCHAR(255))');
$pdo->exec('CREATE TABLE files (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64), organization_id INTEGER NULL)');
$pdo->exec('CREATE TABLE recycle_bin (id INTEGER PRIMARY KEY AUTOINCREMENT, public_id VARCHAR(64) UNIQUE, entity_type VARCHAR(64), entity_public_id VARCHAR(64), payload TEXT, deleted_at DATETIME, restored_at DATETIME NULL)');
$pdo->exec("INSERT INTO organizations (public_id, title) VALUES ('org_a', 'A'), ('org_b', 'B')");
$pdo->exec("INSERT INTO files (public_id, organization_id) VALUES ('fil_a', 1), ('fil_b', 2)");
$pdo->exec("INSERT INTO recycle_bin (public_id, entity_type, entity_public_id, deleted_at) VALUES ('rcb_a', 'file', 'fil_a', CURRENT_TIMESTAMP), ('rcb_b', 'file', 'fil_b', CURRENT_TIMESTAMP)");

$migration = new OrganizationRecycleBinScopeMigration();
$migration->up($pdo, 'sqlite');
$migration->up($pdo, 'sqlite');

recycleScopeAssert((int)$pdo->query("SELECT organization_id FROM recycle_bin WHERE public_id = 'rcb_a'")->fetchColumn() === 1, 'Legacy file trash row must inherit file organization');
recycleScopeAssert((int)$pdo->query("SELECT organization_id FROM recycle_bin WHERE public_id = 'rcb_b'")->fetchColumn() === 2, 'Legacy file trash row must preserve second organization');
recycleScopeAssert((int)$pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'index' AND name = 'idx_recycle_bin_organization_status'")->fetchColumn() === 1, 'Retry must not duplicate recycle-bin index');

$root = dirname(__DIR__, 2);
$repo = file_get_contents($root . '/model/recycle_bin/RecycleBinRepository.php') ?: '';
$service = file_get_contents($root . '/system/library/service/RecycleBinService.php') ?: '';
$fileRepo = file_get_contents($root . '/model/file/FileRepository.php') ?: '';
$checks = [
    'repository scopes item reads and mutations' => str_contains($repo, 'findByPublicId(string $publicId, ?int $organizationId = null)') && str_contains($repo, 'deleteByPublicId(string $publicId, ?int $organizationId = null)'),
    'service scopes restore and purge' => str_contains($service, 'findByPublicId($publicId, $organizationId)') && str_contains($service, 'hardDelete((string)$file[\'public_id\'], $organizationId)'),
    'file destructive paths accept scope' => str_contains($fileRepo, 'restore(string $publicId, ?int $organizationId = null)') && str_contains($fileRepo, 'hardDelete(string $publicId, ?int $organizationId = null)'),
];
foreach ($checks as $label => $ok) {
    echo ($ok ? '[OK] ' : '[FAIL] ') . $label . PHP_EOL;
    if (!$ok) {
        exit(1);
    }
}

echo "[OK] organization recycle-bin scope: backfill, retry and destructive-path guards\n";
