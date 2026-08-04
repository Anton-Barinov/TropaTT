<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use Api\System\Library\Database\IndexHelper;
use PDO;

final class CoreUpdateSystemMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260618_000001_core_update_system';
    }

    public function description(): string
    {
        return 'Create core update history/log tables and system.update permission';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS core_update_history (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                job_id VARCHAR(100) NOT NULL,
                from_version VARCHAR(50) NULL,
                from_build VARCHAR(50) NULL,
                from_sha CHAR(40) NULL,
                to_version VARCHAR(50) NOT NULL,
                to_build VARCHAR(50) NOT NULL,
                to_sha CHAR(40) NOT NULL,
                channel VARCHAR(50) NOT NULL,
                status ENUM(\'started\',\'success\',\'failed\',\'rolled_back\') NOT NULL,
                risk_level VARCHAR(20) NULL,
                package_type VARCHAR(20) NULL,
                backup_id VARCHAR(100) NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                error_message TEXT NULL,
                created_by_user_id BIGINT NULL,
                UNIQUE KEY uq_core_update_job (job_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec('CREATE TABLE IF NOT EXISTS core_update_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                job_id VARCHAR(100) NOT NULL,
                level ENUM(\'debug\',\'info\',\'warning\',\'error\') NOT NULL,
                step VARCHAR(100) NULL,
                message TEXT NOT NULL,
                context JSON NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_core_update_log_job (job_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

            $pdo->exec("INSERT IGNORE INTO permissions (public_id, code, title, created_at)
                VALUES ('perm_system_update', 'system.update', 'System: manage core updates', UTC_TIMESTAMP())");
            return;
        }

        $pdo->exec('CREATE TABLE IF NOT EXISTS core_update_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id VARCHAR(100) NOT NULL UNIQUE,
            from_version VARCHAR(50) NULL,
            from_build VARCHAR(50) NULL,
            from_sha CHAR(40) NULL,
            to_version VARCHAR(50) NOT NULL,
            to_build VARCHAR(50) NOT NULL,
            to_sha CHAR(40) NOT NULL,
            channel VARCHAR(50) NOT NULL,
            status VARCHAR(20) NOT NULL,
            risk_level VARCHAR(20) NULL,
            package_type VARCHAR(20) NULL,
            backup_id VARCHAR(100) NULL,
            started_at DATETIME NOT NULL,
            finished_at DATETIME NULL,
            error_message TEXT NULL,
            created_by_user_id INTEGER NULL
        )');

        $pdo->exec('CREATE TABLE IF NOT EXISTS core_update_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id VARCHAR(100) NOT NULL,
            level VARCHAR(20) NOT NULL,
            step VARCHAR(100) NULL,
            message TEXT NOT NULL,
            context TEXT NULL,
            created_at DATETIME NOT NULL
        )');
        IndexHelper::createIndexIfNotExists($pdo, 'core_update_log', 'idx_core_update_log_job', 'job_id');
        $pdo->exec("INSERT OR IGNORE INTO permissions (public_id, code, title, created_at)
            VALUES ('perm_system_update', 'system.update', 'System: manage core updates', datetime('now'))");
    }
}
