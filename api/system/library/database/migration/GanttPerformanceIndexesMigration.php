<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use Throwable;

final class GanttPerformanceIndexesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260611_000001_gantt_performance_indexes';
    }

    public function description(): string
    {
        return 'Add missing indexes for Gantt page performance (milestones, dependencies, entity_tags)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $indexes = [
            ['milestones', 'idx_milestones_project', 'project_id', false],
            ['task_dependencies', 'idx_dep_task', 'task_id', false],
            ['task_dependencies', 'idx_dep_depends_on_task', 'depends_on_task_id', false],
            ['entity_tags', 'idx_entity_tags_entity', 'entity_type, entity_public_id', false],
        ];

        foreach ($indexes as [$table, $name, $columns, $unique]) {
            $this->createIndexIfMissing($pdo, (string)$table, (string)$name, (string)$columns, (bool)$unique);
        }
    }

    private function createIndexIfMissing(PDO $pdo, string $table, string $name, string $columns, bool $unique): void
    {
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        $sql = sprintf(
            'CREATE %s INDEX %s ON %s(%s)',
            $unique ? 'UNIQUE' : '',
            $name,
            $table,
            $columns
        );

        try {
            $pdo->exec(trim(preg_replace('/\s+/', ' ', $sql) ?? $sql));
        } catch (\Throwable $e) {
            error_log('[GanttPerformanceIndexesMigration::createIndexIfMissing] ' . $e->getMessage());
            // Keep migration idempotent across drivers / concurrent calls.
        }
    }

    private function indexExists(PDO $pdo, string $table, string $name): bool
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        try {
            if ($driver === 'sqlite') {
                $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'index' AND tbl_name = :table AND name = :name LIMIT 1");
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            if ($driver === 'sqlsrv') {
                $stmt = $pdo->prepare('SELECT TOP 1 i.name
                    FROM sys.indexes i
                    INNER JOIN sys.objects o ON o.object_id = i.object_id
                    WHERE o.name = :table AND i.name = :name');
                $stmt->execute(['table' => $table, 'name' => $name]);
                return (bool)$stmt->fetchColumn();
            }

            $stmt = $pdo->prepare(
                'SELECT 1
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = :table
                   AND index_name = :name
                 LIMIT 1'
            );
            $stmt->execute(['table' => $table, 'name' => $name]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[GanttPerformanceIndexesMigration::indexExists] ' . $e->getMessage());
            return false;
        }
    }
}
