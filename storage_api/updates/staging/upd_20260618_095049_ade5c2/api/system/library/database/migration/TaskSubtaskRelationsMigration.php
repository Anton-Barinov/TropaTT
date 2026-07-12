<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class TaskSubtaskRelationsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260421_000010_task_subtask_relations';
    }

    public function description(): string
    {
        return 'Store subtasks as full tasks linked by parent-child relations';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->ensureTaskRelationsTable($pdo, $driver);
        $this->migrateLegacySubtasks($pdo);
    }

    private function ensureTaskRelationsTable(PDO $pdo, string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';

        $pdo->exec("CREATE TABLE IF NOT EXISTS task_relations (
            id {$id},
            public_id VARCHAR(64) UNIQUE,
            parent_task_id INTEGER,
            child_task_id INTEGER,
            relation_type VARCHAR(32),
            sort_order INTEGER DEFAULT 0,
            legacy_subtask_public_id VARCHAR(64) NULL,
            created_at {$dt},
            updated_at {$dt}
        )");

        $this->createIndexIfMissing($pdo, 'task_relations', 'idx_task_rel_parent_type_sort', 'parent_task_id, relation_type, sort_order', false);
        $this->createIndexIfMissing($pdo, 'task_relations', 'idx_task_rel_child_type', 'child_task_id, relation_type', true);
        $this->createIndexIfMissing($pdo, 'task_relations', 'idx_task_rel_legacy', 'legacy_subtask_public_id', true);
    }

    private function migrateLegacySubtasks(PDO $pdo): void
    {
        if (!$this->tableExists($pdo, 'subtasks')) {
            return;
        }

        $rows = $pdo->query(
            'SELECT s.*, t.project_id, t.priority_code, t.creator_user_id
             FROM subtasks s
             INNER JOIN tasks t ON t.id = s.task_id'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $legacyPublicId = (string)($row['public_id'] ?? '');
            if ($legacyPublicId === '') {
                continue;
            }

            $already = $pdo->prepare('SELECT 1 FROM task_relations WHERE legacy_subtask_public_id = :legacy LIMIT 1');
            $already->execute(['legacy' => $legacyPublicId]);
            if ($already->fetchColumn() !== false) {
                continue;
            }

            $childTaskPublicId = $this->generatePublicId('tsk_');
            $now = gmdate('Y-m-d H:i:s');
            $createdAt = (string)($row['created_at'] ?? '') !== '' ? (string)$row['created_at'] : $now;
            $updatedAt = (string)($row['updated_at'] ?? '') !== '' ? (string)$row['updated_at'] : $createdAt;

            $insertTask = $pdo->prepare(
                'INSERT INTO tasks (
                    public_id, project_id, title, description, status_code, priority_code,
                    due_at, start_at, end_at, assignee_user_id, creator_user_id,
                    archived_at, deleted_at, created_at, updated_at, row_version
                ) VALUES (
                    :public_id, :project_id, :title, :description, :status_code, :priority_code,
                    :due_at, :start_at, :end_at, :assignee_user_id, :creator_user_id,
                    :archived_at, :deleted_at, :created_at, :updated_at, :row_version
                )'
            );

            $insertTask->execute([
                'public_id' => $childTaskPublicId,
                'project_id' => (int)($row['project_id'] ?? 0) ?: null,
                'title' => (string)($row['title'] ?? ''),
                'description' => '',
                'status_code' => (string)($row['status_code'] ?? 'new'),
                'priority_code' => (string)($row['priority_code'] ?? 'normal'),
                'due_at' => null,
                'start_at' => null,
                'end_at' => null,
                'assignee_user_id' => (int)($row['assignee_user_id'] ?? 0) ?: null,
                'creator_user_id' => (int)($row['creator_user_id'] ?? 0),
                'archived_at' => null,
                'deleted_at' => null,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
                'row_version' => 1,
            ]);

            $childTaskId = (int)$pdo->lastInsertId();
            if ($childTaskId <= 0) {
                continue;
            }

            $insertRelation = $pdo->prepare(
                'INSERT INTO task_relations (
                    public_id, parent_task_id, child_task_id, relation_type, sort_order,
                    legacy_subtask_public_id, created_at, updated_at
                ) VALUES (
                    :public_id, :parent_task_id, :child_task_id, :relation_type, :sort_order,
                    :legacy_subtask_public_id, :created_at, :updated_at
                )'
            );

            $insertRelation->execute([
                'public_id' => $this->generatePublicId('trl_'),
                'parent_task_id' => (int)($row['task_id'] ?? 0),
                'child_task_id' => $childTaskId,
                'relation_type' => 'subtask',
                'sort_order' => (int)($row['sort_order'] ?? 0),
                'legacy_subtask_public_id' => $legacyPublicId,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);
        }
    }

    private function tableExists(PDO $pdo, string $table): bool
    {
        try {
            $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            return true;
        } catch (\Throwable) {
            return false;
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
        } catch (\Throwable) {
            // idempotent path
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
            $stmt->execute([
                'table' => $table,
                'name' => $name,
            ]);

            return (bool)$stmt->fetchColumn();
        } catch (\Throwable) {
            return false;
        }
    }

    private function generatePublicId(string $prefix): string
    {
        return $prefix . strtoupper(bin2hex(random_bytes(8)));
    }
}
