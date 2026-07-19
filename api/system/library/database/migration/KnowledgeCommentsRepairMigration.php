<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class KnowledgeCommentsRepairMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260614_000002_knowledge_comments_repair';
    }

    public function description(): string
    {
        return 'Repair Knowledge Base comments table for existing installations';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };
        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $text = $driver === 'sqlsrv' ? 'NVARCHAR(MAX)' : 'TEXT';

        $pdo->exec("CREATE TABLE IF NOT EXISTS knowledge_comments (id {$id}, public_id VARCHAR(64) UNIQUE, page_id INTEGER NOT NULL, parent_id INTEGER NULL, user_id INTEGER NOT NULL, body {$text} NOT NULL, resolved_at {$dt} NULL, created_at {$dt}, updated_at {$dt})");

        $this->createIndex($pdo, $driver, 'knowledge_comments', 'idx_knowledge_comments_page', 'page_id, created_at');
        $this->createIndex($pdo, $driver, 'knowledge_comments', 'idx_knowledge_comments_parent', 'parent_id');
    }

    private function createIndex(PDO $pdo, string $driver, string $table, string $name, string $columns): void
    {
        if ($this->indexExists($pdo, $driver, $table, $name)) {
            return;
        }

        try {
            $pdo->exec(sprintf('CREATE INDEX %s ON %s(%s)', $name, $table, $columns));
        } catch (\Throwable $e) {
            error_log('[KnowledgeCommentsRepairMigration::createIndex] CREATE INDEX: ' . $e->getMessage());
            // Existing installations may have manually repaired indexes.
        }
    }

    private function indexExists(PDO $pdo, string $driver, string $table, string $index): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return $stmt->fetchColumn() !== false;
            }
            if ($driver === 'pgsql') {
                $stmt = $pdo->prepare('SELECT 1 FROM pg_indexes WHERE schemaname = current_schema() AND tablename = :table AND indexname = :index LIMIT 1');
                $stmt->execute(['table' => $table, 'index' => $index]);
                return $stmt->fetchColumn() !== false;
            }
            $rows = $pdo->query('PRAGMA index_list(' . $table . ')')->fetchAll() ?: [];
            foreach ($rows as $row) {
                if ((string)($row['name'] ?? '') === $index) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            error_log('[KnowledgeCommentsRepairMigration::indexExists] ' . $e->getMessage());
            return false;
        }

        return false;
    }
}
