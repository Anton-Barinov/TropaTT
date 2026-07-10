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
        return 'Create rate_limits table for DB-based rate limiting with expires_at GC column';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts_count INT NOT NULL DEFAULT 0,
                window_start INT NOT NULL DEFAULT 0,
                blocked_until INT NOT NULL DEFAULT 0,
                expires_at INT NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`),
                INDEX idx_rate_limits_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_limits (
                `key` VARCHAR(64) NOT NULL,
                attempts_count INTEGER NOT NULL DEFAULT 0,
                window_start INTEGER NOT NULL DEFAULT 0,
                blocked_until INTEGER NOT NULL DEFAULT 0,
                expires_at INTEGER NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`key`)
            )');
            try {
                $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_expires ON rate_limits (expires_at)');
            } catch (\Throwable) {
                // Index may already exist
            }
        }
    }
}
