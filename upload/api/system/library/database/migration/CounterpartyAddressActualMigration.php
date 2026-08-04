<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Add "actual (physical) address" (Фактический адрес) column to counterparties.
 */
final class CounterpartyAddressActualMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260804_000001_counterparty_address_actual';
    }

    public function description(): string
    {
        return 'Add actual (physical) address field to counterparties';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $definition = $driver === 'sqlsrv' ? 'NVARCHAR(MAX) NULL' : 'TEXT NULL';
        IndexHelper::addColumnIfNotExists($pdo, 'counterparties', 'address_actual', $definition, $driver);
    }
}
