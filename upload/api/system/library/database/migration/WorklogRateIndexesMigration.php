<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: Indexes for financial rate columns on work_logs and tasks.
 *
 * Separate from WorklogRateColumnsMigration to avoid holding table locks
 * during index builds on potentially large work_logs tables.
 *
 * Indexes (TZ 3.5–3.6):
 *   idx_work_logs_client   — client_public_id, logged_at  (filtering by client)
 *   idx_work_logs_project  — project_public_id, logged_at (filtering by project)
 *   idx_work_logs_activity — activity_code                (activity filter)
 *   idx_work_logs_payout   — user_id, payout_rate_snapshot (me/earnings/available)
 *   idx_tasks_activity_code — activity_code               (task activity defaults)
 */
final class WorklogRateIndexesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000004_worklog_rate_indexes';
    }

    public function description(): string
    {
        return 'Indexes for financial rate columns on work_logs and tasks';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $indexes = [
            ['table' => 'work_logs', 'name' => 'idx_work_logs_client', 'columns' => 'client_public_id, logged_at'],
            ['table' => 'work_logs', 'name' => 'idx_work_logs_project', 'columns' => 'project_public_id, logged_at'],
            ['table' => 'work_logs', 'name' => 'idx_work_logs_activity', 'columns' => 'activity_code'],
            ['table' => 'work_logs', 'name' => 'idx_work_logs_payout', 'columns' => 'user_id, payout_rate_snapshot'],
            ['table' => 'tasks', 'name' => 'idx_tasks_activity_code', 'columns' => 'activity_code'],
        ];

        foreach ($indexes as $idx) {
            try {
                IndexHelper::createIndexIfNotExists($pdo, $idx['table'], $idx['name'], $idx['columns']);
            } catch (\Throwable $e) {
                error_log(
                    sprintf(
                        '[WorklogRateIndexesMigration::up] CREATE INDEX %s on %s: %s',
                        $idx['name'],
                        $idx['table'],
                        $e->getMessage()
                    )
                );
            }
        }
    }
}
