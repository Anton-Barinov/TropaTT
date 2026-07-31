<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use RuntimeException;

final class ModuleMigrationRunner
{
    private PDO $pdo;
    private string $tableName = 'module_migrations';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Run all pending migrations for a module.
     * @param string $moduleName Module name (vendor.name)
     * @param string $migrationDir Absolute path to migrations directory
     * @param string|null $targetVersion Optional target version for versioned migrations
     * @return array{applied: array<string>, errors: array<string>}
     */
    public function migrate(string $moduleName, string $migrationDir, ?string $targetVersion = null): array
    {
        $result = ['applied' => [], 'errors' => []];

        if (!is_dir($migrationDir)) {
            return $result;
        }

        $applied = $this->getAppliedMigrations($moduleName);

        $files = $this->scanMigrationFiles($migrationDir, $applied);

        foreach ($files as $file) {
            if (!$this->isUpFile($file)) {
                continue;
            }

            $migrationName = basename($file);

            try {
                $sql = file_get_contents($file);
                if ($sql === false || trim((string)$sql) === '') {
                    continue;
                }

                $this->pdo->beginTransaction();
                $this->pdo->exec($sql);

                if (!$this->pdo->inTransaction()) {
                    $this->pdo->beginTransaction();
                }

                $this->recordMigration($moduleName, $migrationName);
                $this->pdo->commit();

                $result['applied'][] = $migrationName;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $result['errors'][] = "{$migrationName}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Rollback last N migrations for a module.
     * @return array{rolled_back: array<string>, errors: array<string>}
     */
    public function rollback(string $moduleName, string $migrationDir, int $steps = 1): array
    {
        $result = ['rolled_back' => [], 'errors' => []];

        $applied = $this->getAppliedMigrations($moduleName);
        $toRollback = array_slice($applied, -$steps);

        foreach (array_reverse($toRollback) as $migration) {
            $rollbackFile = $migrationDir . '/' . str_replace('.sql', '_rollback.sql', $migration);
            if (!is_file($rollbackFile)) {
                $rollbackFile = $migrationDir . '/' . str_replace('up.sql', 'down.sql', $migration);
            }

            if (!is_file($rollbackFile)) {
                $result['errors'][] = "No rollback file for: {$migration}";
                continue;
            }

            try {
                $sql = file_get_contents($rollbackFile);
                if ($sql === false || trim((string)$sql) === '') {
                    continue;
                }

                    $this->pdo->beginTransaction();
                    $this->pdo->exec($sql);

                    if (!$this->pdo->inTransaction()) {
                        $this->pdo->beginTransaction();
                    }

                    $this->removeMigrationRecord($moduleName, $migration);
                    $this->pdo->commit();

                $result['rolled_back'][] = $migration;
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                $result['errors'][] = "{$migration}: " . $e->getMessage();
            }
        }

        return $result;
    }

    /**
     * Rollback ALL migrations for a module.
     * @return array{rolled_back: array<string>, errors: array<string>}
     */
    public function rollbackAll(string $moduleName, string $migrationDir): array
    {
        $applied = $this->getAppliedMigrations($moduleName);
        return $this->rollback($moduleName, $migrationDir, count($applied));
    }

    /**
     * Get migration status for a module.
     * @return array{migrations: array<int, array{name: string, applied: bool, applied_at: string|null}>}
     */
    public function getStatus(string $moduleName, string $migrationDir): array
    {
        $applied = $this->getAppliedMigrations($moduleName);
        $files = $this->scanMigrationFiles($migrationDir, []);
        $upFiles = array_filter($files, fn($f) => $this->isUpFile($f));

        $migrations = [];
        foreach ($upFiles as $file) {
            $name = basename($file);
            $migrations[] = [
                'name' => $name,
                'applied' => in_array($name, $applied, true),
                'applied_at' => $this->findMigrationAppliedAt($moduleName, $name),
            ];
        }

        return ['migrations' => $migrations];
    }

    /**
     * Run version-based migrations from a versioned directory structure.
     * Structure: migrations/{version}/*.sql
     *
     * @return array{applied: array<string>, errors: array<string>}
     */
    public function migrateVersion(string $moduleName, string $migrationDir, string $targetVersion): array
    {
        $result = ['applied' => [], 'errors' => []];

        if (!is_dir($migrationDir)) {
            return $result;
        }

        $versions = [];
        $items = scandir($migrationDir);
        if ($items === false) {
            return $result;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || $item[0] === '.') {
                continue;
            }
            $versionPath = $migrationDir . '/' . $item;
            if (is_dir($versionPath)) {
                $versions[] = $item;
            }
        }

        usort($versions, 'version_compare');
        $applied = $this->getAppliedMigrations($moduleName);

        foreach ($versions as $version) {
            if (version_compare($version, $targetVersion, '>')) {
                break;
            }

            $versionDir = $migrationDir . '/' . $version;
            $files = glob($versionDir . '/*.sql');
            if ($files === false) {
                continue;
            }

            foreach ($files as $file) {
                $migrationName = $version . '/' . basename($file);
                if (str_ends_with($file, '_rollback.sql') || str_ends_with($file, 'down.sql')) {
                    continue;
                }
                if (in_array($migrationName, $applied, true)) {
                    continue;
                }

                try {
                    $sql = file_get_contents($file);
                    if ($sql === false || trim((string)$sql) === '') {
                        continue;
                    }

                    $this->pdo->beginTransaction();
                    $this->pdo->exec($sql);

                    if (!$this->pdo->inTransaction()) {
                        $this->pdo->beginTransaction();
                    }

                    $this->recordMigration($moduleName, $migrationName);
                    $this->pdo->commit();

                    $result['applied'][] = $migrationName;
                } catch (\Throwable $e) {
                    if ($this->pdo->inTransaction()) {
                        $this->pdo->rollBack();
                    }
                    $result['errors'][] = "{$migrationName}: " . $e->getMessage();
                }
            }
        }

        return $result;
    }

    /**
     * Ensure module_migrations table exists.
     */
    public function ensureTable(string $driver): void
    {
        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, migration_name {$keyType} NOT NULL, applied_at {$dt} NOT NULL {$nowDefault}, batch INTEGER NOT NULL DEFAULT 1)";
        $this->pdo->exec($sql);

        try {
            $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_module_migrations_unique ON {$this->tableName}(module_name, migration_name)");
        } catch (\Throwable $e) {
            error_log('[ModuleMigrationRunner::ensureTable] UNIQUE INDEX failed: ' . $e->getMessage());
        }

        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_module_migrations_module ON {$this->tableName}(module_name)");
        } catch (\Throwable $e) {
            error_log('[ModuleMigrationRunner::ensureTable] INDEX failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<int, string>
     */
    private function getAppliedMigrations(string $moduleName): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT migration_name FROM {$this->tableName} WHERE module_name = :module ORDER BY id ASC");
            $stmt->execute(['module' => $moduleName]);
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (\Throwable $e) {
            error_log('[ModuleMigrationRunner::getAppliedMigrations] ' . $e->getMessage());
            return [];
        }
    }

    private function recordMigration(string $moduleName, string $migrationName): void
    {
        try {
            $maxBatch = $this->getMaxBatch($moduleName);
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, migration_name, applied_at, batch) VALUES (:module, :migration, :now, :batch)");
            $stmt->execute([
                'module' => $moduleName,
                'migration' => $migrationName,
                'now' => $now,
                'batch' => $maxBatch + 1,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to record migration: " . $e->getMessage(), 0, $e);
        }
    }

    private function removeMigrationRecord(string $moduleName, string $migrationName): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE module_name = :module AND migration_name = :migration");
            $stmt->execute([
                'module' => $moduleName,
                'migration' => $migrationName,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException("Failed to remove migration record: " . $e->getMessage(), 0, $e);
        }
    }

    private function getMaxBatch(string $moduleName): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COALESCE(MAX(batch), 0) FROM {$this->tableName} WHERE module_name = :module");
            $stmt->execute(['module' => $moduleName]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('[ModuleMigrationRunner::getMaxBatch] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * @param array<int, string> $applied
     * @return array<int, string>
     */
    private function scanMigrationFiles(string $dir, array $applied): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = glob($dir . '/*.sql');
        if ($files === false) {
            return [];
        }

        $pending = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (!in_array($name, $applied, true)) {
                $pending[] = $file;
            }
        }

        usort($pending, function ($a, $b) {
            return strnatcmp(basename($a), basename($b));
        });
        return $pending;
    }

    private function isUpFile(string $file): bool
    {
        $name = basename($file);
        return !str_ends_with($name, '_rollback.sql') && !str_ends_with($name, 'down.sql');
    }

    private function findMigrationAppliedAt(string $moduleName, string $migrationName): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT applied_at FROM {$this->tableName} WHERE module_name = :module AND migration_name = :migration");
            $stmt->execute(['module' => $moduleName, 'migration' => $migrationName]);
            $result = $stmt->fetchColumn();
            return $result ? (string)$result : null;
        } catch (\Throwable $e) {
            error_log('[ModuleMigrationRunner::findMigrationAppliedAt] ' . $e->getMessage());
            return null;
        }
    }
}
