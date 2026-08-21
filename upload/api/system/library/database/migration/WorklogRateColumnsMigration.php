<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: Financial rate columns on work_logs, tasks, and users.
 *
 * Adds:
 *   1. users.payout_rate — contractor compensation rate.
 *   2. work_logs — rate snapshots, source tracking, denormalised client/project
 *      context, and rate-lock fields (see TZ 3.5).
 *   3. tasks — activity_code default + per-task rate overrides (see TZ 3.6).
 *
 * The work_logs ALTER is a single statement per driver to avoid multiple table
 * rebuilds. Indexes are created in a separate migration (WorklogRateIndexesMigration).
 *
 * Backfill: client_public_id / project_public_id are populated from current
 * task/project links (TZ 5.2). Financial snapshot columns are left NULL for
 * existing rows — they will be filled by an explicit recalculate operation.
 */
final class WorklogRateColumnsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000003_worklog_rate_columns';
    }

    public function description(): string
    {
        return 'Financial rate columns on work_logs, tasks, and users.payout_rate';
    }

    public function up(PDO $pdo, string $driver): void
    {
        // 1. users.payout_rate
        $this->addColumnIfNotExists($pdo, $driver, 'users', 'payout_rate', 'DECIMAL(12,2) NULL');

        // 2. tasks: activity_code + overrides
        $this->addColumnIfNotExists($pdo, $driver, 'tasks', 'activity_code', 'VARCHAR(64) NULL');
        $this->addColumnIfNotExists($pdo, $driver, 'tasks', 'override_cost_rate', 'DECIMAL(12,2) NULL');
        $this->addColumnIfNotExists($pdo, $driver, 'tasks', 'override_bill_rate', 'DECIMAL(12,2) NULL');
        $this->addColumnIfNotExists($pdo, $driver, 'tasks', 'override_payout_rate', 'DECIMAL(12,2) NULL');

        // 3. work_logs — single ALTER per driver for all new columns
        $this->addWorklogColumns($pdo, $driver);

        // 4. Backfill client_public_id / project_public_id from current links
        $this->backfillClientProject($pdo, $driver);
    }

    private function addWorklogColumns(PDO $pdo, string $driver): void
    {
        $columns = [
            'activity_code'          => 'VARCHAR(64) NULL',
            'cost_rate_snapshot'     => 'DECIMAL(12,2) NULL',
            'bill_rate_snapshot'     => 'DECIMAL(12,2) NULL',
            'payout_rate_snapshot'   => 'DECIMAL(12,2) NULL',
            'currency_code'          => 'VARCHAR(8) NULL',
            'cost_source_type'       => 'VARCHAR(32) NULL',
            'cost_source_ref'        => 'VARCHAR(64) NULL',
            'bill_source_type'       => 'VARCHAR(32) NULL',
            'bill_source_ref'        => 'VARCHAR(64) NULL',
            'payout_source_type'     => 'VARCHAR(32) NULL',
            'payout_source_ref'      => 'VARCHAR(64) NULL',
            'rate_resolved_at'       => 'DATETIME NULL',
            'rate_ambiguous'         => 'TINYINT(1) NOT NULL DEFAULT 0',
            'rate_locked_at'         => 'DATETIME NULL',
            'client_public_id'       => 'VARCHAR(64) NULL',
            'project_public_id'      => 'VARCHAR(64) NULL',
        ];

        // For MySQL, build a single ALTER TABLE ADD COLUMN for all missing columns
        $addList = [];
        foreach ($columns as $name => $definition) {
            if ($this->columnExists($pdo, $driver, 'work_logs', $name)) {
                continue;
            }
            $addList[] = sprintf('ADD COLUMN %s %s', $name, $definition);
        }

        if ($addList === []) {
            return;
        }

        if ($driver === 'mysql') {
            $pdo->exec('ALTER TABLE work_logs ' . implode(', ', $addList));
        } elseif ($driver === 'sqlsrv') {
            $pdo->exec('ALTER TABLE work_logs ' . implode(', ', $addList));
        } else {
            // sqlite / pgsql — add one by one (sqlite doesn't support multi-column ALTER)
            foreach ($addList as $stmt) {
                $pdo->exec('ALTER TABLE work_logs ' . $stmt);
            }
        }
    }

    /**
     * Backfill client_public_id and project_public_id from current task/project
     * links. Financial snapshots are NOT filled — the backfill is purely for
     * context (filters, grouping) on historical data.
     *
     * Processed in batches of 5000 to avoid long locks on large tables.
     */
    private function backfillClientProject(PDO $pdo, string $driver): void
    {
        $batchSize = 5000;
        $offset = 0;

        do {
            $rows = $pdo->query(
                "SELECT w.id, w.task_id, t.project_id, t.client_public_id AS task_client,
                        p.public_id AS project_public_id, p.client_public_id AS project_client
                 FROM work_logs w
                 LEFT JOIN tasks t ON t.id = w.task_id AND t.deleted_at IS NULL
                 LEFT JOIN projects p ON p.id = t.project_id AND p.deleted_at IS NULL
                 WHERE w.client_public_id IS NULL AND w.project_public_id IS NULL
                 ORDER BY w.id ASC
                 LIMIT {$batchSize} OFFSET {$offset}"
            );

            if (!$rows) {
                break;
            }

            $all = $rows->fetchAll(PDO::FETCH_ASSOC);
            if ($all === []) {
                break;
            }

            $updates = [];
            foreach ($all as $row) {
                $taskId = (int)($row['task_id'] ?? 0);
                $clientPublicId = $taskId > 0
                    ? ($row['task_client'] ?: $row['project_client'] ?: null)
                    : null;
                $projectPublicId = $taskId > 0 ? ($row['project_public_id'] ?: null) : null;

                $updates[(int)$row['id']] = [
                    'client_public_id' => $clientPublicId ?: null,
                    'project_public_id' => $projectPublicId ?: null,
                ];
            }

            if ($updates === []) {
                break;
            }

            $stmt = $pdo->prepare(
                'UPDATE work_logs SET client_public_id = :client, project_public_id = :project WHERE id = :id'
            );

            foreach ($updates as $id => $vals) {
                $stmt->execute([
                    ':client' => $vals['client_public_id'],
                    ':project' => $vals['project_public_id'],
                    ':id' => $id,
                ]);
            }

            $offset += $batchSize;

            if (count($all) < $batchSize) {
                break;
            }
        } while (true);
    }

    private function addColumnIfNotExists(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        if ($this->columnExists($pdo, $driver, $table, $column)) {
            return;
        }

        $sql = match ($driver) {
            'mysql', 'pgsql', 'sqlite' => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
            'sqlsrv' => sprintf('ALTER TABLE %s ADD %s %s', $table, $column, $definition),
            default => sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition),
        };

        $pdo->exec($sql);
    }

    private function columnExists(PDO $pdo, string $driver, string $table, string $column): bool
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $pdo->prepare(
                    "SELECT COUNT(*) FROM information_schema.COLUMNS
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column"
                );
                $stmt->execute([':table' => $table, ':column' => $column]);
                return (int)$stmt->fetchColumn() > 0;
            }
            $result = $pdo->query("PRAGMA table_info({$table})");
            if ($result) {
                while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                    if (($row['name'] ?? '') === $column) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Safe: assume column doesn't exist
        }
        return false;
    }
}