<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class TenantModuleRegistry
{
    private PDO $pdo;
    private string $tableName = 'tenant_modules';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTenantModules(int $tenantId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE tenant_id = :tenant AND is_active = 1");
        $stmt->execute(['tenant' => $tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function enableForTenant(int $tenantId, string $moduleName): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO {$this->tableName} (tenant_id, module_name, is_active, enabled_at) VALUES (:tenant, :module, 1, :now)");
        $stmt->execute(['tenant' => $tenantId, 'module' => $moduleName, 'now' => $now]);
    }

    public function disableForTenant(int $tenantId, string $moduleName): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET is_active = 0 WHERE tenant_id = :tenant AND module_name = :module");
        $stmt->execute(['tenant' => $tenantId, 'module' => $moduleName]);
    }

    public function isEnabledForTenant(int $tenantId, string $moduleName): bool
    {
        $stmt = $this->pdo->prepare("SELECT is_active FROM {$this->tableName} WHERE tenant_id = :tenant AND module_name = :module");
        $stmt->execute(['tenant' => $tenantId, 'module' => $moduleName]);
        return (bool)($stmt->fetchColumn() ?? false);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setTenantConfig(int $tenantId, string $moduleName, array $config): void
    {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET config = :config WHERE tenant_id = :tenant AND module_name = :module");
        $stmt->execute(['config' => $json, 'tenant' => $tenantId, 'module' => $moduleName]);
    }

    /** @return array<string, mixed> */
    public function getTenantConfig(int $tenantId, string $moduleName): array
    {
        $stmt = $this->pdo->prepare("SELECT config FROM {$this->tableName} WHERE tenant_id = :tenant AND module_name = :module");
        $stmt->execute(['tenant' => $tenantId, 'module' => $moduleName]);
        $json = $stmt->fetchColumn();
        if (is_string($json) && $json !== '') {
            $config = json_decode($json, true);
            return is_array($config) ? $config : [];
        }
        return [];
    }

    public function ensureTable(string $driver): void
    {
        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tableName} (tenant_id INTEGER NOT NULL, module_name {$keyType} NOT NULL, is_active INTEGER NOT NULL DEFAULT 1, config {$keyType}, enabled_at {$dt} NOT NULL {$nowDefault}, PRIMARY KEY (tenant_id, module_name))");

        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_tenant_modules_tenant ON {$this->tableName}(tenant_id)");
        } catch (\Throwable $e) {
            error_log('[TenantModuleRegistry::ensureTable] ' . $e->getMessage());
        }
    }
}
