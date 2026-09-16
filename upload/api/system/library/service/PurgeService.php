<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;
use Throwable;

/**
 * Physical removal ("purge") of rows that a normal DELETE only parks in a
 * reversible state: `DELETE /projects/{id}` archives, `DELETE /tasks/{id}`
 * soft-deletes. Without a purge nothing can ever leave the database, so the
 * demo stand grew one archived fixture project per release-gate run (PRJ-436,
 * the remainder of PRJ-426).
 *
 * Deliberately narrow, because "delete for real" is the most destructive thing
 * this codebase can do:
 *  - root actors only;
 *  - only rows that are ALREADY archived / soft-deleted — a purge can never
 *    destroy live work, it can only finish a deletion that already happened;
 *  - refuses when accounted time (work_logs) hangs off the row, instead of
 *    silently dropping booked hours;
 *  - keeps the history tables (audit_logs, activity_feed, notifications) on
 *    purpose: they are logs about the entity, not rows the entity owns;
 *  - runs in one transaction and reports what it removed.
 *
 * The cascade is an explicit list: the schema declares no foreign keys, so
 * referential integrity lives in the code and a purge has to know what points
 * at the row. Tables created by optional migrations are guarded by `exists()`
 * so the same code runs against the MySQL stand and the SQLite test schema.
 */
final class PurgeService
{
    /** Columns that name a task id directly. */
    private const TASK_SCOPED = [
        'task_status_history' => 'task_id',
        'task_assignees' => 'task_id',
        'task_watchers' => 'task_id',
        'subtasks' => 'task_id',
        'reminders' => 'task_id',
        'comment_drafts' => 'task_id',
        'calendar_events' => 'task_id',
        'cycle_tasks' => 'task_id',
    ];

    /** Rows that point at an entity through (entity_type, entity_public_id). */
    private const ENTITY_LINKS = [
        'entity_tags',
        'files',
        'mentions',
        'reactions',
        'custom_field_values',
        'favorites',
        'subscriptions',
        'recurring_rules',
        'recurring_instances',
    ];

    /** @var array<string,bool> */
    private array $tableCache = [];

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function purgeProject(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return $this->fail('FORBIDDEN', 403, 'Purge is restricted to root users.');
        }

        $project = $this->project($publicId);
        if ($project === null) {
            return $this->fail('PROJECT_NOT_FOUND', 404, 'Project not found.');
        }
        if (trim((string)($project['archived_at'] ?? '')) === '') {
            return $this->fail('PROJECT_NOT_ARCHIVED', 409, 'Only an archived project can be purged.');
        }

        $tasks = $this->projectTasks((int)$project['id']);
        $live = array_filter($tasks, static fn(array $t): bool => trim((string)($t['deleted_at'] ?? '')) === '');
        if ($live !== []) {
            return $this->fail('PROJECT_HAS_LIVE_TASKS', 409, 'Every task of the project must be deleted first.');
        }

        $booked = $this->worklogCount(array_map(static fn(array $t): int => (int)$t['id'], $tasks));
        if ($booked > 0) {
            return $this->fail('PROJECT_HAS_WORKLOGS', 409, 'The project carries accounted time; delete the work logs first.');
        }

