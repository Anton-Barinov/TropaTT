<?php
declare(strict_types=1);

namespace Api\System\Library\Database\Migration;

use PDO;

final class TaskEstimatesMigration implements MigrationInterface
{
    public function key(): string
    {
        return '20260616_000011_task_estimates';
    }

    public function description(): string
    {
        return 'Create estimate sets, options and task estimates tables';
    }

    public function up(PDO $pdo, string $driver): void
    {
        if ($driver === 'mysql') {
            $pdo->exec("CREATE TABLE IF NOT EXISTS estimate_sets (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                scope_type VARCHAR(32) NOT NULL DEFAULT 'project',
                project_id BIGINT UNSIGNED NULL,

                name VARCHAR(255) NOT NULL,
                code VARCHAR(64) NOT NULL,

                estimate_type VARCHAR(64) NOT NULL DEFAULT 'custom',

                unit_label VARCHAR(32) NULL,
                currency_code VARCHAR(8) NULL,

                description TEXT NULL,

                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                is_locked TINYINT(1) NOT NULL DEFAULT 0,

                active_key VARCHAR(191) NULL,

                sort_order INT NOT NULL DEFAULT 65535,

                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,

                row_version INT NOT NULL DEFAULT 1,

                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_estimate_sets_public_id (public_id),
                UNIQUE KEY uq_estimate_sets_active_key (active_key),

                KEY idx_estimate_sets_scope (scope_type, project_id),
                KEY idx_estimate_sets_project_active (project_id, is_active),
                KEY idx_estimate_sets_type (estimate_type),
                KEY idx_estimate_sets_code (code),
                KEY idx_estimate_sets_archived_at (archived_at),
                KEY idx_estimate_sets_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS estimate_options (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                estimate_set_id BIGINT UNSIGNED NOT NULL,

                label VARCHAR(255) NOT NULL,
                code VARCHAR(64) NOT NULL,

                numeric_value DECIMAL(12, 2) NULL,
                color VARCHAR(32) NULL,

                description TEXT NULL,

                is_default TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,

                active_key VARCHAR(191) NULL,

                sort_order INT NOT NULL DEFAULT 65535,

                created_by_user_id BIGINT UNSIGNED NOT NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,

                row_version INT NOT NULL DEFAULT 1,

                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_estimate_options_public_id (public_id),
                UNIQUE KEY uq_estimate_options_active_key (active_key),

                KEY idx_estimate_options_set_active (estimate_set_id, is_active),
                KEY idx_estimate_options_set_sort (estimate_set_id, sort_order),
                KEY idx_estimate_options_numeric (numeric_value),
                KEY idx_estimate_options_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS task_estimates (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                public_id VARCHAR(64) NOT NULL,

                task_id BIGINT UNSIGNED NOT NULL,
                task_public_id VARCHAR(64) NOT NULL,

                estimate_set_id BIGINT UNSIGNED NOT NULL,
                estimate_option_id BIGINT UNSIGNED NULL,

                numeric_value DECIMAL(12, 2) NULL,
                text_value VARCHAR(255) NULL,

                currency_code VARCHAR(8) NULL,

                note VARCHAR(1000) NULL,

                assigned_by_user_id BIGINT UNSIGNED NOT NULL,
                assigned_at DATETIME NOT NULL,

                updated_by_user_id BIGINT UNSIGNED NULL,

                row_version INT NOT NULL DEFAULT 1,

                active_key VARCHAR(191) NULL,

                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,

                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,

                PRIMARY KEY (id),

                UNIQUE KEY uq_task_estimates_public_id (public_id),
                UNIQUE KEY uq_task_estimates_active_key (active_key),

                KEY idx_task_estimates_task_active (task_id, deleted_at),
                KEY idx_task_estimates_task_public (task_public_id, deleted_at),
                KEY idx_task_estimates_set_value (estimate_set_id, numeric_value),
                KEY idx_task_estimates_option (estimate_option_id),
                KEY idx_task_estimates_assigned_by (assigned_by_user_id, assigned_at),
                KEY idx_task_estimates_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Seed default global estimate sets if they don't exist
            $check = $pdo->query("SELECT COUNT(*) FROM estimate_sets WHERE scope_type = 'global'");
            if ((int)$check->fetchColumn() === 0) {
                $now = gmdate('Y-m-d H:i:s');

                // T-shirt Size
                $pdo->exec("INSERT INTO estimate_sets (public_id, scope_type, name, code, estimate_type, unit_label, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at)
                    VALUES ('est_default_tshirt', 'global', 'T-shirt Size', 'tshirt_size', 'tshirt', NULL, 1, 1, 'global:tshirt_size', 100, 1, '{$now}', '{$now}')");

                // Complexity
                $pdo->exec("INSERT INTO estimate_sets (public_id, scope_type, name, code, estimate_type, unit_label, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at)
                    VALUES ('est_default_complexity', 'global', 'Complexity', 'complexity', 'complexity', NULL, 1, 1, 'global:complexity', 200, 1, '{$now}', '{$now}')");

                // Risk
                $pdo->exec("INSERT INTO estimate_sets (public_id, scope_type, name, code, estimate_type, unit_label, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at)
                    VALUES ('est_default_risk', 'global', 'Risk', 'risk', 'risk', NULL, 1, 1, 'global:risk', 300, 1, '{$now}', '{$now}')");

                // Story Points
                $pdo->exec("INSERT INTO estimate_sets (public_id, scope_type, name, code, estimate_type, unit_label, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at)
                    VALUES ('est_default_story_points', 'global', 'Story Points', 'story_points', 'story_points', 'SP', 0, 1, 'global:story_points', 400, 1, '{$now}', '{$now}')");
            }

            // Seed default options if they don't exist
            $checkOpt = $pdo->query("SELECT COUNT(*) FROM estimate_options");
            if ((int)$checkOpt->fetchColumn() === 0) {
                $now = gmdate('Y-m-d H:i:s');

                // T-shirt options
                $tshirtSet = $pdo->query("SELECT id FROM estimate_sets WHERE code = 'tshirt_size' AND scope_type = 'global'")->fetch();
                if ($tshirtSet) {
                    $sid = (int)$tshirtSet['id'];
                    $pdo->exec("INSERT INTO estimate_options (public_id, estimate_set_id, label, code, numeric_value, color, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at) VALUES
                        ('eopt_default_xs', {$sid}, 'XS', 'xs', 1, 'gray', 0, 1, 'set:{$sid}:xs', 100, 1, '{$now}', '{$now}'),
                        ('eopt_default_s', {$sid}, 'S', 's', 2, 'green', 1, 1, 'set:{$sid}:s', 200, 1, '{$now}', '{$now}'),
                        ('eopt_default_m', {$sid}, 'M', 'm', 3, 'blue', 0, 1, 'set:{$sid}:m', 300, 1, '{$now}', '{$now}'),
                        ('eopt_default_l', {$sid}, 'L', 'l', 5, 'orange', 0, 1, 'set:{$sid}:l', 400, 1, '{$now}', '{$now}'),
                        ('eopt_default_xl', {$sid}, 'XL', 'xl', 8, 'red', 0, 1, 'set:{$sid}:xl', 500, 1, '{$now}', '{$now}')");
                }

                // Complexity options
                $compSet = $pdo->query("SELECT id FROM estimate_sets WHERE code = 'complexity' AND scope_type = 'global'")->fetch();
                if ($compSet) {
                    $sid = (int)$compSet['id'];
                    $pdo->exec("INSERT INTO estimate_options (public_id, estimate_set_id, label, code, numeric_value, color, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at) VALUES
                        ('eopt_default_comp_low', {$sid}, 'Low', 'low', 1, 'green', 1, 1, 'set:{$sid}:low', 100, 1, '{$now}', '{$now}'),
                        ('eopt_default_comp_med', {$sid}, 'Medium', 'medium', 2, 'blue', 0, 1, 'set:{$sid}:medium', 200, 1, '{$now}', '{$now}'),
                        ('eopt_default_comp_high', {$sid}, 'High', 'high', 3, 'orange', 0, 1, 'set:{$sid}:high', 300, 1, '{$now}', '{$now}'),
                        ('eopt_default_comp_vhigh', {$sid}, 'Very High', 'very_high', 5, 'red', 0, 1, 'set:{$sid}:very_high', 400, 1, '{$now}', '{$now}')");
                }

                // Risk options
                $riskSet = $pdo->query("SELECT id FROM estimate_sets WHERE code = 'risk' AND scope_type = 'global'")->fetch();
                if ($riskSet) {
                    $sid = (int)$riskSet['id'];
                    $pdo->exec("INSERT INTO estimate_options (public_id, estimate_set_id, label, code, numeric_value, color, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at) VALUES
                        ('eopt_default_risk_low', {$sid}, 'Low', 'low', 1, 'green', 1, 1, 'set:{$sid}:low', 100, 1, '{$now}', '{$now}'),
                        ('eopt_default_risk_med', {$sid}, 'Medium', 'medium', 2, 'blue', 0, 1, 'set:{$sid}:medium', 200, 1, '{$now}', '{$now}'),
                        ('eopt_default_risk_high', {$sid}, 'High', 'high', 3, 'orange', 0, 1, 'set:{$sid}:high', 300, 1, '{$now}', '{$now}'),
                        ('eopt_default_risk_crit', {$sid}, 'Critical', 'critical', 5, 'red', 0, 1, 'set:{$sid}:critical', 400, 1, '{$now}', '{$now}')");
                }

                // Story Points options
                $spSet = $pdo->query("SELECT id FROM estimate_sets WHERE code = 'story_points' AND scope_type = 'global'")->fetch();
                if ($spSet) {
                    $sid = (int)$spSet['id'];
                    $pdo->exec("INSERT INTO estimate_options (public_id, estimate_set_id, label, code, numeric_value, color, is_default, is_active, active_key, sort_order, created_by_user_id, created_at, updated_at) VALUES
                        ('eopt_default_sp1', {$sid}, '1', '1', 1, 'green', 0, 1, 'set:{$sid}:1', 100, 1, '{$now}', '{$now}'),
                        ('eopt_default_sp2', {$sid}, '2', '2', 2, 'blue', 1, 1, 'set:{$sid}:2', 200, 1, '{$now}', '{$now}'),
                        ('eopt_default_sp3', {$sid}, '3', '3', 3, 'blue', 0, 1, 'set:{$sid}:3', 300, 1, '{$now}', '{$now}'),
                        ('eopt_default_sp5', {$sid}, '5', '5', 5, 'orange', 0, 1, 'set:{$sid}:5', 400, 1, '{$now}', '{$now}'),
                        ('eopt_default_sp8', {$sid}, '8', '8', 8, 'orange', 0, 1, 'set:{$sid}:8', 500, 1, '{$now}', '{$now}'),
                        ('eopt_default_sp13', {$sid}, '13', '13', 13, 'red', 0, 1, 'set:{$sid}:13', 600, 1, '{$now}', '{$now}'),
                        ('eopt_default_sp21', {$sid}, '21', '21', 21, 'red', 0, 1, 'set:{$sid}:21', 700, 1, '{$now}', '{$now}')");
                }
            }
        } else {
            // SQLite fallback
            $pdo->exec("CREATE TABLE IF NOT EXISTS estimate_sets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                scope_type VARCHAR(32) NOT NULL DEFAULT 'project',
                project_id INTEGER NULL,
                name VARCHAR(255) NOT NULL,
                code VARCHAR(64) NOT NULL,
                estimate_type VARCHAR(64) NOT NULL DEFAULT 'custom',
                unit_label VARCHAR(32) NULL,
                currency_code VARCHAR(8) NULL,
                description TEXT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                is_locked INTEGER NOT NULL DEFAULT 0,
                active_key VARCHAR(191) NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                created_by_user_id INTEGER NOT NULL,
                updated_by_user_id INTEGER NULL,
                row_version INT NOT NULL DEFAULT 1,
                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_estimate_sets_public_id ON estimate_sets(public_id)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_estimate_sets_active_key ON estimate_sets(active_key)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_estimate_sets_scope ON estimate_sets(scope_type, project_id)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS estimate_options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                estimate_set_id INTEGER NOT NULL,
                label VARCHAR(255) NOT NULL,
                code VARCHAR(64) NOT NULL,
                numeric_value DECIMAL(12, 2) NULL,
                color VARCHAR(32) NULL,
                description TEXT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                is_active INTEGER NOT NULL DEFAULT 1,
                active_key VARCHAR(191) NULL,
                sort_order INT NOT NULL DEFAULT 65535,
                created_by_user_id INTEGER NOT NULL,
                updated_by_user_id INTEGER NULL,
                row_version INT NOT NULL DEFAULT 1,
                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_estimate_options_public_id ON estimate_options(public_id)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_estimate_options_active_key ON estimate_options(active_key)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_estimate_options_set_active ON estimate_options(estimate_set_id, is_active)");

            $pdo->exec("CREATE TABLE IF NOT EXISTS task_estimates (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                public_id VARCHAR(64) NOT NULL,
                task_id INTEGER NOT NULL,
                task_public_id VARCHAR(64) NOT NULL,
                estimate_set_id INTEGER NOT NULL,
                estimate_option_id INTEGER NULL,
                numeric_value DECIMAL(12, 2) NULL,
                text_value VARCHAR(255) NULL,
                currency_code VARCHAR(8) NULL,
                note VARCHAR(1000) NULL,
                assigned_by_user_id INTEGER NOT NULL,
                assigned_at DATETIME NOT NULL,
                updated_by_user_id INTEGER NULL,
                row_version INT NOT NULL DEFAULT 1,
                active_key VARCHAR(191) NULL,
                archived_at DATETIME NULL,
                deleted_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            )");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_task_estimates_public_id ON task_estimates(public_id)");
            $pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS uq_task_estimates_active_key ON task_estimates(active_key)");
            $pdo->exec("CREATE INDEX IF NOT EXISTS idx_task_estimates_task_active ON task_estimates(task_id, deleted_at)");
        }
    }
}
