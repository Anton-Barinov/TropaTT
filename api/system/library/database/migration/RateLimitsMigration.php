<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class RateLimitsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260623_000001_rate_limits';
    }

    public function description(): string
    {
        return 'Create rate_limits table for DB-based rate limiting';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts TEXT NOT NULL,
                blocked_until INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts TEXT NOT NULL,
                blocked_until INTEGER NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
            )');
        }
    }
}
