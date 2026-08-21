<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

/**
 * Migration: Rate cards (price lists) for custom rates by counterparty/project.
 *
 * Creates the three tables that form the rate-card subsystem:
 *   1. rate_cards      — named price lists with currency and default/archive flags.
 *   2. rate_card_lines — per-user / per-role / per-activity rate overrides with
 *                        effective date ranges and independent cost/bill/payout columns.
 *   3. rate_card_assignments — attaches a rate card to a counterparty or project
 *                              with its own date range and priority.
 *
 * See TZ_kastomnye_stavki.md section 3.
 */
final class RateCardsMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260821_000002_rate_cards';
    }

    public function description(): string
    {
        return 'Rate cards: price lists with per-user/role/activity lines and counterparty/project assignments';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_cards (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id       VARCHAR(64)    NOT NULL,
                title           VARCHAR(255)   NOT NULL,
                description     TEXT           NULL,
                currency_code   VARCHAR(8)     NULL,
                is_default      TINYINT(1)     NOT NULL DEFAULT 0,
                is_archived     TINYINT(1)     NOT NULL DEFAULT 0,
                created_by_user_id INTEGER     NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                deleted_at      DATETIME       NULL,
                row_version     INTEGER        NOT NULL DEFAULT 1,

                PRIMARY KEY (id),
                UNIQUE KEY uq_rate_cards_public_id (public_id),
                KEY idx_rate_cards_default (is_default, is_archived)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_card_lines (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id       VARCHAR(64)    NOT NULL,
                rate_card_id    BIGINT UNSIGNED NOT NULL,
                user_id         BIGINT UNSIGNED NULL,
                role_code       VARCHAR(64)    NULL,
                activity_code   VARCHAR(64)    NULL,
                cost_rate       DECIMAL(12,2)  NULL,
                bill_rate       DECIMAL(12,2)  NULL,
                payout_rate     DECIMAL(12,2)  NULL,
                currency_code   VARCHAR(8)     NULL,
                effective_from  DATE           NOT NULL,
                effective_to    DATE           NULL,
                note            VARCHAR(500)   NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                deleted_at      DATETIME       NULL,
                row_version     INTEGER        NOT NULL DEFAULT 1,

                PRIMARY KEY (id),
                UNIQUE KEY uq_rate_card_lines_public_id (public_id),
                KEY idx_rate_card_lines_card (rate_card_id, deleted_at),
                KEY idx_rate_card_lines_lookup (rate_card_id, user_id, activity_code, effective_from)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_card_assignments (
                id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id       VARCHAR(64)    NOT NULL,
                rate_card_id    BIGINT UNSIGNED NOT NULL,
                scope_type      VARCHAR(32)    NOT NULL,
                scope_ref       VARCHAR(64)    NOT NULL,
                priority        INTEGER        NOT NULL DEFAULT 100,
                effective_from  DATE           NOT NULL,
                effective_to    DATE           NULL,
                created_by_user_id INTEGER     NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                deleted_at      DATETIME       NULL,

                PRIMARY KEY (id),
                UNIQUE KEY uq_rate_card_assign_public_id (public_id),
                KEY idx_rate_card_assign_scope (scope_type, scope_ref, deleted_at),
                KEY idx_rate_card_assign_card (rate_card_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        } else {
            // sqlite / pgsql / sqlsrv fallback
            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_cards (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id       VARCHAR(64)    NOT NULL UNIQUE,
                title           VARCHAR(255)   NOT NULL,
                description     TEXT           NULL,
                currency_code   VARCHAR(8)     NULL,
                is_default      INTEGER        NOT NULL DEFAULT 0,
                is_archived     INTEGER        NOT NULL DEFAULT 0,
                created_by_user_id INTEGER     NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                deleted_at      DATETIME       NULL,
                row_version     INTEGER        NOT NULL DEFAULT 1
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'rate_cards', 'idx_rate_cards_default', 'is_default, is_archived');

            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_card_lines (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id       VARCHAR(64)    NOT NULL UNIQUE,
                rate_card_id    INTEGER        NOT NULL,
                user_id         INTEGER        NULL,
                role_code       VARCHAR(64)    NULL,
                activity_code   VARCHAR(64)    NULL,
                cost_rate       DECIMAL(12,2)  NULL,
                bill_rate       DECIMAL(12,2)  NULL,
                payout_rate     DECIMAL(12,2)  NULL,
                currency_code   VARCHAR(8)     NULL,
                effective_from  DATE           NOT NULL,
                effective_to    DATE           NULL,
                note            VARCHAR(500)   NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                deleted_at      DATETIME       NULL,
                row_version     INTEGER        NOT NULL DEFAULT 1
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'rate_card_lines', 'idx_rate_card_lines_card', 'rate_card_id, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'rate_card_lines', 'idx_rate_card_lines_lookup', 'rate_card_id, user_id, activity_code, effective_from');

            $pdo->exec('CREATE TABLE IF NOT EXISTS rate_card_assignments (
                id              INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id       VARCHAR(64)    NOT NULL UNIQUE,
                rate_card_id    INTEGER        NOT NULL,
                scope_type      VARCHAR(32)    NOT NULL,
                scope_ref       VARCHAR(64)    NOT NULL,
                priority        INTEGER        NOT NULL DEFAULT 100,
                effective_from  DATE           NOT NULL,
                effective_to    DATE           NULL,
                created_by_user_id INTEGER     NULL,
                created_at      DATETIME       NOT NULL,
                updated_at      DATETIME       NOT NULL,
                deleted_at      DATETIME       NULL
            )');
            IndexHelper::createIndexIfNotExists($pdo, 'rate_card_assignments', 'idx_rate_card_assign_scope', 'scope_type, scope_ref, deleted_at');
            IndexHelper::createIndexIfNotExists($pdo, 'rate_card_assignments', 'idx_rate_card_assign_card', 'rate_card_id');
        }
    }
}
