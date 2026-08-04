<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use Api\System\Library\Database\IndexHelper;

final class ModuleAuditLogger
{
    private PDO $pdo;
    private string $tableName = 'module_audit_log';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function log(string $moduleName, string $eventType, string $eventName, array $details = [], ?string $ipAddress = null, ?int $userId = null): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $detailsJson = json_encode($details, JSON_UNESCAPED_UNICODE);
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, event_type, event_name, details, ip_address, user_id, created_at) VALUES (:module, :type, :event, :details, :ip, :user_id, :now)");
            $stmt->execute([
                'module' => $moduleName,
                'type' => $eventType,
                'event' => $eventName,
                'details' => $detailsJson,
                'ip' => $ipAddress,
                'user_id' => $userId,
                'now' => $now,
            ]);
        } catch (\Throwable $e) {
            error_log('[ModuleAuditLogger::log] ' . $e->getMessage());
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function getAuditLog(string $moduleName, int $limit = 100): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE module_name = :module ORDER BY id DESC LIMIT :limit");
            $stmt->execute(['module' => $moduleName, 'limit' => $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ModuleAuditLogger::getAuditLog] ' . $e->getMessage());
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

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, event_type {$keyType} NOT NULL, event_name {$keyType} NOT NULL, details {$keyType}, ip_address {$keyType}, user_id INTEGER, created_at {$dt} NOT NULL {$nowDefault})";
        $this->pdo->exec($sql);

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_audit_module', 'module_name');
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_audit_created', 'created_at');
        } catch (\Throwable $e) {
            error_log('[ModuleAuditLogger::ensureTable] ' . $e->getMessage());
        }
    }
}
