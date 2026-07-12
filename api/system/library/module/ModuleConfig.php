<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use RuntimeException;

final class ModuleConfig
{
    private PDO $pdo;
    private string $tableName;

    public function __construct(PDO $pdo, string $tableName = 'module_registry')
    {
        $this->pdo = $pdo;
        $this->tableName = $tableName;
    }

    /**
     * Get all settings for a module.
     * @return array<string, mixed>
     */
    public function getAll(string $moduleName): array
    {
        $config = $this->loadConfig($moduleName);
        return $config;
    }

    /**
     * Get a specific config key.
     */
    public function get(string $moduleName, string $key, mixed $default = null): mixed
    {
        $config = $this->loadConfig($moduleName);
        return $config[$key] ?? $default;
    }

    /**
     * Set a single config key.
     */
    public function set(string $moduleName, string $key, mixed $value): void
    {
        $config = $this->loadConfig($moduleName);
        $config[$key] = $value;
        $this->saveConfig($moduleName, $config);
    }

    /**
     * Set multiple config keys at once.
     * @param array<string, mixed> $values
     */
    public function setMultiple(string $moduleName, array $values): void
    {
        $config = $this->loadConfig($moduleName);
        foreach ($values as $key => $value) {
            $config[$key] = $value;
        }
        $this->saveConfig($moduleName, $config);
    }

    /**
     * Delete a config key.
     */
    public function delete(string $moduleName, string $key): void
    {
        $config = $this->loadConfig($moduleName);
        unset($config[$key]);
        $this->saveConfig($moduleName, $config);
    }

    /**
     * Reset config to defaults from manifest.
     */
    public function reset(string $moduleName, Manifest $manifest): void
    {
        $defaults = $manifest->configDefaults;
        $this->saveConfig($moduleName, $defaults);
    }

    /**
     * Initialize config from manifest defaults if not already set.
     */
    public function initFromManifest(string $moduleName, Manifest $manifest): void
    {
        $existing = $this->loadConfig($moduleName);
        if ($existing !== []) {
            return;
        }

        $defaults = $manifest->configDefaults;
        $this->saveConfig($moduleName, $defaults);
    }

