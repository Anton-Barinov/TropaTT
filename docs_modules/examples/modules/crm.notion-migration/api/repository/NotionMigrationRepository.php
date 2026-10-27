<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Repository;

use PDO;

final class NotionMigrationRepository
{
    private int $organizationId = 0;
    private string $workerLeaseToken = '';

    public function __construct(private readonly PDO $pdo, int $organizationId = 0)
    {
        $this->organizationId = max(0, $organizationId);
    }

    public function setOrganizationId(int $organizationId): void
    {
        if ($organizationId < 1) throw new \RuntimeException('NOTION_WORKSPACE_REQUIRED');
        $this->organizationId = $organizationId;
    }

    private function requireWorkspace(): void
    {
        if ($this->organizationId < 1) throw new \RuntimeException('NOTION_WORKSPACE_REQUIRED');
    }

    public function actor(int $id, int $organizationId): ?array
    {
        if ($id < 1 || $organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT id, public_id, login, full_name, is_root, is_active FROM users WHERE id = :id AND is_active = 1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $actor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($actor)) return null;
        if (empty($actor['is_root'])) {
            $membership = $this->pdo->prepare('SELECT 1 FROM organization_memberships WHERE user_id = :user AND organization_id = :organization LIMIT 1');
            $membership->execute(['user' => $id, 'organization' => $organizationId]);
            if ($membership->fetchColumn() === false) return null;
        }
        $actor['organization_id'] = $organizationId;
        return $actor;
    }

