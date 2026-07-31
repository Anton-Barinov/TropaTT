<?php
declare(strict_types=1);

/**
 * Rollback Migration: Drop all Confluence Knowledge Migration module tables.
 *
 * Run manually:
 *   php -r "require 'api/index.php'; \$pdo = new PDO(...); require 'modules/crm.confluence-migration/api/migrations/002_rollback.php'; \$m = new RollbackConfluenceMigration(\$pdo); \$m->down();"
 *
 * Or via ModuleMigrationRunner:
 *   php api/scripts/module.php migrate crm.confluence-migration down
 */

use Api\System\Library\Database\Migration\AbstractMigration;

final class RollbackConfluenceMigration extends AbstractMigration
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function up(): void
    {
        // No-op: this migration is only for rollback
    }

    public function down(): void
    {
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

        // Drop module-specific tables (order matters due to FK)
        $tables = [
            'module_confluence_import_items',
            'module_confluence_import_logs',
            'module_confluence_import_jobs',
            'module_confluence_unresolved_links',
            'module_confluence_unsupported_macros',
            'module_confluence_user_mappings',
            'module_confluence_group_mappings',
            'module_confluence_settings',
            'module_confluence_rate_limits',
            'module_confluence_connections',
        ];

        foreach ($tables as $table) {
            try {
                $this->pdo->exec("DROP TABLE IF EXISTS {$table}");
            } catch (\Throwable $e) {
                fwrite(STDERR, "Warning: Could not drop {$table}: " . $e->getMessage() . PHP_EOL);
            }
        }

        $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        fwrite(STDOUT, "Rollback complete: " . count($tables) . " tables dropped." . PHP_EOL);
    }
}
