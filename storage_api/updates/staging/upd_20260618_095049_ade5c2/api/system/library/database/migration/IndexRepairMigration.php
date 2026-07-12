<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;
use Throwable;

final class IndexRepairMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260419_000007_index_repair';
    }

    public function description(): string
    {
        return 'Repair and enforce baseline + list-query indexes for existing installations';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $indexes = [
            ['users', 'idx_users_login', 'login', false],
            ['users', 'idx_users_created_by', 'created_by_user_id', false],
            ['user_sessions', 'idx_sessions_token', 'token_hash', false],
            ['tasks', 'idx_tasks_project', 'project_id', false],
            ['tasks', 'idx_tasks_status', 'status_code', false],
            ['tasks', 'idx_tasks_due', 'due_at', false],
            ['tasks', 'idx_tasks_updated_public', 'updated_at, public_id', false],
            ['tasks', 'idx_tasks_active_updated', 'deleted_at, archived_at, updated_at, public_id', false],
            ['tasks', 'idx_tasks_project_active_updated', 'project_id, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_status_active_updated', 'status_code, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_priority_active_updated', 'priority_code, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_assignee_active_updated', 'assignee_user_id, deleted_at, archived_at, updated_at', false],
            ['tasks', 'idx_tasks_creator_active_updated', 'creator_user_id, deleted_at, archived_at, updated_at', false],
            ['projects', 'idx_projects_status', 'status_code', false],
            ['projects', 'idx_projects_updated_public', 'updated_at, public_id', false],
            ['projects', 'idx_projects_archived_updated', 'archived_at, updated_at, public_id', false],
            ['projects', 'idx_projects_creator_archived_updated', 'created_by_user_id, archived_at, updated_at', false],
            ['projects', 'idx_projects_manager_archived_updated', 'manager_user_id, archived_at, updated_at', false],
            ['comments', 'idx_comments_task', 'task_id', false],
            ['companies', 'idx_companies_created_by', 'created_by_user_id', false],
            ['clients', 'idx_clients_created_by', 'created_by_user_id', false],
            ['contacts', 'idx_contacts_created_by', 'created_by_user_id', false],
            ['task_templates', 'idx_task_templates_created_by', 'created_by_user_id', false],
            ['project_templates', 'idx_project_templates_created_by', 'created_by_user_id', false],
            ['automation_rules', 'idx_automation_rules_created_by', 'created_by_user_id', false],
            ['comment_drafts', 'uq_comment_drafts_user_task', 'user_id, task_id', true],
            ['files', 'idx_files_entity', 'entity_type, entity_public_id', false],
            ['request_logs', 'idx_request_logs_request', 'request_id', false],
            ['request_logs', 'idx_request_logs_created', 'created_at', false],
            ['request_logs', 'idx_request_logs_user_created', 'user_public_id, created_at', false],
            ['request_logs', 'idx_request_logs_method_created', 'method, created_at', false],
            ['request_logs', 'idx_request_logs_result_created', 'result_code, created_at', false],
            ['audit_logs', 'idx_audit_entity', 'entity_type, entity_public_id', false],
            ['audit_logs', 'idx_audit_logs_created', 'created_at', false],
            ['audit_logs', 'idx_audit_logs_actor_created', 'actor_public_id, created_at', false],
            ['security_logs', 'idx_security_logs_created', 'created_at', false],
            ['security_logs', 'idx_security_logs_actor_created', 'actor_public_id, created_at', false],
            ['security_logs', 'idx_security_logs_event_created', 'event_type, created_at', false],
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
        } catch (Throwable) {
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
        } catch (Throwable) {
            return false;
        }
    }
}
