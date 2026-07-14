<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleJobDispatcher
{
    private PDO $pdo;
    private string $tableName = 'module_jobs';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Dispatch a background job.
     * @return int Job ID
     */
    public function dispatch(string $moduleName, string $jobName, array $payload, int $delay = 0): int
    {
        $now = date('Y-m-d H:i:s');
        $delayUntil = $delay > 0 ? date('Y-m-d H:i:s', time() + $delay) : null;

        $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, job_name, payload, status, delay_until, created_at) VALUES (:module, :job, :payload, 'pending', :delay, :now)");
        $stmt->execute([
            'module' => $moduleName,
            'job' => $jobName,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'delay' => $delayUntil,
            'now' => $now,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Process the next pending job.
     * @return array{id: int, status: string}|null
     */
    public function processNext(): ?array
    {
        $this->pdo->beginTransaction();

        try {
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE status = 'pending' AND (delay_until IS NULL OR delay_until <= :now) ORDER BY id ASC LIMIT 1 FOR UPDATE");
            $stmt->execute(['now' => $now]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($job === false) {
                $this->pdo->rollBack();
                return null;
            }

            $jobId = (int)$job['id'];
            $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'running', attempts = attempts + 1 WHERE id = :id");
            $stmt->execute(['id' => $jobId]);
            $this->pdo->commit();

            $startTime = microtime(true);
            $payload = json_decode($job['payload'], true) ?: [];

            try {
                $handlerClass = $job['job_name'];
                if (str_contains($handlerClass, '::')) {
                    [$class, $method] = explode('::', $handlerClass, 2);
                    if (class_exists($class) && method_exists($class, $method)) {
                        $instance = new $class();
                        $instance->{$method}($payload);
                    }
                } elseif (class_exists($handlerClass)) {
                    $instance = new $handlerClass();
                    if (method_exists($instance, 'handle')) {
                        $instance->handle($payload);
                    }
                }

                $duration = (microtime(true) - $startTime) * 1000;
                $completedAt = date('Y-m-d H:i:s');
                $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'completed', completed_at = :completed WHERE id = :id");
                $stmt->execute(['completed' => $completedAt, 'id' => $jobId]);

                return ['id' => $jobId, 'status' => 'completed'];
            } catch (\Throwable $e) {
                $maxAttempts = (int)($job['max_attempts'] ?? 3);
                $attempts = (int)($job['attempts']) + 1;

                if ($attempts >= $maxAttempts) {
                    $now = gmdate('Y-m-d H:i:s');
                    $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'failed', completed_at = :now WHERE id = :id");
                    $stmt->execute(['id' => $jobId, 'now' => $now]);
                    return ['id' => $jobId, 'status' => 'failed'];
                }

                $delayUntil = gmdate('Y-m-d H:i:s', time() + 60);
                $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'pending', delay_until = :delay WHERE id = :id");
                $stmt->execute(['id' => $jobId, 'delay' => $delayUntil]);
                $stmt->execute(['id' => $jobId]);

                return ['id' => $jobId, 'status' => 'retrying'];
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                try { $this->pdo->rollBack(); } catch (\Throwable $e) {
                    error_log('[ModuleJobDispatcher] rollBack failed: ' . $e->getMessage());
                }
            }
            return null;
        }
    }

    /** @return array{id: int, status: string}|null */
    public function getJobStatus(int $jobId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, status FROM {$this->tableName} WHERE id = :id");
        $stmt->execute(['id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ['id' => (int)$row['id'], 'status' => $row['status']] : null;
    }

    public function cleanupCompleted(int $days = 7): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE status IN ('completed', 'failed') AND completed_at IS NOT NULL AND completed_at < :cutoff");
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
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

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, job_name {$keyType} NOT NULL, payload {$keyType} NOT NULL, status {$keyType} NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 3, delay_until {$dt}, created_at {$dt} NOT NULL {$nowDefault}, completed_at {$dt})");

        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_module_jobs_status ON {$this->tableName}(status, created_at)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_module_jobs_module ON {$this->tableName}(module_name)");
        } catch (\Throwable) {
        }
    }
}