        $removed = [];
        try {
            $this->pdo->beginTransaction();
            foreach ($tasks as $task) {
                $this->purgeTaskRows((int)$task['id'], (string)$task['public_id'], 'task', $removed);
            }
            $this->deleteWhereIn($removed, 'tasks', 'project_id', [(int)$project['id']]);
            $this->deleteWhereIn($removed, 'comments', 'project_id', [(int)$project['id']]);
            $this->deleteWhereIn($removed, 'calendar_events', 'project_id', [(int)$project['id']]);
            $this->deleteWhereIn($removed, 'milestones', 'project_id', [(int)$project['id']]);
            $this->deleteWhereIn($removed, 'work_cycles', 'project_id', [(int)$project['id']]);
            $this->deleteEntityLinks($removed, 'project', (string)$project['public_id']);
            $this->purgeProjectChats((int)$project['id'], $removed);
            $this->purgeProjectModules((int)$project['id'], $removed);
            $this->deleteWhereIn($removed, 'projects', 'id', [(int)$project['id']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->fail('PURGE_FAILED', 500, $e->getMessage());
        }

        return ['ok' => true, 'removed' => $removed];
    }

    /**
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function purgeTask(string $publicId, array $actor): array
    {
        if (!(bool)($actor['is_root'] ?? false)) {
            return $this->fail('FORBIDDEN', 403, 'Purge is restricted to root users.');
        }

        $task = $this->task($publicId);
        if ($task === null) {
            return $this->fail('TASK_NOT_FOUND', 404, 'Task not found.');
        }
        if (trim((string)($task['deleted_at'] ?? '')) === '') {
            return $this->fail('TASK_NOT_DELETED', 409, 'Only a deleted task can be purged.');
        }
        if ($this->worklogCount([(int)$task['id']]) > 0) {
            return $this->fail('TASK_HAS_WORKLOGS', 409, 'The task carries accounted time; delete its work logs first.');
        }

        $removed = [];
        try {
            $this->pdo->beginTransaction();
            $this->purgeTaskRows((int)$task['id'], (string)$task['public_id'], 'task', $removed);
            $this->deleteWhereIn($removed, 'tasks', 'id', [(int)$task['id']]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return $this->fail('PURGE_FAILED', 500, $e->getMessage());
        }

        return ['ok' => true, 'removed' => $removed];
    }

    /**
     * Everything that hangs off one task row.
     *
     * @param array<string,int> $removed
     */
    private function purgeTaskRows(int $taskId, string $taskPublicId, string $entityType, array &$removed): void
    {
        if ($this->exists('checklists')) {
            $ids = $this->column('SELECT id FROM checklists WHERE task_id = ?', [$taskId]);
            if ($ids !== []) {
                $this->deleteWhereIn($removed, 'checklist_items', 'checklist_id', $ids);
            }
            $this->deleteWhereIn($removed, 'checklists', 'task_id', [$taskId]);
        }
        if ($this->exists('comments')) {
            $this->deleteWhereIn($removed, 'comments', 'task_id', [$taskId]);
        }
        foreach (self::TASK_SCOPED as $table => $column) {
            if ($this->exists($table)) {
                $this->deleteWhereIn($removed, $table, $column, [$taskId]);
            }
        }
        // A dependency names the blocked task in `task_id` and the blocker in
        // `depends_on_task_id`: both directions have to go.
        if ($this->exists('task_dependencies')) {
            $this->deleteEither($removed, 'task_dependencies', 'task_id', 'depends_on_task_id', $taskId);
        }
        if ($this->exists('task_relations')) {
            $this->deleteEither($removed, 'task_relations', 'parent_task_id', 'child_task_id', $taskId);
        }
        if ($this->exists('project_module_tasks')) {
            $this->deleteWhereIn($removed, 'project_module_tasks', 'task_id', [$taskId]);
        }
        $this->deleteEntityLinks($removed, $entityType, $taskPublicId);
    }

    /**
     * @param array<string,int> $removed
     */
    private function deleteEntityLinks(array &$removed, string $entityType, string $entityPublicId): void
    {
        foreach (self::ENTITY_LINKS as $table) {
            if (!$this->exists($table)) {
                continue;
            }
            $sql = sprintf('DELETE FROM %s WHERE entity_type = ? AND entity_public_id = ?', $table);
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$entityType, $entityPublicId]);
            $this->count($removed, $table, $stmt->rowCount());
        }
        if ($this->exists('knowledge_entity_links')) {
            $stmt = $this->pdo->prepare('DELETE FROM knowledge_entity_links WHERE entity_type = ? AND entity_public_id = ?');
            $stmt->execute([$entityType, $entityPublicId]);
            $this->count($removed, 'knowledge_entity_links', $stmt->rowCount());
        }
    }

    /**
     * @param array<string,int> $removed
     */
    private function purgeProjectChats(int $projectId, array &$removed): void
    {
        if (!$this->exists('chats')) {
            return;
        }
        $ids = $this->column('SELECT id FROM chats WHERE project_id = ?', [$projectId]);
        if ($ids === []) {
            return;
        }
        if ($this->exists('chat_messages')) {
            $this->deleteWhereIn($removed, 'chat_messages', 'chat_id', $ids);
        }
        if ($this->exists('chat_participants')) {
            $this->deleteWhereIn($removed, 'chat_participants', 'chat_id', $ids);
        }
        if ($this->exists('chat_read_markers')) {
            $this->deleteWhereIn($removed, 'chat_read_markers', 'chat_id', $ids);
        }
        $this->deleteWhereIn($removed, 'chats', 'id', $ids);
    }

    /**
     * @param array<string,int> $removed
     */
    private function purgeProjectModules(int $projectId, array &$removed): void
    {
        if (!$this->exists('project_modules')) {
            return;
        }
        $ids = $this->column('SELECT id FROM project_modules WHERE project_id = ?', [$projectId]);
        if ($ids === []) {
            return;
        }
        if ($this->exists('project_module_tasks')) {
            $this->deleteWhereIn($removed, 'project_module_tasks', 'module_id', $ids);
        }
        if ($this->exists('project_module_links')) {
            $this->deleteWhereIn($removed, 'project_module_links', 'module_id', $ids);
        }
        $this->deleteWhereIn($removed, 'project_modules', 'id', $ids);
    }

    /**
     * @param array<string,int> $removed
     * @param int[] $values
     */
    private function deleteWhereIn(array &$removed, string $table, string $column, array $values): void
    {
        if ($values === [] || !$this->exists($table)) {
            return;
        }
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s IN (%s)', $table, $column, $placeholders));
        $stmt->execute(array_values($values));
        $this->count($removed, $table, $stmt->rowCount());
    }

    /**
     * @param array<string,int> $removed
     */
    private function deleteEither(array &$removed, string $table, string $left, string $right, int $value): void
    {
        $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE %s = ? OR %s = ?', $table, $left, $right));
        $stmt->execute([$value, $value]);
        $this->count($removed, $table, $stmt->rowCount());
    }

    /**
     * @param array<string,int> $removed
     */
    private function count(array &$removed, string $table, int $rows): void
    {
        if ($rows > 0) {
            $removed[$table] = ($removed[$table] ?? 0) + $rows;
        }
    }

    /**
     * @param int[] $taskIds
     */
    private function worklogCount(array $taskIds): int
    {
        if ($taskIds === [] || !$this->exists('work_logs')) {
            return 0;
        }
        $placeholders = implode(', ', array_fill(0, count($taskIds), '?'));
        $stmt = $this->pdo->prepare(sprintf('SELECT COUNT(*) FROM work_logs WHERE task_id IN (%s)', $placeholders));
        $stmt->execute(array_values($taskIds));

        return (int)$stmt->fetchColumn();
    }

    /**
     * @return array<string,mixed>|null
     */
    private function project(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, archived_at FROM projects WHERE public_id = ? LIMIT 1');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function task(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, deleted_at FROM tasks WHERE public_id = ? LIMIT 1');
        $stmt->execute([$publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function projectTasks(int $projectId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, deleted_at FROM tasks WHERE project_id = ?');
        $stmt->execute([$projectId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int,int|string> $params
     * @return int[]
     */
    private function column(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    private function exists(string $table): bool
    {
        if (array_key_exists($table, $this->tableCache)) {
            return $this->tableCache[$table];
        }
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
                $stmt->execute([$table]);
                $found = $stmt->fetchColumn() !== false;
            } else {
                $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
                $stmt->execute([$table]);
                $found = (int)$stmt->fetchColumn() > 0;
            }
        } catch (Throwable) {
            $found = false;
        }

        return $this->tableCache[$table] = $found;
    }

    /**
     * @return array<string,mixed>
     */
    private function fail(string $code, int $status, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'status' => $status, 'message' => $message];
    }
}
