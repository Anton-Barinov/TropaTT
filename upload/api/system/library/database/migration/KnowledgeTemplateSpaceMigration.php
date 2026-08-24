<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: Knowledge Template Space ACL (H-11)
 *
 * Adds space_id to knowledge_templates so templates can be scoped
 * to specific knowledge spaces. NULL means global (visible everywhere).
 *
 * Changes:
 *   1. knowledge_templates.space_id — nullable FK to knowledge_spaces.id
 *   2. Index on space_id for efficient lookup
 */
final class KnowledgeTemplateSpaceMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260824_000001_knowledge_template_space';
    }

    public function description(): string
    {
        return 'Knowledge templates: add space_id for space-scoped templates';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // 1. Add space_id column (NULL = global, visible everywhere)
        $this->addColumnIfNotExists($pdo, $driver, 'knowledge_templates', 'space_id',
            $driver === 'sqlsrv' ? 'INT NULL' : 'INTEGER NULL');

        // 2. Create index for efficient lookup by space
        try {
            IndexHelper::createIndexIfNotExists($pdo, 'knowledge_templates', 'idx_knowledge_templates_space_id', 'space_id');
        } catch (\Throwable $e) {
            error_log('[KnowledgeTemplateSpaceMigration::up] CREATE INDEX idx_knowledge_templates_space_id: ' . $e->getMessage());
        }
    }

    private function addColumnIfNotExists(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        try {
            $existing = $pdo->query("SELECT * FROM {$table} LIMIT 0");
            if ($existing !== false) {
                $colCount = $existing->columnCount();
                for ($i = 0; $i < $colCount; $i++) {
                    $meta = $existing->getColumnMeta($i);
                    if ($meta !== false && isset($meta['name']) && $meta['name'] === $column) {
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[KnowledgeTemplateSpaceMigration] column check for ' . $table . '.' . $column . ': ' . $e->getMessage());
        }

        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable $e) {
            error_log('[KnowledgeTemplateSpaceMigration] ALTER TABLE ' . $table . ' ADD ' . $column . ': ' . $e->getMessage());
        }
    }
}