    /**
     * Ensure module_registry table exists.
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
        $bool = $driver === 'sqlsrv' ? 'BIT' : 'INTEGER';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';
        $configType = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';

        $sql = "CREATE TABLE IF NOT EXISTS module_registry (
            module_name {$keyType} NOT NULL,
            vendor {$keyType} NOT NULL,
            version {$keyType} NOT NULL,
            is_active {$bool} NOT NULL DEFAULT 0,
            installed_at {$dt} NOT NULL {$nowDefault},
            activated_at {$dt},
            config {$configType} NOT NULL,
            PRIMARY KEY (module_name)
        )";
        $this->pdo->exec($sql);

        try {
            $this->pdo->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_module_registry_name ON module_registry(module_name)");
        } catch (\Throwable) {
        }
    }

    private function ensureConfigColumnCapacity(string $driver): void
    {
        try {
            if ($driver === 'mysql') {
                $stmt = $this->pdo->query("SELECT DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'module_registry' AND COLUMN_NAME = 'config'");
                $column = $stmt?->fetch(PDO::FETCH_ASSOC) ?: [];
                $type = strtolower((string)($column['DATA_TYPE'] ?? ''));
                $length = (int)($column['CHARACTER_MAXIMUM_LENGTH'] ?? 0);
                if (!in_array($type, ['text', 'mediumtext', 'longtext'], true) && $length < 65535) {
                    $this->pdo->exec('ALTER TABLE module_registry MODIFY config LONGTEXT NOT NULL');
                }
                return;
            }

            if ($driver === 'pgsql') {
                $stmt = $this->pdo->query("SELECT data_type FROM information_schema.columns
                    WHERE table_schema = current_schema() AND table_name = 'module_registry' AND column_name = 'config'");
                $type = strtolower((string)($stmt?->fetchColumn() ?: ''));
                if ($type !== '' && $type !== 'text') {
                    $this->pdo->exec('ALTER TABLE module_registry ALTER COLUMN config TYPE TEXT');
                }
                return;
            }

            if ($driver === 'sqlsrv') {
                $stmt = $this->pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_NAME = 'module_registry' AND COLUMN_NAME = 'config'");
                $length = (int)($stmt?->fetchColumn() ?: 0);
                if ($length !== -1 && $length < 65535) {
                    $this->pdo->exec('ALTER TABLE module_registry ALTER COLUMN config NVARCHAR(MAX) NOT NULL');
                }
            }
            // SQLite does not enforce VARCHAR lengths, so no conversion is needed.
        } catch (\Throwable) {
            // A read-only database must not prevent modules that already fit.
            // saveConfig will still surface a real write failure to the caller.
        }
    }

    /**
     * Register a module in the registry.
     */
    public function register(string $moduleName, string $vendor, string $version): void
    {
        try {
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, vendor, version, is_active, config) VALUES (:name, :vendor, :version, 0, '{}')");
            $stmt->execute([
                'name' => $moduleName,
                'vendor' => $vendor,
                'version' => $version,
            ]);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            if ($code !== '23000' && !str_contains($e->getMessage(), 'Duplicate') && !str_contains($e->getMessage(), 'UNIQUE')) {
                throw $e;
            }
        }
    }

    /**
     * Set module as active in the registry.
     */
    public function setActive(string $moduleName): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET is_active = 1, activated_at = :now WHERE module_name = :name");
        $stmt->execute(['name' => $moduleName, 'now' => $now]);
    }

    /**
     * Set module as inactive in the registry.
     */
    public function setInactive(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET is_active = 0 WHERE module_name = :name");
        $stmt->execute(['name' => $moduleName]);
    }

    /**
     * Update module version in registry.
     */
    public function setVersion(string $moduleName, string $version): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET version = :version WHERE module_name = :name");
        $stmt->execute(['version' => $version, 'name' => $moduleName]);
    }

    /**
     * Remove module from registry.
     */
    public function unregister(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE module_name = :name");
        $stmt->execute(['name' => $moduleName]);
    }

    /**
     * Get module registry info.
     * @return array<string, mixed>|null
     */
    public function getRegistry(string $moduleName): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE module_name = :name");
            $stmt->execute(['name' => $moduleName]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Get all active modules from registry.
     * @return array<int, array<string, mixed>>
     */
    public function getActiveModules(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE is_active = 1");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(string $moduleName): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT config FROM {$this->tableName} WHERE module_name = :name");
            $stmt->execute(['name' => $moduleName]);
            $json = $stmt->fetchColumn();
            if ($json && is_string($json)) {
                $config = json_decode($json, true);
                if (is_array($config)) {
                    return $config;
                }
            }
        } catch (\Throwable) {
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function saveConfig(string $moduleName, array $config): void
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Failed to encode config to JSON');
        }

        $params = ['config' => $json, 'name' => $moduleName];
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET config = :config WHERE module_name = :name");

        try {
            $stmt->execute($params);
        } catch (\PDOException $e) {
            if (!$this->isConfigColumnTooSmall($e)) {
                throw $e;
            }

            // Upgrade legacy VARCHAR(190) installations only if a real module
            // configuration needs more space. This keeps the normal request path
            // free of INFORMATION_SCHEMA reads on shared hosting.
            $this->ensureConfigColumnCapacity((string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
            $retry = $this->pdo->prepare("UPDATE {$this->tableName} SET config = :config WHERE module_name = :name");
            $retry->execute($params);
        }
    }

    private function isConfigColumnTooSmall(\PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return (string)$exception->getCode() === '22001'
            || str_contains($message, 'data too long')
            || str_contains($message, 'string data, right truncated');
    }
}
