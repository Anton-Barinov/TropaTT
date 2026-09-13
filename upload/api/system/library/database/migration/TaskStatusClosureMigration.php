<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use Api\System\Library\Support\TaskStatusSemantics;
use PDO;

/**
 * Statuses gained an explicit "terminal" mark so every active/open-task counter
 * uses one definition instead of a hard-coded per-query list.
 *
 * Before this flag the dashboard excluded only `done`/`archived` while other
 * modules excluded `done`/`closed`/`archived`, so aliases such as `completed`
 * (a `done` alias produced by the API/import) leaked into "active tasks".
 *
 * Backfill marks the canonical terminal task codes. Codes that are absent from
 * the dictionary keep resolving through TaskStatusSemantics' canonical fallback,
 * so installs with legacy codes (`completed`, `closed`, `new`) stay correct.
 *
 * Idempotent: addColumnIfNotExists + a scoped UPDATE.
 */
final class TaskStatusClosureMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260913_000002_task_status_closure';
    }

    public function description(): string
    {
        return 'Add is_closed flag to statuses and backfill terminal task statuses';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'statuses', 'is_closed', 'INTEGER NOT NULL DEFAULT 0', $driver);

        $codes = TaskStatusSemantics::FALLBACK_TERMINAL_CODES;
        $placeholders = implode(', ', array_fill(0, count($codes), '?'));

        $stmt = $pdo->prepare(
            'UPDATE statuses SET is_closed = 1, updated_at = ? WHERE scope = ? AND code IN (' . $placeholders . ')'
        );
        $stmt->execute(array_merge([gmdate('Y-m-d H:i:s'), TaskStatusSemantics::SCOPE], $codes));
    }
}
