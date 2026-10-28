<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Statuses gain an optional `progress_percent` column (0..100) allowing
 * administrators to explicitly configure the progress percentage represented
 * by each status in the task/project lifecycle.
 *
 * Terminal/closed statuses automatically represent 100%, but intermediate
 * stages (e.g. analysis, in-progress, review, testing) can now have explicit
 * percentages rather than relying purely on array indexing.
 */
final class StatusProgressPercentMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261028_000001_status_progress_percent';
    }

    public function description(): string
    {
        return 'Add progress_percent column to statuses table for deterministic workflow progress';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'statuses', 'progress_percent', 'INTEGER NULL', $driver);
    }
}
