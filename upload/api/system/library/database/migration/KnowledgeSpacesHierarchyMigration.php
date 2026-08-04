<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class KnowledgeSpacesHierarchyMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260615_000001_knowledge_spaces_hierarchy';
    }

    public function description(): string
    {
        return 'Add parent_id to knowledge_spaces for nested subspaces hierarchy';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $colType = $driver === 'sqlsrv' ? 'INT' : 'INTEGER';

        if ($driver === 'mysql') {
            $check = $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'knowledge_spaces' AND COLUMN_NAME = 'parent_id'");
        } elseif ($driver === 'pgsql') {
            $check = $pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_name = 'knowledge_spaces' AND column_name = 'parent_id'");
        } else {
            $check = $pdo->query("SELECT COUNT(*) FROM pragma_table_info('knowledge_spaces') WHERE name = 'parent_id'");
        }

        if ($check === false || (int)$check->fetchColumn() > 0) {
            return;
        }

        $pdo->exec("ALTER TABLE knowledge_spaces ADD COLUMN parent_id {$colType} NULL");

        // Driver-aware helper: vanilla MySQL has no IF NOT EXISTS on CREATE
        // INDEX, so existence is checked via information_schema first.
        IndexHelper::createIndexIfNotExists($pdo, 'knowledge_spaces', 'idx_knowledge_spaces_parent', 'parent_id');
    }
}
