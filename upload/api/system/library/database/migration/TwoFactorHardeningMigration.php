<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: 2FA Security Hardening (M-4, M-5)
 *
 * M-4: TOTP code one-time use — track last used step to prevent replay
 *      within the validity window.
 * M-5: login_token single-use — store consumed nonce to prevent replay
 *      of the intermediate 2FA token.
 *
 * Changes:
 *   1. two_factor_secrets.last_totp_step — BIGINT to track last used TOTP step
 *   2. two_factor_secrets.last_login_nonce_hash — VARCHAR(128) for consumed nonce hash
 */
final class TwoFactorHardeningMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260823_000002_two_factor_hardening';
    }

    public function description(): string
    {
        return '2FA hardening: TOTP step replay prevention and login token nonce tracking';
    }

    public function up(PDO $pdo, string $driver): void
    {
        $this->addColumnIfNotExists($pdo, $driver, 'two_factor_secrets', 'last_totp_step',
            $driver === 'sqlsrv' ? 'BIGINT DEFAULT 0' : 'BIGINT DEFAULT 0');

        $this->addColumnIfNotExists($pdo, $driver, 'two_factor_secrets', 'last_login_nonce_hash',
            $driver === 'sqlsrv' ? 'VARCHAR(128) NULL' : 'VARCHAR(128) NULL');
    }

    private function addColumnIfNotExists(PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        try {
            $existing = $pdo->query("SELECT * FROM {$table} LIMIT 0");
            if ($existing !== false) {
                $colCount = $existing->columnCount();
                for ($i = 0; $i < $colCount; $i++) {
                    $meta = $existing->getColumnMeta($i);
                    if ($meta !== false && isset($meta['name']) && $meta['name'] === $column) {
                        return;
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('[TwoFactorHardeningMigration] column check for ' . $table . '.' . $column . ': ' . $e->getMessage());
        }

        try {
            $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        } catch (\Throwable $e) {
            error_log('[TwoFactorHardeningMigration] ALTER TABLE ' . $table . ' ADD ' . $column . ': ' . $e->getMessage());
        }
    }
}