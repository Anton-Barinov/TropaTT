<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use Throwable;

final class ListQueryIndexesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260419_000006_list_query_indexes';
    }

    public function description(): string
    {
        return 'Hardening indexes for list/search/cursor endpoints (tasks/projects/logs/audit/activity)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $indexes = [
            // tasks list/search/cursor
            ['tasks', 'idx_tasks_updated_public', 'updated_at, public_id'],
            ['tasks', 'idx_tasks_active_updated', 'deleted_at, archived_at, updated_at, public_id'],
            ['tasks', 'idx_tasks_project_active_updated', 'project_id, deleted_at, archived_at, updated_at'],
            ['tasks', 'idx_tasks_status_active_updated', 'status_code, deleted_at, archived_at, updated_at'],
            ['tasks', 'idx_tasks_priority_active_updated', 'priority_code, deleted_at, archived_at, updated_at'],
            ['tasks', 'idx_tasks_assignee_active_updated', 'assignee_user_id, deleted_at, archived_at, updated_at'],
            ['tasks', 'idx_tasks_creator_active_updated', 'creator_user_id, deleted_at, archived_at, updated_at'],

            // projects list/search/cursor
            ['projects', 'idx_projects_updated_public', 'updated_at, public_id'],
            ['projects', 'idx_projects_archived_updated', 'archived_at, updated_at, public_id'],
            ['projects', 'idx_projects_creator_archived_updated', 'created_by_user_id, archived_at, updated_at'],
            ['projects', 'idx_projects_manager_archived_updated', 'manager_user_id, archived_at, updated_at'],

            // logs/activity feed
            ['request_logs', 'idx_request_logs_created', 'created_at'],
            ['request_logs', 'idx_request_logs_user_created', 'user_public_id, created_at'],
            ['request_logs', 'idx_request_logs_method_created', 'method, created_at'],
            ['request_logs', 'idx_request_logs_result_created', 'result_code, created_at'],
            ['audit_logs', 'idx_audit_logs_created', 'created_at'],
            ['audit_logs', 'idx_audit_logs_actor_created', 'actor_public_id, created_at'],
            ['security_logs', 'idx_security_logs_created', 'created_at'],
            ['security_logs', 'idx_security_logs_actor_created', 'actor_public_id, created_at'],
            ['security_logs', 'idx_security_logs_event_created', 'event_type, created_at'],
        ];

        foreach ($indexes as [$table, $name, $columns]) {
            $this->createIndexIfMissing($pdo, (string)$table, (string)$name, (string)$columns);
        }
    }

    private function createIndexIfMissing(PDO $pdo, string $table, string $name, string $columns): void
    {
        if ($this->indexExists($pdo, $table, $name)) {
            return;
        }

        try {
            $pdo->exec(sprintf('CREATE INDEX %s ON %s(%s)', $name, $table, $columns));
        } catch (Throwable) {
            // ignore duplicate/unsupported variants to keep migration idempotent across drivers
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
        } catch (Throwable) {
            return false;
        }
    }
}
