<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Add deleted_at to knowledge_spaces so a removed section (and its whole
 * sub-tree) can be kept in a recycle bin and restored later instead of being
 * destroyed immediately (issue #18 follow-up).
 */
final class KnowledgeSpacesTrashMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260913_000001_knowledge_spaces_deleted_at';
    }

    public function description(): string
    {
        return 'Add deleted_at to knowledge_spaces for the section recycle bin';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($this->hasColumn($pdo, $driver, 'knowledge_spaces', 'deleted_at')) {
            return;
        }

        $colType = $driver === 'sqlsrv' ? 'DATETIME2 NULL' : 'DATETIME NULL';
        $pdo->exec("ALTER TABLE knowledge_spaces ADD COLUMN deleted_at {$colType}");

        // Driver-aware helper: vanilla MySQL has no IF NOT EXISTS on CREATE
        // INDEX, so existence is checked via information_schema first.
        IndexHelper::createIndexIfNotExists($pdo, 'knowledge_spaces', 'idx_knowledge_spaces_deleted', 'deleted_at');
    }

    private function hasColumn(PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'mysql') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        }
        if ($driver === 'pgsql') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_name = ? AND column_name = ?');
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        }

        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pragma_table_info('{$table}') WHERE name = :name");
            $stmt->execute(['name' => $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
