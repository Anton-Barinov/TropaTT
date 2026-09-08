<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * API keys gained an optional human-readable name so administrators can tell
 * several keys of one client apart. Installs created before this change only
 * get it through CREATE TABLE IF NOT EXISTS (no effect on an existing table),
 * so existing databases must be upgraded explicitly.
 */
final class ApiKeysNameMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260907_000001_api_keys_name';
    }

    public function description(): string
    {
        return 'Add name column to api_keys for existing installs';
    }

    public function up(PDO $pdo, string $driver): void
    {
        IndexHelper::addColumnIfNotExists($pdo, 'api_keys', 'name', 'VARCHAR(255) NULL', $driver);
    }
}
