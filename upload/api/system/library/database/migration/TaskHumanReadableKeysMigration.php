<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;
use PDOStatement;

final class TaskHumanReadableKeysMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000002_task_human_readable_keys';
    }

    public function description(): string
    {
        return 'Add human-readable task keys and project task prefixes';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            // Add columns to projects
            IndexHelper::addColumnIfNotExists($pdo, 'projects', 'task_key_prefix', 'VARCHAR(10) NULL AFTER team_public_id');
            IndexHelper::addColumnIfNotExists($pdo, 'projects', 'task_key_prefix_locked', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER task_key_prefix');

            // Add unique index on task_key_prefix (MySQL allows multiple NULLs in unique index)
            IndexHelper::createIndexIfNotExists($pdo, 'projects', 'uq_projects_task_key_prefix', 'task_key_prefix', true);

            // Add columns to tasks
            IndexHelper::addColumnIfNotExists($pdo, 'tasks', 'task_key', 'VARCHAR(32) NULL AFTER priority_code');
            IndexHelper::addColumnIfNotExists($pdo, 'tasks', 'task_key_prefix', 'VARCHAR(10) NULL AFTER task_key');
            IndexHelper::addColumnIfNotExists($pdo, 'tasks', 'task_sequence_number', 'BIGINT UNSIGNED NULL AFTER task_key_prefix');

            // Add indexes
            IndexHelper::createIndexIfNotExists($pdo, 'tasks', 'uq_tasks_task_key', 'task_key', true);
            IndexHelper::createIndexIfNotExists($pdo, 'tasks', 'idx_tasks_task_key_prefix_sequence', 'task_key_prefix, task_sequence_number');
            IndexHelper::createIndexIfNotExists($pdo, 'tasks', 'idx_tasks_task_sequence_number', 'task_sequence_number');

            // Create task_key_counters table
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_key_counters (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                scope_key VARCHAR(64) NOT NULL,
                scope_type VARCHAR(32) NOT NULL,
                project_id BIGINT UNSIGNED NULL,
                prefix VARCHAR(10) NOT NULL,
                current_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_task_key_counters_scope_key (scope_key),
                KEY idx_task_key_counters_project_id (project_id),
                KEY idx_task_key_counters_prefix (prefix)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // SQLite fallback
            $tableInfo = $pdo->query("PRAGMA table_info(projects)")->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_map(static fn(array $row): string => (string)$row['name'], $tableInfo);

            if (!in_array('task_key_prefix', $columns, true)) {
                $pdo->exec('ALTER TABLE projects ADD COLUMN task_key_prefix VARCHAR(10) NULL');
                $pdo->exec('ALTER TABLE projects ADD COLUMN task_key_prefix_locked INTEGER NOT NULL DEFAULT 0');
            }

            $tableInfo = $pdo->query("PRAGMA table_info(tasks)")->fetchAll(PDO::FETCH_ASSOC);
            $columns = array_map(static fn(array $row): string => (string)$row['name'], $tableInfo);

            if (!in_array('task_key', $columns, true)) {
                $pdo->exec('ALTER TABLE tasks ADD COLUMN task_key VARCHAR(32) NULL');
                $pdo->exec('ALTER TABLE tasks ADD COLUMN task_key_prefix VARCHAR(10) NULL');
                $pdo->exec('ALTER TABLE tasks ADD COLUMN task_sequence_number INTEGER NULL');
            }

            // Create task_key_counters table for SQLite
            $pdo->exec('CREATE TABLE IF NOT EXISTS task_key_counters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                scope_key VARCHAR(64) NOT NULL UNIQUE,
                scope_type VARCHAR(32) NOT NULL,
                project_id INTEGER NULL,
                prefix VARCHAR(10) NOT NULL,
                current_value INTEGER NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )');

            IndexHelper::createIndexIfNotExists($pdo, 'task_key_counters', 'idx_task_key_counters_project_id', 'project_id');
            IndexHelper::createIndexIfNotExists($pdo, 'task_key_counters', 'idx_task_key_counters_prefix', 'prefix');
        }

        // Backfill: generate task_key_prefix for existing projects without one
        $projects = $pdo->query("SELECT id, public_id, title FROM projects WHERE task_key_prefix IS NULL ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($projects as $project) {
            $prefix = $this->generatePrefixFromTitle((string)($project['title'] ?? ''));
            $prefix = $this->ensureUniquePrefix($pdo, $prefix, (int)$project['id']);
            $stmt = $pdo->prepare('UPDATE projects SET task_key_prefix = :prefix WHERE id = :id');
            $stmt->execute([
                'prefix' => $prefix,
                'id' => (int)$project['id'],
            ]);
        }

        // Backfill: assign task_key to existing tasks
        $this->backfillTaskKeys($pdo, $driver);
    }

    private function generatePrefixFromTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') {
            return 'PRJ';
        }

        // Extract uppercase letters and digits, take first 2-10 chars
        $cleaned = preg_replace('/[^A-Z0-9]/', '', strtoupper($title));

        if ($cleaned === '' || strlen($cleaned) < 2) {
            return 'PRJ';
        }

        // Ensure starts with a letter
        if (!preg_match('/^[A-Z]/', $cleaned)) {
            return 'PRJ';
        }

        return substr($cleaned, 0, 10);
    }

    private function ensureUniquePrefix(PDO $pdo, string $prefix, int $exceptProjectId): string
    {
        $candidate = $prefix;
        $suffix = 2;

        while (true) {
            // Exclude by numeric id (never by public_id): for legacy rows with a
            // NULL/empty public_id the SQL comparison would never match, so two
            // projects could be assigned the same prefix and later collide on
            // the unique index uq_projects_task_key_prefix.
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM projects WHERE task_key_prefix = :prefix AND id != :id');
            $stmt->execute(['prefix' => $candidate, 'id' => $exceptProjectId]);
            $count = (int)$stmt->fetchColumn();

            if ($count === 0) {
                return $candidate;
            }

            $candidate = substr($prefix, 0, 8) . (string)$suffix;
            $suffix++;
        }
    }

    private function backfillTaskKeys(PDO $pdo, string $driver): void
    {
        // Tasks with project - assign by project order
        $tasks = $pdo->query("
            SELECT t.id, t.public_id, t.project_id, p.task_key_prefix
            FROM tasks t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE t.task_key IS NULL
            ORDER BY t.project_id ASC, t.created_at ASC, t.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $projectCounters = [];
        $keyExistsStmt = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE task_key = :key');

        foreach ($tasks as $task) {
            $projectId = (int)$task['project_id'];
            $prefix = trim((string)($task['task_key_prefix'] ?? ''));
            if ($prefix === '') {
                $prefix = 'PRJ';
            }

            // Idempotency: continue numbering from the highest sequence already
            // assigned to this project instead of restarting at 1. A retry after
            // a partial run (e.g. a previously failed attempt) must never
            // regenerate an existing key such as PRJ-1 and hit the unique index
            // uq_tasks_task_key.
            if (!isset($projectCounters[$projectId])) {
                $projectCounters[$projectId] = $this->maxAssignedSequence($pdo, 'project', $projectId);
            }

            $seq = $projectCounters[$projectId] + 1;
            $taskKey = $prefix . '-' . $seq;

            // Collision guard: a previous state (e.g. two projects left sharing
            // the same prefix) may already hold this exact key — bump the
            // sequence until the key is free before updating.
            while ($this->taskKeyExists($keyExistsStmt, $taskKey)) {
                $seq++;
                $taskKey = $prefix . '-' . $seq;
            }
            $projectCounters[$projectId] = $seq;

            $stmt = $pdo->prepare('UPDATE tasks SET task_key = :task_key, task_key_prefix = :prefix, task_sequence_number = :seq WHERE id = :id');
            $stmt->execute([
                'task_key' => $taskKey,
                'prefix' => $prefix,
                'seq' => $seq,
                'id' => (int)$task['id'],
            ]);
        }

        // Tasks without project - assign global TASK prefix
        $globalTasks = $pdo->query("
            SELECT t.id, t.public_id
            FROM tasks t
            WHERE t.task_key IS NULL AND t.project_id IS NULL
            ORDER BY t.created_at ASC, t.id ASC
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $globalCounter = $this->maxAssignedSequence($pdo, 'global', null);

        foreach ($globalTasks as $task) {
            $globalCounter++;
            $taskKey = 'TASK-' . $globalCounter;

            while ($this->taskKeyExists($keyExistsStmt, $taskKey)) {
                $globalCounter++;
                $taskKey = 'TASK-' . $globalCounter;
            }

            $stmt = $pdo->prepare('UPDATE tasks SET task_key = :task_key, task_key_prefix = :prefix, task_sequence_number = :seq WHERE id = :id');
            $stmt->execute([
                'task_key' => $taskKey,
                'prefix' => 'TASK',
                'seq' => $globalCounter,
                'id' => (int)$task['id'],
            ]);
        }

        // Backfill task_key_counters for projects
        $projectMaxSeq = $pdo->query("
            SELECT t.project_id, p.task_key_prefix, MAX(t.task_sequence_number) AS max_seq
            FROM tasks t
            INNER JOIN projects p ON p.id = t.project_id
            WHERE t.project_id IS NOT NULL AND t.task_sequence_number IS NOT NULL
            GROUP BY t.project_id, p.task_key_prefix
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $now = gmdate('Y-m-d H:i:s');

        foreach ($projectMaxSeq as $row) {
            $projectId = (int)$row['project_id'];
            $prefix = (string)($row['task_key_prefix'] ?? 'PRJ');
            $maxSeq = (int)($row['max_seq'] ?? 0);

            $scopeKey = 'project:' . $projectId;

            $stmt = $pdo->prepare('INSERT IGNORE INTO task_key_counters (scope_key, scope_type, project_id, prefix, current_value, created_at, updated_at) VALUES (:scope_key, :scope_type, :project_id, :prefix, :current_value, :created_at, :updated_at)');
            $stmt->execute([
                'scope_key' => $scopeKey,
                'scope_type' => 'project',
                'project_id' => $projectId,
                'prefix' => $prefix,
                'current_value' => $maxSeq,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Global counter
        $stmt = $pdo->prepare('INSERT IGNORE INTO task_key_counters (scope_key, scope_type, project_id, prefix, current_value, created_at, updated_at) VALUES (:scope_key, :scope_type, :project_id, :prefix, :current_value, :created_at, :updated_at)');
        $stmt->execute([
            'scope_key' => 'global',
            'scope_type' => 'global',
            'project_id' => null,
            'prefix' => 'TASK',
            'current_value' => $globalCounter,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Highest numeric sequence already assigned in the given scope. Parsed from
     * the task_key suffix (not task_sequence_number) so it also covers keys
     * written by newer app code before this migration ran.
     */
    private function maxAssignedSequence(PDO $pdo, string $scope, ?int $projectId): int
    {
        if ($scope === 'project' && $projectId !== null) {
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(task_key, '-', -1) AS UNSIGNED)), 0)
                FROM tasks
                WHERE project_id = :pid AND task_key IS NOT NULL
            ");
            $stmt->execute(['pid' => $projectId]);
            return (int)$stmt->fetchColumn();
        }

        $stmt = $pdo->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(task_key, '-', -1) AS UNSIGNED)), 0)
            FROM tasks
            WHERE project_id IS NULL AND task_key IS NOT NULL
        ");
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }

    private function taskKeyExists(PDOStatement $stmt, string $key): bool
    {
        $stmt->execute(['key' => $key]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
