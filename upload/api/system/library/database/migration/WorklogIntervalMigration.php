<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Adds exact time-tracker intervals to work_logs.
 *
 * The task timer stores its start/stop span only in the browser cookie; when
 * the timer is stopped only the rounded minutes and an exact-duration note
 * marker reached the server. Without the [started_at, ended_at] interval the
 * analytics cannot detect that several parallel timers double-counted the
 * same wall-clock time. These two columns carry the interval for NEW timer
 * entries; existing rows keep NULL (they simply do not participate in overlap
 * calculation — legacy data is deliberately left untouched).
 */
final class WorklogIntervalMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260811_000002_worklog_interval';
    }

    public function description(): string
    {
        return 'Add exact started_at/ended_at intervals to work_logs for overlap-aware time analytics';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver !== 'mysql') {
            return;
        }

        $columns = $this->existingColumns($pdo, 'work_logs');
        $alter = [];

        if (!in_array('started_at', $columns, true)) {
            $alter[] = 'ADD COLUMN started_at DATETIME NULL AFTER logged_at';
        }
        if (!in_array('ended_at', $columns, true)) {
            $alter[] = 'ADD COLUMN ended_at DATETIME NULL AFTER started_at';
        }

        if ($alter !== []) {
            $pdo->exec('ALTER TABLE work_logs ' . implode(', ', $alter));
        }

        IndexHelper::createIndexIfNotExists($pdo, 'work_logs', 'idx_work_logs_interval', 'user_id, started_at, ended_at');
    }

    /** @return array<int,string> */
    private function existingColumns(PDO $pdo, string $table): array
    {
        try {
            $stmt = $pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            );
            $stmt->execute([$table]);
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);

            return is_array($result) ? array_map('strval', $result) : [];
        } catch (\Throwable $e) {
            error_log('[WorklogIntervalMigration::existingColumns] ' . $e->getMessage());

            return [];
        }
    }
}
