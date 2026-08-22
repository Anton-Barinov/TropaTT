<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;
use Api\System\Library\Database\IndexHelper;
use RuntimeException;

final class ModuleErrorHandler
{
    private PDO $pdo;
    private string $tableName = 'module_errors';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Wrap a callable with module error isolation.
     * Static version: only logs to error_log, does NOT persist to DB.
     */
    public static function wrap(callable $fn, string $moduleName, string $context = ''): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            error_log(sprintf(
                '[Module:%s] Error in %s: %s',
                $moduleName,
                $context,
                $e->getMessage()
            ));
            return null;
        }
    }

    /**
     * Wrap a callable with module error isolation AND persist error to DB.
     */
    public function wrapAndLog(callable $fn, string $moduleName, string $context = ''): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            $this->logException($moduleName, $context, $e);
            return null;
        }
    }

    /**
     * Log an error to the module_errors table.
     */
    public function logError(string $moduleName, string $context, string $errorCode, string $errorMessage, ?string $stackTrace = null, ?string $requestId = null): void
    {
        try {
            $now = date('Y-m-d H:i:s');
            $trace = $stackTrace ? substr($stackTrace, 0, 65535) : null;

            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, context, error_code, error_message, stack_trace, request_id, created_at) VALUES (:module, :context, :code, :message, :trace, :request_id, :now)");
            $stmt->execute([
                'module' => $moduleName,
                'context' => $context,
                'code' => $errorCode,
                'message' => $errorMessage,
                'trace' => $trace,
                'request_id' => $requestId,
                'now' => $now,
            ]);
        } catch (\Throwable $e) {
            error_log("[ModuleErrorHandler::persistError] DB persist failed for {$moduleName}: " . $e->getMessage() . " | Original error: {$errorMessage}");
        }
    }

    /**
     * Log an error from a Throwable.
     */
    public function logException(string $moduleName, string $context, \Throwable $e, ?string $requestId = null): void
    {
        $this->logError(
            $moduleName,
            $context,
            'MOD_ERR_' . strtoupper(str_replace(' ', '_', $context)),
            $e->getMessage(),
            $e->getTraceAsString(),
            $requestId
        );
    }

    /**
     * Get errors for a module.
     * @return array<int, array<string, mixed>>
     */
    public function getErrors(string $moduleName, int $limit = 100): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE module_name = :module ORDER BY id DESC LIMIT :limit");
            $stmt->execute(['module' => $moduleName, 'limit' => $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            error_log('[ModuleErrorHandler::getErrors] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * List module errors with filters (for admin API).
     */
    public function list(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['module_name'])) {
            $where[] = 'module_name = ?';
            $params[] = $filters['module_name'];
        }

        if (!empty($filters['from'])) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'];
        }

        if (!empty($filters['to'])) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(error_message LIKE ? OR context LIKE ?)';
            $params[] = '%' . $filters['search'] . '%';
            $params[] = '%' . $filters['search'] . '%';
        }

        $limit = min((int)($filters['limit'] ?? 100), 500);
        $offset = max((int)($filters['offset'] ?? 0), 0);

        $whereSql = implode(' AND ', $where);

        try {
            $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->tableName} WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $this->pdo->prepare(
                "SELECT * FROM {$this->tableName} WHERE {$whereSql} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return ['items' => $items, 'total' => $total];
        } catch (\Throwable $e) {
            error_log('[ModuleErrorHandler::list] ' . $e->getMessage());
            return ['items' => [], 'total' => 0];
        }
    }

    /**
     * Delete old module errors (retention).
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        try {
            $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE created_at < ?");
            $stmt->execute([$cutoff]);
            return $stmt->rowCount();
        } catch (\Throwable $e) {
            error_log('[ModuleErrorHandler::cleanup] ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Clear errors for a module.
     */
    public function clearErrors(string $moduleName): void
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE module_name = :module");
            $stmt->execute(['module' => $moduleName]);
        } catch (\Throwable $e) {
            error_log('[ModuleErrorHandler::clearErrors] ' . $e->getMessage());
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

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, context {$keyType} NOT NULL, error_code {$keyType}, error_message {$keyType} NOT NULL, stack_trace {$keyType}, request_id {$keyType}, created_at {$dt} NOT NULL {$nowDefault})";
        $this->pdo->exec($sql);

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_errors_module', 'module_name');
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_errors_created', 'created_at');
        } catch (\Throwable $e) {
            error_log('[ModuleErrorHandler::ensureTable] ' . $e->getMessage());
        }
    }
}
