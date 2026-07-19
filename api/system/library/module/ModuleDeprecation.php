<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleDeprecation
{
    private PDO $pdo;
    private string $tableName = 'module_deprecations';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public static function warn(string $moduleName, string $message, string $since, string $replacement = ''): void
    {
        error_log(sprintf(
            '[ModuleDeprecation] %s: %s (since %s)%s',
            $moduleName,
            $message,
            $since,
            $replacement !== '' ? " — use {$replacement} instead" : ''
        ));
    }

    public function logDeprecation(string $moduleName, string $message, string $since, string $replacement = ''): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, message, since_version, replacement, created_at) VALUES (:module, :message, :since, :replacement, :now)");
            $stmt->execute([
                'module' => $moduleName,
                'message' => $message,
                'since' => $since,
                'replacement' => $replacement,
                'now' => $now,
            ]);
        } catch (\Throwable $e) {
            error_log('[ModuleDeprecation::logDeprecation] ' . $e->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getDeprecations(string $moduleName): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE module_name = :module ORDER BY id DESC");
            $stmt->execute(['module' => $moduleName]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ModuleDeprecation::getDeprecations] SELECT: ' . $e->getMessage());
            return [];
        }
    }

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

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, message {$keyType} NOT NULL, since_version {$keyType}, replacement {$keyType}, created_at {$dt} NOT NULL {$nowDefault})";
        $this->pdo->exec($sql);

        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_module_deprecations_module ON {$this->tableName}(module_name)");
        } catch (\Throwable $e) {
            error_log('[ModuleDeprecation::ensureTable] CREATE INDEX: ' . $e->getMessage());
        }
    }
}