    public function activeUserPublicId(string $publicId): ?string
    {
        if ($this->organizationId < 1 || trim($publicId) === '') return null;
        $stmt = $this->pdo->prepare('SELECT u.public_id FROM users u WHERE u.public_id = :public_id AND u.is_active = 1 AND (u.is_root = 1 OR EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id = u.id AND om.organization_id = :organization)) LIMIT 1');
        $stmt->execute(['public_id' => $publicId, 'organization' => $this->organizationId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    private function publicId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(10));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    private function jobInWorkspace(int $jobId): bool
    {
        if ($jobId < 1 || $this->organizationId < 1) return false;
        $stmt = $this->pdo->prepare('SELECT id FROM module_notion_import_jobs WHERE id = :id AND organization_id = :organization LIMIT 1');
        $stmt->execute(['id' => $jobId, 'organization' => $this->organizationId]);
        return $stmt->fetchColumn() !== false;
    }

    private function requireJobLease(int $jobId, bool $allowTerminalForLog = false): void
    {
        if ($this->workerLeaseToken === '') return;

        $activeStatuses = "status IN ('running', 'pausing', 'cancelling')";
        $allowedStatuses = $allowTerminalForLog
            ? "status IN ('running', 'pausing', 'cancelling', 'paused', 'cancelled', 'completed', 'failed')"
            : $activeStatuses;
        $stmt = $this->pdo->prepare('SELECT id FROM module_notion_import_jobs WHERE id = :id AND organization_id = :organization AND lease_token = :token AND lease_until >= UTC_TIMESTAMP() AND ' . $allowedStatuses . ' LIMIT 1');
        $stmt->execute(['id' => $jobId, 'organization' => $this->organizationId, 'token' => $this->workerLeaseToken]);
        if ($stmt->fetchColumn() === false) throw new \RuntimeException('NOTION_JOB_LEASE_LOST');
    }

    public function claimNextJob(int $leaseSeconds = 900): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->query("SELECT j.id, j.organization_id FROM module_notion_import_jobs j JOIN module_notion_connections c ON c.id = j.connection_id AND c.organization_id = j.organization_id WHERE j.organization_id IS NOT NULL AND (j.status = 'queued' OR (j.status IN ('running', 'pausing', 'cancelling') AND (j.lease_until IS NULL OR j.lease_until < UTC_TIMESTAMP()))) AND (j.lease_token IS NULL OR j.lease_until < UTC_TIMESTAMP()) AND NOT EXISTS (SELECT 1 FROM module_notion_import_jobs active WHERE active.connection_id = j.connection_id AND active.organization_id = j.organization_id AND (active.status IN ('pausing', 'cancelling') AND active.lease_until >= UTC_TIMESTAMP() OR active.status IN ('running', 'rolling_back') AND active.lease_until >= UTC_TIMESTAMP())) ORDER BY j.created_at ASC LIMIT 1" . ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''));
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($candidate)) { $this->pdo->commit(); return null; }
            $organizationId = (int)$candidate['organization_id'];
            $jobId = (int)$candidate['id'];
            if ($organizationId < 1 || $jobId < 1) { $this->pdo->commit(); return null; }
            $token = bin2hex(random_bytes(32));
            $leaseUntil = gmdate('Y-m-d H:i:s', time() + max(120, min(3600, $leaseSeconds)));
            $claim = $this->pdo->prepare("UPDATE module_notion_import_jobs SET status = CASE WHEN status IN ('pausing', 'cancelling') THEN status ELSE 'running' END, lease_token = :token, lease_until = :until, started_at = COALESCE(started_at, UTC_TIMESTAMP()), updated_at = UTC_TIMESTAMP() WHERE id = :id AND organization_id = :organization AND (status = 'queued' OR (status IN ('running', 'pausing', 'cancelling') AND (lease_until IS NULL OR lease_until < UTC_TIMESTAMP()))) AND (lease_token IS NULL OR lease_until < UTC_TIMESTAMP())");
            $claim->execute(['token' => $token, 'until' => $leaseUntil, 'id' => $jobId, 'organization' => $organizationId]);
            if ($claim->rowCount() !== 1) { $this->pdo->rollBack(); return null; }
            $this->pdo->commit();
            $this->setOrganizationId($organizationId);
            $this->workerLeaseToken = $token;
            $job = $this->getJobByNumericId($jobId);
            return $job ? array_merge($job, ['lease_token' => $token]) : null;
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function getJobByNumericId(int $id): ?array
    {
        if ($this->organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT public_id FROM module_notion_import_jobs WHERE id = :id AND organization_id = :organization LIMIT 1');
        $stmt->execute(['id' => $id, 'organization' => $this->organizationId]);
        $publicId = $stmt->fetchColumn();
        return $publicId === false ? null : $this->getJob((string)$publicId);
    }

    public function heartbeat(string $jobPublicId, string $leaseToken, int $leaseSeconds = 900): bool
    {
        if ($this->organizationId < 1 || $leaseToken === '') return false;
        $stmt = $this->pdo->prepare("UPDATE module_notion_import_jobs SET lease_until = :until, updated_at = UTC_TIMESTAMP() WHERE public_id = :public_id AND organization_id = :organization AND lease_token = :token AND lease_until >= UTC_TIMESTAMP() AND status IN ('running', 'pausing', 'cancelling')");
        $stmt->execute(['until' => gmdate('Y-m-d H:i:s', time() + max(120, min(3600, $leaseSeconds))), 'public_id' => $jobPublicId, 'organization' => $this->organizationId, 'token' => $leaseToken]);
        return $this->ownsLease($jobPublicId, $leaseToken);
    }

    public function ownsLease(string $jobPublicId, string $leaseToken): bool
    {
        if ($this->organizationId < 1 || $leaseToken === '') return false;
        $stmt = $this->pdo->prepare("SELECT id FROM module_notion_import_jobs WHERE public_id = :public_id AND organization_id = :organization AND lease_token = :token AND lease_until >= UTC_TIMESTAMP() AND status IN ('running', 'pausing', 'cancelling') LIMIT 1");
        $stmt->execute(['public_id' => $jobPublicId, 'organization' => $this->organizationId, 'token' => $leaseToken]);
        return $stmt->fetchColumn() !== false;
    }

    public function releaseLease(string $jobPublicId, string $leaseToken): void
    {
        if ($this->organizationId < 1 || $leaseToken === '') return;
        $this->pdo->prepare('UPDATE module_notion_import_jobs SET lease_token = NULL, lease_until = NULL, updated_at = UTC_TIMESTAMP() WHERE public_id = :public_id AND organization_id = :organization AND lease_token = :token')->execute(['public_id' => $jobPublicId, 'organization' => $this->organizationId, 'token' => $leaseToken]);
    }

    // ── Connections ──

    public function listConnections(): array
    {
        $this->requireWorkspace();
        $stmt = $this->pdo->prepare('SELECT id, public_id, organization_id, name, workspace_name, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_notion_connections WHERE organization_id = :organization ORDER BY created_at DESC');
        $stmt->execute(['organization' => $this->organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        if ($this->organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT id, public_id, organization_id, name, workspace_name, token_encrypted, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_notion_connections WHERE public_id = :public_id AND organization_id = :organization LIMIT 1');
        $stmt->execute(['public_id' => $publicId, 'organization' => $this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getConnectionById(int $id): ?array
    {
        if ($this->organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT id, public_id, organization_id, name, workspace_name, token_encrypted, last_check_status, last_check_message, created_by_user_id, created_at, updated_at FROM module_notion_connections WHERE id = :id AND organization_id = :organization LIMIT 1');
        $stmt->execute(['id' => $id, 'organization' => $this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createConnection(array $data): array
    {
        $this->requireWorkspace();
        if ($this->actor((int)($data['created_by_user_id'] ?? 0), $this->organizationId) === null) throw new \RuntimeException('NOTION_WORKSPACE_MEMBERSHIP_REQUIRED');
        $publicId = $this->publicId('ncf');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_connections (organization_id, public_id, name, workspace_name, token_encrypted, created_by_user_id, created_at, updated_at) VALUES (:organization, :public_id, :name, :workspace_name, :token_encrypted, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'organization' => $this->organizationId,
            'name' => $data['name'],
            'workspace_name' => $data['workspace_name'] ?? null,
            'token_encrypted' => $data['token_encrypted'],
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function updateConnection(string $publicId, array $data): void
    {
        $this->requireWorkspace();
        $sets = [];
        $params = ['public_id' => $publicId, 'organization' => $this->organizationId];
        foreach ($data as $key => $value) {
            $sets[] = "$key = :$key";
            $params[$key] = $value;
        }
        $params['updated_at'] = $this->now();
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE module_notion_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id AND organization_id = :organization')->execute($params);
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $this->requireWorkspace();
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_notion_connections SET last_check_status = :status, last_check_message = :message, updated_at = :updated_at WHERE public_id = :public_id AND organization_id = :organization');
        $stmt->execute([
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'updated_at' => $now,
            'public_id' => $publicId,
            'organization' => $this->organizationId,
        ]);
    }

    public function deleteConnection(string $publicId): void
    {
        $this->requireWorkspace();
        $this->pdo->beginTransaction();
        try {
            $connection = $this->getConnection($publicId);
            if (!$connection) { $this->pdo->commit(); return; }
            $jobIds = $this->pdo->prepare('SELECT id FROM module_notion_import_jobs WHERE connection_id = :connection AND organization_id = :organization');
            $jobIds->execute(['connection' => $connection['id'], 'organization' => $this->organizationId]);
            $ids = array_map('intval', $jobIds->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($ids as $jobId) {
                foreach (['module_notion_import_items', 'module_notion_import_logs'] as $table) {
                    $this->pdo->prepare("DELETE FROM {$table} WHERE job_id = :job AND organization_id = :organization")->execute(['job' => $jobId, 'organization' => $this->organizationId]);
                }
            }
            $this->pdo->prepare('DELETE FROM module_notion_import_jobs WHERE connection_id = :connection AND organization_id = :organization')->execute(['connection' => $connection['id'], 'organization' => $this->organizationId]);
            $this->pdo->prepare('DELETE FROM module_notion_user_mappings WHERE connection_id = :connection AND organization_id = :organization')->execute(['connection' => $connection['id'], 'organization' => $this->organizationId]);
            $this->pdo->prepare('DELETE FROM module_notion_connections WHERE public_id = :public_id AND organization_id = :organization')->execute(['public_id' => $publicId, 'organization' => $this->organizationId]);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function findRunningJobsByConnection(int $connectionId): bool
    {
        if ($this->organizationId < 1 || !$this->getConnectionById($connectionId)) return false;
        $stmt = $this->pdo->prepare("SELECT id FROM module_notion_import_jobs WHERE connection_id = :conn_id AND organization_id = :organization AND status IN ('queued', 'running', 'pausing', 'cancelling', 'paused') LIMIT 1");
        $stmt->execute(['conn_id' => $connectionId, 'organization' => $this->organizationId]);
        return $stmt->fetchColumn() !== false;
    }

    // ── Jobs ──

    public function listJobs(?int $actorUserId = null): array
    {
        $this->requireWorkspace();
        $where = 'j.organization_id = :organization';
        $params = ['organization' => $this->organizationId];
        if ($actorUserId !== null) {
            $where .= ' AND j.created_by_user_id = :uid';
            $params['uid'] = $actorUserId;
        }
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.organization_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, c.name AS connection_name FROM module_notion_import_jobs j JOIN module_notion_connections c ON c.id = j.connection_id AND c.organization_id = j.organization_id WHERE ' . $where . ' ORDER BY j.created_at DESC');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getJob(string $publicId): ?array
    {
        if ($this->organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.organization_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, j.lease_token, j.lease_until, c.name AS connection_name FROM module_notion_import_jobs j JOIN module_notion_connections c ON c.id = j.connection_id AND c.organization_id = j.organization_id WHERE j.public_id = :public_id AND j.organization_id = :organization LIMIT 1');
        $stmt->execute(['public_id' => $publicId, 'organization' => $this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (isset($row['source_object_ids_json']) && is_string($row['source_object_ids_json'])) {
            $row['source_object_ids'] = json_decode($row['source_object_ids_json'], true);
        }
        if (isset($row['options_json']) && is_string($row['options_json'])) {
            $row['options'] = json_decode($row['options_json'], true);
        }
        if (isset($row['stats_json']) && is_string($row['stats_json'])) {
            $row['stats'] = json_decode($row['stats_json'], true);
        }
        return $row;
    }

    public function createJob(array $data): array
    {
        $this->requireWorkspace();
        $connection = $this->getConnectionById((int)($data['connection_id'] ?? 0));
        if (!$connection || $this->actor((int)($data['created_by_user_id'] ?? 0), $this->organizationId) === null) throw new \RuntimeException('NOTION_WORKSPACE_ACCESS_REQUIRED');
        $publicId = $this->publicId('nij');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_import_jobs (organization_id, public_id, connection_id, status, mode, source_object_ids_json, target_root_space_public_id, options_json, created_by_user_id, created_at, updated_at) VALUES (:organization, :public_id, :connection_id, :status, :mode, :source_object_ids_json, :target_root_space_public_id, :options_json, :created_by_user_id, :created_at, :updated_at)');
        $stmt->execute([
            'public_id' => $publicId,
            'organization' => $this->organizationId,
            'connection_id' => $data['connection_id'],
            'status' => 'draft',
            'mode' => $data['mode'],
            'source_object_ids_json' => json_encode($data['source_object_ids_json'], JSON_UNESCAPED_UNICODE),
            'target_root_space_public_id' => $data['target_root_space_public_id'] ?? null,
            'options_json' => json_encode($data['options_json'], JSON_UNESCAPED_UNICODE),
            'created_by_user_id' => $data['created_by_user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        return $this->getJob($publicId) ?? ['public_id' => $publicId];
    }

    public function updateJobStatus(string $publicId, string $status, ?string $expectedStatus = null): bool
    {
        $this->requireWorkspace();
        $now = $this->now();
        $sets = ['status = :status', 'updated_at = :updated_at'];
        $params = ['public_id' => $publicId, 'organization' => $this->organizationId, 'status' => $status, 'updated_at' => $now];

        if ($status === 'running' || $status === 'queued') {
            $sets[] = 'started_at = COALESCE(started_at, :started_at)';
            $params['started_at'] = $now;
        }
        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            $sets[] = 'finished_at = :finished_at';
            $params['finished_at'] = $now;
        }

        $where = ' WHERE public_id = :public_id AND organization_id = :organization';
        if ($expectedStatus !== null) { $where .= ' AND status = :expected_status'; $params['expected_status'] = $expectedStatus; }
        if ($this->workerLeaseToken !== '') { $where .= ' AND lease_token = :worker_lease AND lease_until >= UTC_TIMESTAMP()'; $params['worker_lease'] = $this->workerLeaseToken; }
        $statement = $this->pdo->prepare('UPDATE module_notion_import_jobs SET ' . implode(', ', $sets) . $where);
        $statement->execute($params);
        if ($this->workerLeaseToken !== '' && $statement->rowCount() !== 1) throw new \RuntimeException('NOTION_JOB_LEASE_LOST');
        return $statement->rowCount() === 1;
    }

    public function updateJobProgress(string $publicId, string $step, float $percent, array $stats): void
    {
        $this->requireWorkspace();
        $now = $this->now();
        $where = ' WHERE public_id = :public_id AND organization_id = :organization';
        $params = ['step' => $step, 'percent' => $percent, 'stats' => json_encode($stats, JSON_UNESCAPED_UNICODE), 'updated_at' => $now, 'public_id' => $publicId, 'organization' => $this->organizationId];
        if ($this->workerLeaseToken !== '') { $where .= ' AND lease_token = :worker_lease AND lease_until >= UTC_TIMESTAMP()'; $params['worker_lease'] = $this->workerLeaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_notion_import_jobs SET current_step = :step, progress_percent = :percent, stats_json = :stats, updated_at = :updated_at' . $where);
        $stmt->execute($params);
        if ($this->workerLeaseToken !== '' && $stmt->rowCount() !== 1) throw new \RuntimeException('NOTION_JOB_LEASE_LOST');
    }

    public function resetFailedItems(string $publicId): int
    {
        $this->requireWorkspace();
        $stmt = $this->pdo->prepare("UPDATE module_notion_import_items SET status = 'pending', error_code = NULL, error_message = NULL, attempts = 0 WHERE organization_id = :organization AND job_id = (SELECT id FROM module_notion_import_jobs WHERE public_id = :public_id AND organization_id = :organization) AND status = 'failed'");
        $stmt->execute(['public_id' => $publicId, 'organization' => $this->organizationId]);
        return $stmt->rowCount();
    }

    // ── Job Items ──

    public function listJobItems(string $jobPublicId, string $sourceType = '', string $status = '', int $limit = 50, int $page = 1): array
    {
        if ($this->organizationId < 1 || !$this->getJob($jobPublicId)) return [];
        $where = ['i.organization_id = :organization', 'i.job_id = (SELECT id FROM module_notion_import_jobs WHERE public_id = :job_pub AND organization_id = :organization)'];
        $params = ['job_pub' => $jobPublicId, 'organization' => $this->organizationId];
        if ($sourceType !== '') {
            $where[] = 'i.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }
        if ($status !== '') {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        $offset = max(0, ($page - 1) * $limit);
        $stmt = $this->pdo->prepare('SELECT i.id, i.job_id, i.source_type, i.source_id, i.source_key, i.source_parent_id, i.target_type, i.target_public_id, i.status, i.checksum, i.source_updated_at, i.error_code, i.error_message, i.attempts, i.payload_json, i.created_at, i.updated_at FROM module_notion_import_items i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertJobItem(int $jobId, string $sourceType, string $sourceId, array $data): void
    {
        $this->requireWorkspace();
        if (!$this->jobInWorkspace($jobId)) throw new \RuntimeException('NOTION_JOB_NOT_FOUND');
        $this->requireJobLease($jobId);
        $now = $this->now();
        $existing = $this->findJobItem($jobId, $sourceType, $sourceId);
        if ($existing) {
            $sets = ['updated_at = :updated_at'];
            $params = ['id' => $existing['id'], 'organization' => $this->organizationId, 'updated_at' => $now];
            foreach (['source_key', 'source_parent_id', 'target_type', 'target_public_id', 'status', 'checksum', 'source_updated_at', 'error_code', 'error_message', 'attempts', 'payload_json'] as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            $this->pdo->prepare('UPDATE module_notion_import_items SET ' . implode(', ', $sets) . ' WHERE id = :id AND organization_id = :organization')->execute($params);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO module_notion_import_items (organization_id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at) VALUES (:organization, :job_id, :source_type, :source_id, :source_key, :source_parent_id, :target_type, :target_public_id, :status, :checksum, :source_updated_at, :error_code, :error_message, :attempts, :payload_json, :created_at, :updated_at)');
            $stmt->execute([
                'organization' => $this->organizationId,
                'job_id' => $jobId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_key' => $data['source_key'] ?? null,
                'source_parent_id' => $data['source_parent_id'] ?? null,
                'target_type' => $data['target_type'] ?? null,
                'target_public_id' => $data['target_public_id'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'checksum' => $data['checksum'] ?? null,
                'source_updated_at' => $data['source_updated_at'] ?? null,
                'error_code' => $data['error_code'] ?? null,
                'error_message' => $data['error_message'] ?? null,
                'attempts' => $data['attempts'] ?? 0,
                'payload_json' => isset($data['payload_json']) ? (is_string($data['payload_json']) ? $data['payload_json'] : json_encode($data['payload_json'], JSON_UNESCAPED_UNICODE)) : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function findJobItem(int $jobId, string $sourceType, string $sourceId): ?array
    {
        if ($this->organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT id, organization_id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at FROM module_notion_import_items WHERE organization_id = :organization AND job_id = :job_id AND source_type = :source_type AND source_id = :source_id LIMIT 1');
        $stmt->execute(['organization' => $this->organizationId, 'job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findJobItemsByStatus(int $jobId, string $status, int $limit = 500, int $offset = 0): array
    {
        if ($this->organizationId < 1 || !$this->jobInWorkspace($jobId)) return [];
        $limit = max(1, min(1000, $limit)); $offset = max(0, $offset);
        $stmt = $this->pdo->prepare('SELECT id, organization_id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at FROM module_notion_import_items WHERE organization_id = :organization AND job_id = :job_id AND status = :status ORDER BY id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute(['organization' => $this->organizationId, 'job_id' => $jobId, 'status' => $status]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countJobItemsByStatus(int $jobId): array
    {
        if ($this->organizationId < 1 || !$this->jobInWorkspace($jobId)) return ['pending' => 0, 'processing' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM module_notion_import_items WHERE organization_id = :organization AND job_id = :job_id GROUP BY status");
        $stmt->execute(['organization' => $this->organizationId, 'job_id' => $jobId]);
        $result = ['pending' => 0, 'processing' => 0, 'imported' => 0, 'skipped' => 0, 'failed' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string)$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    public function findTargetForSource(int $jobId, string $sourceType, string $sourceId): ?string
    {
        if ($this->organizationId < 1 || !$this->jobInWorkspace($jobId)) return null;
        $stmt = $this->pdo->prepare("SELECT target_public_id FROM module_notion_import_items WHERE organization_id = :organization AND job_id = :job_id AND source_type = :source_type AND source_id = :source_id AND status IN ('imported', 'skipped') LIMIT 1");
        $stmt->execute(['organization' => $this->organizationId, 'job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    // ── Job Logs ──

    public function addJobLog(string $jobPublicId, string $level, ?string $step, string $message, array $context = []): void
    {
        $this->requireWorkspace();
        $job = $this->getJob($jobPublicId);
        if (!$job) throw new \RuntimeException('NOTION_JOB_NOT_FOUND');
        $this->requireJobLease((int)$job['id'], true);
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_import_logs (organization_id, job_id, level, step, message, context_json, created_at) VALUES (:organization, (SELECT id FROM module_notion_import_jobs WHERE public_id = :pub AND organization_id = :organization), :level, :step, :message, :context, :created_at)');
        $stmt->execute([
            'organization' => $this->organizationId,
            'pub' => $jobPublicId,
            'level' => $level,
            'step' => $step,
            'message' => mb_substr($message, 0, 2000),
            'context' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
        ]);
    }

    public function listJobLogs(string $jobPublicId, string $level = '', string $step = '', int $limit = 50, int $page = 1): array
    {
        if ($this->organizationId < 1 || !$this->getJob($jobPublicId)) return [];
        $where = ['l.organization_id = :organization', 'l.job_id = (SELECT id FROM module_notion_import_jobs WHERE public_id = :job_pub AND organization_id = :organization)'];
        $params = ['job_pub' => $jobPublicId, 'organization' => $this->organizationId];
        if ($level !== '') {
            $where[] = 'l.level = :level';
            $params['level'] = $level;
        }
        if ($step !== '') {
            $where[] = 'l.step = :step';
            $params['step'] = $step;
        }
        $limit = max(1, min(1000, $limit)); $offset = max(0, ($page - 1) * $limit);
        $stmt = $this->pdo->prepare('SELECT l.id, l.job_id, l.level, l.step, l.message, l.context_json, l.created_at FROM module_notion_import_logs l WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Report ──

    public function getJobReport(string $jobPublicId): array
    {
        if ($this->organizationId < 1) return [];
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.organization_id, j.connection_id, j.status, j.mode, j.source_object_ids_json, j.target_root_space_public_id, j.options_json, j.current_step, j.progress_percent, j.stats_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at FROM module_notion_import_jobs j WHERE j.public_id = :pub AND j.organization_id = :organization LIMIT 1');
        $stmt->execute(['pub' => $jobPublicId, 'organization' => $this->organizationId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return [];
        }

        $itemCounts = $this->countJobItemsByStatus((int)$job['id']);
        $stats = json_decode((string)($job['stats_json'] ?? '{}'), true) ?? [];

        return [
            'job_public_id' => $jobPublicId,
            'mode' => $job['mode'],
            'status' => $job['status'],
            'current_step' => $job['current_step'],
            'progress_percent' => (float)($job['progress_percent'] ?? 0),
            'items' => $itemCounts,
            'stats' => $stats,
            'started_at' => $job['started_at'],
            'finished_at' => $job['finished_at'],
            'created_at' => $job['created_at'],
        ];
    }

    public function getJobReportMarkdown(string $jobPublicId): string
    {
        $report = $this->getJobReport($jobPublicId);
        if ($report === []) {
            return 'Job not found.';
        }

        $lines = [];
        $lines[] = '# Notion Migration Report: ' . $jobPublicId;
        $lines[] = '';
        $lines[] = '- **Mode:** ' . ($report['mode'] ?? 'N/A');
        $lines[] = '- **Status:** ' . ($report['status'] ?? 'N/A');
        $lines[] = '- **Progress:** ' . ($report['progress_percent'] ?? 0) . '%';
        $lines[] = '- **Current step:** ' . ($report['current_step'] ?? 'N/A');
        $lines[] = '';
        $lines[] = '## Items';
        $lines[] = '';
        $lines[] = '| Status | Count |';
        $lines[] = '|--------|-------|';
        foreach (($report['items'] ?? []) as $status => $count) {
            $lines[] = "| $status | $count |";
        }
        $lines[] = '';
        $lines[] = '- **Started:** ' . ($report['started_at'] ?? 'N/A');
        $lines[] = '- **Finished:** ' . ($report['finished_at'] ?? 'N/A');

        return implode("\n", $lines);
    }

    // ── User Mappings ──

    public function listUserMappings(int $connectionId): array
    {
        if ($this->organizationId < 1 || !$this->getConnectionById($connectionId)) return [];
        $stmt = $this->pdo->prepare('SELECT id, organization_id, connection_id, notion_user_id, display_name, email, crm_user_public_id, mapping_status, created_at, updated_at FROM module_notion_user_mappings WHERE connection_id = :conn_id AND organization_id = :organization ORDER BY display_name ASC');
        $stmt->execute(['conn_id' => $connectionId, 'organization' => $this->organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertUserMapping(int $connectionId, string $notionUserId, string $displayName, ?string $email = null): void
    {
        $this->requireWorkspace();
        if (!$this->getConnectionById($connectionId)) throw new \RuntimeException('NOTION_CONNECTION_NOT_FOUND');
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_notion_user_mappings (organization_id, connection_id, notion_user_id, display_name, email, mapping_status, created_at, updated_at) VALUES (:organization, :conn_id, :nid, :name, :email, :status, :created_at, :updated_at) ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), email = VALUES(email), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'organization' => $this->organizationId,
            'conn_id' => $connectionId,
            'nid' => $notionUserId,
            'name' => $displayName,
            'email' => $email,
            'status' => 'unmapped',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateUserMapping(int $mappingId, ?string $crmUserPublicId, string $status): void
    {
        $this->requireWorkspace();
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_notion_user_mappings SET crm_user_public_id = :crm_pub, mapping_status = :status, updated_at = :updated_at WHERE id = :id AND organization_id = :organization');
        $stmt->execute([
            'crm_pub' => $crmUserPublicId,
            'status' => $status,
            'updated_at' => $now,
            'id' => $mappingId,
            'organization' => $this->organizationId,
        ]);
    }

    // ── Settings ──

    public function getModuleSettings(): array
    {
        $defaults = [
            'request_timeout_seconds' => 30,
            'max_retries' => 4,
            'default_batch_size' => 100,
            'max_pages_per_job' => 0,
            'max_depth' => 20,
            'include_comments_by_default' => true,
            'publish_by_default' => false,
        ];
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM module_notion_settings WHERE module_name = 'crm.notion-migration'");
        $stmt->execute();
        $settings = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $settings[(string)$row['setting_key']] = json_decode((string)$row['setting_value'], true);
        }
        return array_merge($defaults, $settings);
    }

    public function setModuleSetting(string $key, mixed $value): void
    {
        $now = $this->now();
        $jsonValue = json_encode($value, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("INSERT INTO module_notion_settings (module_name, setting_key, setting_value, updated_at) VALUES ('crm.notion-migration', :skey, :svalue, :updated_at) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([
            'skey' => $key,
            'svalue' => $jsonValue,
            'updated_at' => $now,
        ]);
    }
}
