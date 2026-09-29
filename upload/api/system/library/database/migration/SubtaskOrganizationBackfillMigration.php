<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Backfill `tasks.organization_id` for subtasks from their parent tasks.
 *
 * Subtasks created via SubtaskService::create were inserted with organization_id = NULL
 * because SubtaskService did not propagate the parent task's organization_id.
 * This caused org-scoped task lookups and time tracking (worklog creation) to fail
 * with TASK_NOT_FOUND.
 *
 * This migration backfills organization_id from the parent task for every subtask
 * where organization_id IS NULL and parent.organization_id IS NOT NULL.
 */
final class SubtaskOrganizationBackfillMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260929_000001_subtask_organization_backfill';
    }

    public function description(): string
    {
        return 'Backfill tasks.organization_id for subtasks from their parent tasks';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($this->tableHasColumn($pdo, 'tasks', 'organization_id') === false) {
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec(
                "UPDATE tasks child "
                . "JOIN task_relations r ON r.child_task_id = child.id AND r.relation_type = 'subtask' "
                . "JOIN tasks parent ON parent.id = r.parent_task_id "
                . "SET child.organization_id = parent.organization_id "
                . "WHERE child.organization_id IS NULL AND parent.organization_id IS NOT NULL"
            );
            return;
        }

        // SQLite / fallback portable path
        $pdo->exec(
            "UPDATE tasks "
            . "SET organization_id = ("
            . "  SELECT parent.organization_id FROM task_relations r "
            . "  JOIN tasks parent ON parent.id = r.parent_task_id "
            . "  WHERE r.child_task_id = tasks.id AND r.relation_type = 'subtask' "
            . "  AND parent.organization_id IS NOT NULL LIMIT 1"
            . ") "
            . "WHERE organization_id IS NULL "
            . "AND id IN ("
            . "  SELECT r.child_task_id FROM task_relations r "
            . "  JOIN tasks parent ON parent.id = r.parent_task_id "
            . "  WHERE r.relation_type = 'subtask' AND parent.organization_id IS NOT NULL"
            . ")"
        );
    }

    private function tableHasColumn(PDO $pdo, string $table, string $column): bool
    {
        try {
            $pdo->query("SELECT {$column} FROM {$table} LIMIT 0");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
