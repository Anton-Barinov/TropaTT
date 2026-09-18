<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/** Adds workspace ownership to sprint/cycle tables on existing installs. */
final class OrganizationCycleScopeMigration implements MigrationInterface
{
    public function key(): string { return '20260919_000001_organization_cycle_scope'; }
    public function description(): string { return 'Add organization scope to work cycles and snapshots'; }

    public function up(PDO $pdo, string $driver): void
    {
        $default = $pdo->query('SELECT id FROM organizations ORDER BY id ASC LIMIT 1')->fetchColumn();
        if ($default === false || $default === null) {
            throw new \RuntimeException('Unable to resolve default organization for cycle scope migration');
        }
        $organizationId = (int)$default;
        foreach (['work_cycles', 'cycle_tasks', 'cycle_snapshots'] as $table) {
            if (!$this->tableExists($pdo, $driver, $table)) continue;
            IndexHelper::addColumnIfNotExists($pdo, $table, 'organization_id', 'INTEGER NULL', $driver);
            $stmt = $pdo->prepare("UPDATE {$table} SET organization_id = :organization_id WHERE organization_id IS NULL");
            $stmt->execute(['organization_id' => $organizationId]);
            IndexHelper::createIndexIfNotExists($pdo, $table, 'idx_' . $table . '_organization', 'organization_id', false, $driver);
        }
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
        $stmt = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table LIMIT 1');
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetchColumn();
    }
}
