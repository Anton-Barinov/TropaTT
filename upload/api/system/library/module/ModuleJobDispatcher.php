<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Support\AppLog;
use PDO;
use Api\System\Library\Database\IndexHelper;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ModuleJobDispatcher
{
    private PDO $pdo;
    private string $tableName = 'module_jobs';
    private string $contextTable = 'module_job_contexts';

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Dispatch a background job.
     * @return int Job ID
     */
    public function dispatch(
        string $moduleName,
        string $jobName,
        array $payload,
        int $delay = 0,
        ?ModuleExecutionContext $context = null,
        ?string $idempotencyKey = null
    ): int
    {
        if ($context === null || $context->moduleName !== $moduleName) {
            throw new InvalidArgumentException('An explicit module execution context is required');
        }
        if ($idempotencyKey !== null && ($idempotencyKey === '' || strlen($idempotencyKey) > 190)) {
            throw new InvalidArgumentException('Invalid idempotency key');
        }
        self::assertHandlerName($moduleName, $jobName);
        $this->assertActiveModule($moduleName);
        $this->assertOrganization($context);
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (!IndexHelper::indexExists($this->pdo, $driver, $this->contextTable, 'idx_module_job_scope_key')) {
            throw new RuntimeException('Module job idempotency index is unavailable');
        }

        if ($idempotencyKey !== null) {
            $existing = $this->findByIdempotencyKey($moduleName, $context->organizationId, $idempotencyKey);
            if ($existing !== null) {
                return $existing;
            }
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($json) > 1048576) {
            throw new InvalidArgumentException('Module job payload exceeds 1 MiB');
        }
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $now = gmdate('Y-m-d H:i:s');
            $delayUntil = $delay > 0 ? gmdate('Y-m-d H:i:s', time() + $delay) : null;
            // The old payload column is VARCHAR(190). Store new payloads in a
            // companion table so upgrades do not rewrite a large legacy table.
            $stmt = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, job_name, payload, status, delay_until, created_at) VALUES (:module, :job, '{}', 'pending', :delay, :now)");
            $stmt->execute(['module' => $moduleName, 'job' => $jobName, 'delay' => $delayUntil, 'now' => $now]);
            $jobId = (int)$this->pdo->lastInsertId();
            $stmt = $this->pdo->prepare("INSERT INTO {$this->contextTable} (job_id, module_name, organization_id, organization_public_id, actor_public_id, source, correlation_id, idempotency_key, payload_json, created_at) VALUES (:job_id, :module, :org_id, :org_public, :actor, :source, :correlation, :idempotency, :payload, :created)");
            $stmt->execute([
                'job_id' => $jobId,
                'module' => $moduleName,
                'org_id' => $context->organizationId,
                'org_public' => $context->organizationPublicId,
                'actor' => $context->actorPublicId,
                'source' => $context->source,
                'correlation' => $context->correlationId,
                'idempotency' => $idempotencyKey,
                'payload' => $json,
                'created' => $now,
            ]);
            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return $jobId;
        } catch (Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($idempotencyKey !== null && $ownTransaction) {
                $existing = $this->findByIdempotencyKey($moduleName, $context->organizationId, $idempotencyKey);
                if ($existing !== null) {
                    return $existing;
                }
            }
            throw $e;
        }
    }

    /**
     * Process the next pending job.
     * @return array{id: int, status: string}|null
     */
    public function processNext(): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $lock = in_array($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME), ['mysql', 'pgsql'], true) ? ' FOR UPDATE' : '';
            $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE status = 'pending' AND (delay_until IS NULL OR delay_until <= :now) ORDER BY id ASC LIMIT 1{$lock}");
            $stmt->execute(['now' => gmdate('Y-m-d H:i:s')]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($job === false) {
                $this->pdo->rollBack();
                return null;
            }
            $jobId = (int)$job['id'];
            $ctxStmt = $this->pdo->prepare("SELECT * FROM {$this->contextTable} WHERE job_id = :id");
            $ctxStmt->execute(['id' => $jobId]);
            $stored = $ctxStmt->fetch(PDO::FETCH_ASSOC);
            if ($stored === false) {
                // Legacy jobs have no authoritative workspace. Never guess.
                $this->setStatus($jobId, 'paused_legacy');
                $this->pdo->commit();
                return ['id' => $jobId, 'status' => 'paused_legacy'];
            }
            if (!$this->isActiveModule((string)$job['module_name'])) {
                $this->setStatus($jobId, 'paused_module');
                $this->pdo->commit();
                return ['id' => $jobId, 'status' => 'paused_module'];
            }
            $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'running', attempts = attempts + 1 WHERE id = :id");
            $stmt->execute(['id' => $jobId]);
            $stmt = $this->pdo->prepare("UPDATE {$this->contextTable} SET claimed_at = :now WHERE job_id = :id");
            $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $jobId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            AppLog::error('[ModuleJobDispatcher::processNext] Claim failed: ' . $e->getMessage());
            throw $e;
        }

        try {
            if ((string)$stored['module_name'] !== (string)$job['module_name']) {
                throw new RuntimeException('Module context mismatch');
            }
            $context = new ModuleExecutionContext(
                (string)$stored['module_name'],
                (int)$stored['organization_id'],
                $stored['organization_public_id'] !== null ? (string)$stored['organization_public_id'] : null,
                $stored['actor_public_id'] !== null ? (string)$stored['actor_public_id'] : null,
                (string)$stored['source'],
                (string)$stored['correlation_id'],
            );
            $this->assertOrganization($context);
            $class = (string)$job['job_name'];
            self::assertHandlerName($context->moduleName, $class);
            if (!class_exists($class) || !is_subclass_of($class, WorkspaceModuleJobInterface::class)) {
                throw new RuntimeException('Workspace-aware job handler not found');
            }
            $payload = json_decode((string)$stored['payload_json'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($payload)) {
                throw new RuntimeException('Invalid job payload');
            }
            /** @var WorkspaceModuleJobInterface $handler */
            $handler = new $class();
            $handler->handle($payload, $context);
            $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'completed', completed_at = :now WHERE id = :id");
            $stmt->execute(['now' => gmdate('Y-m-d H:i:s'), 'id' => $jobId]);
            $stmt = $this->pdo->prepare("UPDATE {$this->contextTable} SET claimed_at = NULL, last_error = NULL WHERE job_id = :id");
            $stmt->execute(['id' => $jobId]);
            return ['id' => $jobId, 'status' => 'completed'];
        } catch (Throwable $e) {
            $attempts = (int)$job['attempts'] + 1;
            $maxAttempts = max(1, (int)($job['max_attempts'] ?? 3));
            $terminal = $attempts >= $maxAttempts;
            $delay = $terminal ? null : gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** min($attempts - 1, 6))));
            $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = :status, delay_until = :delay, completed_at = :completed WHERE id = :id");
            $stmt->execute(['status' => $terminal ? 'failed' : 'pending', 'delay' => $delay, 'completed' => $terminal ? gmdate('Y-m-d H:i:s') : null, 'id' => $jobId]);
            $stmt = $this->pdo->prepare("UPDATE {$this->contextTable} SET claimed_at = NULL, last_error = :error WHERE job_id = :id");
            $safeError = get_class($e);
            $stmt->execute(['error' => $safeError, 'id' => $jobId]);
            AppLog::error('[ModuleJobDispatcher::processNext] Job ' . $jobId . ' failed: ' . $safeError);
            return ['id' => $jobId, 'status' => $terminal ? 'failed' : 'retrying'];
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

    /** @return array{processed:int,completed:int,retrying:int,failed:int,paused:int} */
    public function runBatch(int $limit = 5, float $maxSeconds = 8.0): array
    {
        $limit = max(1, min(20, $limit));
        $maxSeconds = max(0.5, min(20.0, $maxSeconds));
        $started = microtime(true);
        $this->reclaimStaleRunning(20);
        $result = ['processed' => 0, 'completed' => 0, 'retrying' => 0, 'failed' => 0, 'paused' => 0];
        for ($i = 0; $i < $limit && microtime(true) - $started < $maxSeconds; $i++) {
            $job = $this->processNext();
            if ($job === null) {
                break;
            }
            $result['processed']++;
            $key = match ($job['status']) {
                'completed' => 'completed',
                'retrying' => 'retrying',
                'failed' => 'failed',
                default => 'paused',
            };
            $result[$key]++;
        }
        return $result;
    }

    /** Recover only leases older than ten minutes, in small bounded batches. */
    public function reclaimStaleRunning(int $limit = 20): int
    {
        $limit = max(1, min(20, $limit));
        $cutoff = gmdate('Y-m-d H:i:s', time() - 600);
        $stmt = $this->pdo->prepare("SELECT c.job_id, j.attempts, j.max_attempts FROM {$this->contextTable} c JOIN {$this->tableName} j ON j.id = c.job_id WHERE j.status = 'running' AND c.claimed_at < :cutoff ORDER BY c.job_id ASC LIMIT {$limit}");
        $stmt->execute(['cutoff' => $cutoff]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $reclaimed = 0;
        foreach ($rows as $row) {
            $terminal = (int)$row['attempts'] >= max(1, (int)$row['max_attempts']);
            $job = $this->pdo->prepare("UPDATE {$this->tableName} SET status = :status, delay_until = :delay, completed_at = :completed WHERE id = :id AND status = 'running'");
            $job->execute([
                'status' => $terminal ? 'failed' : 'pending',
                'delay' => $terminal ? null : gmdate('Y-m-d H:i:s'),
                'completed' => $terminal ? gmdate('Y-m-d H:i:s') : null,
                'id' => (int)$row['job_id'],
            ]);
            if ($job->rowCount() === 0) {
                continue;
            }
            $context = $this->pdo->prepare("UPDATE {$this->contextTable} SET claimed_at = NULL, last_error = 'Stale job lease expired' WHERE job_id = :id");
            $context->execute(['id' => (int)$row['job_id']]);
            $reclaimed++;
        }
        return $reclaimed;
    }

    public function resumeModule(string $moduleName): int
    {
        $this->assertActiveModule($moduleName);
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = 'pending' WHERE module_name = :module AND status = 'paused_module' AND id IN (SELECT job_id FROM {$this->contextTable})");
        $stmt->execute(['module' => $moduleName]);
        return $stmt->rowCount();
    }

    /** @return array<string,int> Aggregate queue health without payloads or errors. */
    public function getHealth(): array
    {
        $health = ['pending' => 0, 'running' => 0, 'completed' => 0, 'failed' => 0, 'paused_legacy' => 0, 'paused_module' => 0];
        $stmt = $this->pdo->query("SELECT status, COUNT(*) AS total FROM {$this->tableName} GROUP BY status");
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $status = (string)$row['status'];
            if (array_key_exists($status, $health)) {
                $health[$status] = (int)$row['total'];
            }
        }
        return $health;
    }

    public function cleanupCompleted(int $days = 7): int
    {
        $cutoff = gmdate('Y-m-d H:i:s', strtotime('-' . max(1, $days) . ' days'));
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare("DELETE FROM {$this->contextTable} WHERE job_id IN (SELECT id FROM {$this->tableName} WHERE status IN ('completed', 'failed') AND completed_at IS NOT NULL AND completed_at < :cutoff)");
            $stmt->execute(['cutoff' => $cutoff]);
            $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE status IN ('completed', 'failed') AND completed_at IS NOT NULL AND completed_at < :cutoff");
            $stmt->execute(['cutoff' => $cutoff]);
            $count = $stmt->rowCount();
            if ($ownTransaction) {
                $this->pdo->commit();
            }
            return $count;
        } catch (Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /** @var array<string, true> */
    private static array $schemaEnsured = [];

    public function ensureTable(string $driver): void
    {
        $cacheKey = spl_object_id($this->pdo) . '|' . $driver . '|' . $this->tableName . '|' . $this->contextTable;
        if (isset(self::$schemaEnsured[$cacheKey])) {
            return;
        }
        self::$schemaEnsured[$cacheKey] = true;

        $id = match ($driver) {
            'mysql' => 'INT AUTO_INCREMENT PRIMARY KEY',
            'pgsql' => 'SERIAL PRIMARY KEY',
            'sqlsrv' => 'INT IDENTITY(1,1) PRIMARY KEY',
            default => 'INTEGER PRIMARY KEY AUTOINCREMENT',
        };

        $dt = $driver === 'sqlsrv' ? 'DATETIME2' : 'DATETIME';
        $nowDefault = $driver === 'sqlite' ? "DEFAULT (datetime('now'))" : 'DEFAULT CURRENT_TIMESTAMP';
        $keyType = $driver === 'mysql' ? 'VARCHAR(190)' : 'TEXT';
        $jsonType = $driver === 'mysql' ? 'LONGTEXT' : 'TEXT';

        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->tableName} (id {$id}, module_name {$keyType} NOT NULL, job_name {$keyType} NOT NULL, payload {$keyType} NOT NULL, status {$keyType} NOT NULL DEFAULT 'pending', attempts INTEGER NOT NULL DEFAULT 0, max_attempts INTEGER NOT NULL DEFAULT 3, delay_until {$dt}, created_at {$dt} NOT NULL {$nowDefault}, completed_at {$dt})");
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS {$this->contextTable} (job_id INTEGER PRIMARY KEY, module_name {$keyType} NOT NULL, organization_id INTEGER NOT NULL, organization_public_id VARCHAR(64), actor_public_id VARCHAR(64), source VARCHAR(32) NOT NULL, correlation_id VARCHAR(64) NOT NULL, idempotency_key {$keyType}, payload_json {$jsonType} NOT NULL, last_error {$jsonType}, claimed_at {$dt}, created_at {$dt} NOT NULL {$nowDefault})");

        try {
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_jobs_status', 'status, created_at');
            IndexHelper::createIndexIfNotExists($this->pdo, $this->tableName, 'idx_module_jobs_module', 'module_name');
            IndexHelper::createIndexIfNotExists($this->pdo, $this->contextTable, 'idx_module_job_scope_key', 'module_name, organization_id, idempotency_key', true);
            IndexHelper::createIndexIfNotExists($this->pdo, $this->contextTable, 'idx_module_job_scope', 'organization_id, job_id');
        } catch (\Throwable $e) {
            AppLog::error('[ModuleJobDispatcher::ensureTable] Index creation failed: ' . $e->getMessage());
        }
    }

    private function setStatus(int $jobId, string $status): void
    {
        $stmt = $this->pdo->prepare("UPDATE {$this->tableName} SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $jobId]);
    }

    private function assertActiveModule(string $moduleName): void
    {
        if (!$this->isActiveModule($moduleName)) {
            throw new RuntimeException('Module is not installed or active');
        }
    }

    private function isActiveModule(string $moduleName): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_active FROM module_registry WHERE module_name = :name LIMIT 1');
        $stmt->execute(['name' => $moduleName]);
        return (int)$stmt->fetchColumn() === 1;
    }

    private function assertOrganization(ModuleExecutionContext $context): void
    {
        if ($context->organizationId === 0) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT public_id FROM organizations WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $context->organizationId]);
        $publicId = $stmt->fetchColumn();
        if (!is_string($publicId) || !hash_equals($publicId, (string)$context->organizationPublicId)) {
            throw new RuntimeException('Job workspace no longer exists or does not match');
        }
    }

    private function findByIdempotencyKey(string $moduleName, int $organizationId, string $key): ?int
    {
        $stmt = $this->pdo->prepare("SELECT job_id FROM {$this->contextTable} WHERE module_name = :module AND organization_id = :org AND idempotency_key = :key LIMIT 1");
        $stmt->execute(['module' => $moduleName, 'org' => $organizationId, 'key' => $key]);
        $id = $stmt->fetchColumn();
        return $id === false ? null : (int)$id;
    }

    private static function assertHandlerName(string $moduleName, string $class): void
    {
        $parts = explode('.', $moduleName, 2);
        if (count($parts) !== 2 || !preg_match('/^[a-z][a-z0-9_-]*$/i', $parts[0]) || !preg_match('/^[a-z][a-z0-9_-]*$/i', $parts[1])) {
            throw new InvalidArgumentException('Invalid module namespace');
        }
        $segment = static fn(string $part): string => str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $part)));
        $prefix = 'Module\\' . $segment($parts[0]) . '\\' . $segment($parts[1]) . '\\';
        if (!str_starts_with(strtolower($class), strtolower($prefix)) || str_contains($class, '::') || !preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $class)) {
            throw new InvalidArgumentException('Job handler must belong to its module namespace');
        }
    }
}
