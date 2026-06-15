<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

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

        $check = $pdo->query("SELECT COUNT(*) FROM pragma_table_info('knowledge_spaces') WHERE name = 'parent_id'");
        if ($check === false || (int)$check->fetchColumn() > 0) {
            return;
        }

        $pdo->exec("ALTER TABLE knowledge_spaces ADD COLUMN parent_id {$colType} NULL");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_knowledge_spaces_parent ON knowledge_spaces (parent_id)");
    }
}
