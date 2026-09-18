<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Adds the tenant boundary to recycle-bin records created by file deletion. */
final class OrganizationRecycleBinScopeMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260918_000003_organization_recycle_bin_scope';
    }

    public function description(): string
    {
        return 'Add organization scope to recycle-bin records and backfill legacy files';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if (!$this->tableExists($pdo, $driver, 'recycle_bin')) {
            return;
        }

        IndexHelper::addColumnIfNotExists($pdo, 'recycle_bin', 'organization_id', 'INTEGER NULL', $driver);

        // File rows already carry the authoritative tenant after the first
        // organization migration. Other legacy recycle records use the
        // oldest workspace as a deterministic compatibility fallback.
        if ($this->tableExists($pdo, $driver, 'files')) {
            $pdo->exec(
                'UPDATE recycle_bin SET organization_id = ('
                . 'SELECT f.organization_id FROM files f '
                . 'WHERE f.public_id = recycle_bin.entity_public_id '
                . "AND recycle_bin.entity_type = 'file'"
                . ') WHERE organization_id IS NULL'
            );
        }

        if ($this->tableExists($pdo, $driver, 'organizations')) {
            $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
            if ($default !== false && $default !== null) {
                $stmt = $pdo->prepare('UPDATE recycle_bin SET organization_id = :organization_id WHERE organization_id IS NULL');
                $stmt->execute(['organization_id' => (int)$default]);
            }
        }

        IndexHelper::createIndexIfNotExists(
            $pdo,
            'recycle_bin',
            'idx_recycle_bin_organization_status',
            'organization_id, restored_at, deleted_at',
            false,
            $driver
        );
    }

    private function tableExists(PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = :table LIMIT 1");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }
        if ($driver === 'sqlsrv') {
            $stmt = $pdo->prepare("SELECT TOP 1 1 FROM sys.objects WHERE name = :table AND type = 'U'");
            $stmt->execute(['table' => $table]);
            return (bool)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
