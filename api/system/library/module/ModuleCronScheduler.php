<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use PDO;

final class ModuleCronScheduler
{
    private PDO $pdo;
    private CronExpressionParser $parser;
    private string $tasksTable = 'module_scheduled_tasks';
    private string $executionsTable = 'module_task_executions';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->parser = new CronExpressionParser();
    }

    /**
     * Run all due tasks for all active modules.
     * @return array{executed: int, failed: int, results: array<int, array<string, mixed>>}
     */
    public function run(): array
    {
        $result = ['executed' => 0, 'failed' => 0, 'results' => []];
        $now = new \DateTime();

        $tasks = $this->getDueTasks($now);
        foreach ($tasks as $task) {
            $taskResult = $this->executeTask($task['id'], $task['module_name'], $task['task_name'], $task['handler_class'], $task['handler_method'], (int)$task['timeout']);
            $result['results'][] = $taskResult;

            if ($taskResult['status'] === 'success') {
                $result['executed']++;
            } else {
                $result['failed']++;
            }

            $this->updateNextRun((int)$task['id'], $task['schedule']);
        }

        return $result;
    }

    /**
     * Register a scheduled task from a module's ServiceProvider.
     */
    public function registerTask(string $moduleName, ScheduledTask $task): void
    {
        try {
            $nextRun = $this->parser->getNextRunDate($task->schedule);

            $stmt = $this->pdo->prepare("INSERT INTO {$this->tasksTable} (module_name, task_name, description, schedule, handler_class, handler_method, enabled, timeout, overlap_allowed, last_run_at, next_run_at, created_at, updated_at) VALUES (:module, :task, :desc, :schedule, :class, :method, :enabled, :timeout, :overlap, NULL, :next, datetime('now'), datetime('now'))");
            $stmt->execute([
                'module' => $moduleName,
                'task' => $task->name,
                'desc' => $task->description,
                'schedule' => $task->schedule,
                'class' => $task->handler[0],
                'method' => $task->handler[1],
                'enabled' => $task->enabled ? 1 : 0,
                'timeout' => $task->timeout,
                'overlap' => $task->overlapAllowed ? 1 : 0,
                'next' => $nextRun->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            $code = $e->getCode();
            if ($code !== '23000' && !str_contains($e->getMessage(), 'Duplicate') && !str_contains($e->getMessage(), 'UNIQUE')) {
                throw $e;
            }
        }
    }

    public function disableAllForModule(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tasksTable} SET enabled = 0 WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);
    }

    public function deleteAllForModule(string $moduleName): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->tasksTable} WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);

        $stmt = $this->pdo->prepare("DELETE FROM {$this->executionsTable} WHERE module_name = :module");
        $stmt->execute(['module' => $moduleName]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getTasks(?string $moduleName = null): array
    {
        if ($moduleName !== null) {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tasksTable} WHERE module_name = :module ORDER BY next_run_at ASC");
            $stmt->execute(['module' => $moduleName]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tasksTable} ORDER BY next_run_at ASC");
            $stmt->execute();
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getExecutionHistory(string $moduleName, string $taskName, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->executionsTable} WHERE module_name = :module AND task_name = :task ORDER BY id DESC LIMIT :limit");
        $stmt->execute(['module' => $moduleName, 'task' => $taskName, 'limit' => $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cleanupOldExecutions(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        $stmt = $this->pdo->prepare("DELETE FROM {$this->executionsTable} WHERE created_at < :cutoff");
        $stmt->execute(['cutoff' => $cutoff]);
        return $stmt->rowCount();
    }

    public function ensureTables(string $driver): void
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

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tasksTable} (id {$id}, module_name {$keyType} NOT NULL, task_name {$keyType} NOT NULL, description {$keyType}, schedule {$keyType} NOT NULL, handler_class {$keyType} NOT NULL, handler_method {$keyType} NOT NULL, enabled INTEGER NOT NULL DEFAULT 1, timeout INTEGER NOT NULL DEFAULT 300, overlap_allowed INTEGER NOT NULL DEFAULT 0, last_run_at {$dt}, next_run_at {$dt} NOT NULL, last_status {$keyType}, last_error {$keyType}, created_at {$dt} NOT NULL {$nowDefault}, updated_at {$dt} NOT NULL {$nowDefault})");

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->executionsTable} (id {$id}, module_name {$keyType} NOT NULL, task_name {$keyType} NOT NULL, started_at {$dt} NOT NULL {$nowDefault}, finished_at {$dt}, duration_ms INTEGER, status {$keyType} NOT NULL, output {$keyType}, error_message {$keyType}, error_trace {$keyType}, memory_peak_mb INTEGER, pid INTEGER, created_at {$dt} NOT NULL {$nowDefault})");

        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_scheduled_tasks_next ON {$this->tasksTable}(next_run_at, enabled)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_scheduled_tasks_module ON {$this->tasksTable}(module_name)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_task_executions_module ON {$this->executionsTable}(module_name, task_name, started_at)");
        } catch (\Throwable) {
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getDueTasks(\DateTime $now): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tasksTable} WHERE enabled = 1 AND next_run_at <= :now ORDER BY next_run_at ASC LIMIT 50");
            $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{status: string, module: string, task: string, duration_ms: float, error: string|null}
     */
    private function executeTask(int $taskId, string $moduleName, string $taskName, string $handlerClass, string $handlerMethod, int $timeout): array
    {
        $startTime = microtime(true);
        $pid = getmypid();
        $now = date('Y-m-d H:i:s');

        $overlapAllowed = $this->isOverlapAllowed($taskId);

        if (!$overlapAllowed) {
            $running = $this->hasRunningExecution($moduleName, $taskName);
            if ($running) {
                return ['status' => 'skipped', 'module' => $moduleName, 'task' => $taskName, 'duration_ms' => 0, 'error' => 'Task already running (overlap disallowed)'];
            }
        }

        try {
            $timeoutSec = $timeout > 0 ? $timeout : 300;

            $stmt = $this->pdo->prepare("INSERT INTO {$this->executionsTable} (module_name, task_name, started_at, status, pid, duration_ms) VALUES (:module, :task, :now, 'running', :pid, 0)");
            $stmt->execute(['module' => $moduleName, 'task' => $taskName, 'now' => $now, 'pid' => $pid]);
            $executionId = (int)$this->pdo->lastInsertId();

            if (!class_exists($handlerClass)) {
                throw new \RuntimeException("Handler class not found: {$handlerClass}");
            }

            $handler = new $handlerClass();
            if (!method_exists($handler, $handlerMethod)) {
                throw new \RuntimeException("Handler method not found: {$handlerClass}::{$handlerMethod}");
            }

            $output = $handler->{$handlerMethod}();
            $duration = (microtime(true) - $startTime) * 1000;

            $finishNow = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("UPDATE {$this->executionsTable} SET finished_at = :finished, duration_ms = :duration, status = 'success', output = :output WHERE id = :id");
            $stmt->execute([
                'finished' => $finishNow,
                'duration' => (int)$duration,
                'output' => is_string($output) ? $output : json_encode($output, JSON_UNESCAPED_UNICODE),
                'id' => $executionId,
            ]);

            return ['status' => 'success', 'module' => $moduleName, 'task' => $taskName, 'duration_ms' => $duration, 'error' => null];
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $finishNow = date('Y-m-d H:i:s');

            try {
                $stmt = $this->pdo->prepare("UPDATE {$this->executionsTable} SET finished_at = :finished, duration_ms = :duration, status = 'failed', error_message = :error, error_trace = :trace WHERE id = :id");
                $stmt->execute([
                    'finished' => $finishNow,
                    'duration' => (int)$duration,
                    'error' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 65535),
                    'id' => $executionId ?? 0,
                ]);
            } catch (\Throwable) {
            }

            return ['status' => 'failed', 'module' => $moduleName, 'task' => $taskName, 'duration_ms' => $duration, 'error' => $e->getMessage()];
        }
    }

    private function updateNextRun(int $taskId, string $schedule): void
    {
        try {
            $nextRun = $this->parser->getNextRunDate($schedule);
            $now = date('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare("UPDATE {$this->tasksTable} SET last_run_at = :now, next_run_at = :next WHERE id = :id");
            $stmt->execute(['now' => $now, 'next' => $nextRun->format('Y-m-d H:i:s'), 'id' => $taskId]);
        } catch (\Throwable) {
        }
    }

    private function isOverlapAllowed(int $taskId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT overlap_allowed FROM {$this->tasksTable} WHERE id = :id");
            $stmt->execute(['id' => $taskId]);
            return (bool)($stmt->fetchColumn() ?? 0);
        } catch (\Throwable) {
            return false;
        }
    }

    private function hasRunningExecution(string $moduleName, string $taskName): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM {$this->executionsTable} WHERE module_name = :module AND task_name = :task AND status = 'running'");
            $stmt->execute(['module' => $moduleName, 'task' => $taskName]);
            return ((int)$stmt->fetchColumn()) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
