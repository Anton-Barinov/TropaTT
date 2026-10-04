<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class TaskStartAtRangeIndexMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261004_000002_task_start_at_range_index';
    }

    public function description(): string
    {
        return 'Index task start dates for bounded calendar range queries';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::createIndexIfNotExists($pdo, 'tasks', 'idx_tasks_start_at', 'start_at', false, $driver);
    }
}
