<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

/**
 * Widen counterparty requisite columns (tax_inn, tax_kpp, tax_ogrn, tax_ogrnip) to VARCHAR(32) NULL.
 * Ensures compatibility with sole proprietor OGRNIP (15 digits), legal entity OGRN (13 digits),
 * and international registration identifiers without truncation errors.
 */
final class CounterpartyRequisitesExpansionMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20261105_000001_counterparty_requisites_expansion';
    }

    public function description(): string
    {
        return 'Widen counterparty tax_inn, tax_kpp, tax_ogrn, tax_ogrnip to VARCHAR(32)';
    }

    public function up(PDO $pdo, string $driver): void
    {
        switch ($driver) {
            case 'mysql':
                $pdo->exec("ALTER TABLE counterparties
                    MODIFY tax_inn VARCHAR(32) NULL,
                    MODIFY tax_kpp VARCHAR(32) NULL,
                    MODIFY tax_ogrn VARCHAR(32) NULL,
                    MODIFY tax_ogrnip VARCHAR(32) NULL");
                break;
            case 'pgsql':
                $pdo->exec("ALTER TABLE counterparties
                    ALTER COLUMN tax_inn TYPE VARCHAR(32),
                    ALTER COLUMN tax_kpp TYPE VARCHAR(32),
                    ALTER COLUMN tax_ogrn TYPE VARCHAR(32),
                    ALTER COLUMN tax_ogrnip TYPE VARCHAR(32)");
                break;
            case 'sqlsrv':
                $pdo->exec("ALTER TABLE counterparties ALTER COLUMN tax_inn VARCHAR(32) NULL;
                    ALTER TABLE counterparties ALTER COLUMN tax_kpp VARCHAR(32) NULL;
                    ALTER TABLE counterparties ALTER COLUMN tax_ogrn VARCHAR(32) NULL;
                    ALTER TABLE counterparties ALTER COLUMN tax_ogrnip VARCHAR(32) NULL;");
                break;
            case 'sqlite':
            default:
                // SQLite dynamically types text columns; no alter needed.
                break;
        }
    }
}
