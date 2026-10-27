<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Repository;

use PDO;

final class JiraMigrationRepository
{
    private int $organizationId = 0;
    private ?string $workerLeaseToken = null;
    public function __construct(private readonly PDO $pdo) {}
    public function setOrganizationId(int $organizationId): void { $this->organizationId = max(0, $organizationId); }
    public function setWorkerLeaseToken(?string $token): void { $this->workerLeaseToken = $token !== null && $token !== '' ? $token : null; }
    public function getWorkerLeaseToken(): ?string { return $this->workerLeaseToken; }
    public function actor(int $id, int $organizationId): ?array { if($id<1||$organizationId<1)return null;$st=$this->pdo->prepare("SELECT u.id,u.public_id,u.login,u.full_name,u.is_root,u.is_active FROM users u WHERE u.id=:id AND u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:org)) LIMIT 1");$st->execute(['id'=>$id,'org'=>$organizationId]);$r=$st->fetch(PDO::FETCH_ASSOC);if(!is_array($r))return null;$r['organization_id']=$organizationId;return $r; }

    private function jobInWorkspace(int $jobId): bool { if($jobId<1||$this->organizationId<1)return false;$q=$this->pdo->prepare('SELECT id FROM module_jira_jobs WHERE id=:id AND organization_id=:org LIMIT 1');$q->execute(['id'=>$jobId,'org'=>$this->organizationId]);return$q->fetchColumn()!==false; }
    private function connectionInWorkspace(int $connectionId): bool { if($connectionId<1||$this->organizationId<1)return false;$q=$this->pdo->prepare('SELECT id FROM module_jira_connections WHERE id=:id AND organization_id=:org LIMIT 1');$q->execute(['id'=>$connectionId,'org'=>$this->organizationId]);return$q->fetchColumn()!==false; }

    private function publicId(string $prefix): string
    {
        return $prefix . '_' . bin2hex(random_bytes(10));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    // ── Connections ──

    public function listConnections(string $actorPublicId): array { if($this->organizationId<1)return[];$q=$this->pdo->prepare('SELECT id,public_id,name,site_url,auth_type,email,cloud_id,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_jira_connections WHERE organization_id=:org ORDER BY created_at DESC');$q->execute(['org'=>$this->organizationId]);return$q->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function getConnection(string $publicId): ?array { if($this->organizationId<1)return null;$q=$this->pdo->prepare('SELECT id,public_id,name,site_url,auth_type,email,token_encrypted,cloud_id,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_jira_connections WHERE public_id=:id AND organization_id=:org LIMIT 1');$q->execute(['id'=>$publicId,'org'=>$this->organizationId]);$r=$q->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }

    public function createConnection(array $data): array { $org=(int)($data['organization_id']??$this->organizationId);$owner=(int)($data['created_by_user_id']??0);if($org<1||$owner<1)throw new \RuntimeException('JIRA_WORKSPACE_REQUIRED');$pid=$this->publicId('jic');$now=$this->now();$q=$this->pdo->prepare("INSERT INTO module_jira_connections (public_id,organization_id,name,site_url,auth_type,email,token_encrypted,created_by_user_id,created_at,updated_at) SELECT :pid,:org,:name,:url,:auth,:email,:token,u.id,:created,:updated FROM users u WHERE u.id=:owner AND u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:check_org))");$q->execute(['pid'=>$pid,'org'=>$org,'name'=>$data['name'],'url'=>$data['site_url'],'auth'=>$data['auth_type'],'email'=>$data['email']??null,'token'=>$data['token_encrypted'],'created'=>$now,'updated'=>$now,'owner'=>$owner,'check_org'=>$org]);if($q->rowCount()!==1)throw new \RuntimeException('JIRA_WORKSPACE_ACCESS_REVOKED');$this->organizationId=$org;return$this->getConnection($pid)??['public_id'=>$pid,'organization_id'=>$org]; }

    public function updateConnection(string $publicId, array $data): void
    {
        $sets = [];
        $params = ['public_id' => $publicId, 'org' => $this->organizationId];
        foreach ($data as $key => $value) {
            $sets[] = "$key = :$key";
            $params[$key] = $value;
        }
        $params['updated_at'] = $this->now();
        $sets[] = 'updated_at = :updated_at';
        $this->pdo->prepare('UPDATE module_jira_connections SET ' . implode(', ', $sets) . ' WHERE public_id = :public_id AND organization_id=:org')->execute($params);
    }

    public function updateConnectionLastCheck(string $publicId, string $status, string $message): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_jira_connections SET status = :status, last_error = :message, last_checked_at = :now, updated_at = :now WHERE public_id = :public_id AND organization_id=:org');
        $stmt->execute([
            'org' => $this->organizationId,
            'status' => $status,
            'message' => mb_substr($message, 0, 500),
            'now' => $now,
            'public_id' => $publicId,
        ]);
    }

    public function deleteConnection(string $publicId): void
    {
        if ($this->organizationId < 1) { throw new \RuntimeException('JIRA_WORKSPACE_REQUIRED'); }
        $this->pdo->beginTransaction();
        try {
            $lockClause = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $conn = $this->pdo->prepare('SELECT id FROM module_jira_connections WHERE public_id=:public_id AND organization_id=:org LIMIT 1' . $lockClause);
            $conn->execute(['public_id' => $publicId, 'org' => $this->organizationId]);
            $connectionId = (int)$conn->fetchColumn();
            if ($connectionId < 1) { $this->pdo->commit(); return; }
            $busy = $this->pdo->prepare("SELECT id FROM module_jira_jobs WHERE connection_id=:conn AND organization_id=:org AND status IN ('queued','running','paused','cancelling') LIMIT 1");
            $busy->execute(['conn' => $connectionId, 'org' => $this->organizationId]);
            if ($busy->fetchColumn() !== false) { throw new \RuntimeException('JIRA_CONNECTION_HAS_ACTIVE_JOBS'); }
            $jobIds = $this->pdo->prepare('SELECT id FROM module_jira_jobs WHERE connection_id=:conn AND organization_id=:org');
            $jobIds->execute(['conn' => $connectionId, 'org' => $this->organizationId]);
            $ids = array_map('intval', $jobIds->fetchAll(PDO::FETCH_COLUMN) ?: []);
            foreach ($ids as $index => $jobId) {
                $placeholder = ':job_' . $index;
                $params = ['org' => $this->organizationId, 'job_' . $index => $jobId];
                foreach (['module_jira_job_items', 'module_jira_job_logs', 'module_jira_unresolved_entities', 'module_jira_unsupported_fields'] as $table) {
                    $this->pdo->prepare("DELETE FROM {$table} WHERE job_id={$placeholder} AND job_id IN (SELECT id FROM module_jira_jobs WHERE organization_id=:org)")->execute($params);
                }
            }
            $this->pdo->prepare('DELETE FROM module_jira_jobs WHERE connection_id=:conn AND organization_id=:org')->execute(['conn' => $connectionId, 'org' => $this->organizationId]);
            $this->pdo->prepare('DELETE FROM module_jira_identity_mappings WHERE connection_id=:conn AND connection_id IN (SELECT id FROM module_jira_connections WHERE organization_id=:org)')->execute(['conn' => $connectionId, 'org' => $this->organizationId]);
            $this->pdo->prepare('DELETE FROM module_jira_rate_limits WHERE connection_id=:conn AND connection_id IN (SELECT id FROM module_jira_connections WHERE organization_id=:org)')->execute(['conn' => $connectionId, 'org' => $this->organizationId]);
            $this->pdo->prepare('DELETE FROM module_jira_connections WHERE id=:conn AND organization_id=:org')->execute(['conn' => $connectionId, 'org' => $this->organizationId]);
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    public function findRunningJobsByConnection(int $connectionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_jira_jobs WHERE connection_id = :conn_id AND organization_id=:org AND status IN ('queued', 'running', 'paused', 'cancelling') LIMIT 1");
        $stmt->execute(['conn_id' => $connectionId, 'org' => $this->organizationId]);
        return $stmt->fetchColumn() !== false;
    }

    public function getConnectionById(int $id): ?array { if($id<1||$this->organizationId<1)return null;$q=$this->pdo->prepare('SELECT id,public_id,name,site_url,auth_type,email,token_encrypted,cloud_id,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_jira_connections WHERE id=:id AND organization_id=:org LIMIT 1');$q->execute(['id'=>$id,'org'=>$this->organizationId]);$r=$q->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }

    public function listJobs(?string $actorPublicId=null): array { if($this->organizationId<1)return[];$sql='SELECT j.id,j.public_id,j.organization_id,j.connection_id,j.status,j.mode,j.source_scope_json,j.target_options_json,j.current_step,j.progress_percent,j.progress_json,j.created_by_user_id,j.started_at,j.finished_at,j.created_at,j.updated_at,j.summary_json,c.name connection_name FROM module_jira_jobs j JOIN module_jira_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.organization_id=:org';$p=['org'=>$this->organizationId];if($actorPublicId!==null){$sql.=' AND j.created_by_user_id=(SELECT id FROM users WHERE public_id=:pub)';$p['pub']=$actorPublicId;}$q=$this->pdo->prepare($sql.' ORDER BY j.created_at DESC');$q->execute($p);return$q->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.organization_id, j.connection_id, j.status, j.mode, j.source_scope_json, j.target_options_json, j.current_step, j.progress_percent, j.progress_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, j.summary_json, c.name AS connection_name FROM module_jira_jobs j LEFT JOIN module_jira_connections c ON c.id = j.connection_id WHERE j.public_id = :public_id AND j.organization_id=:org AND c.organization_id=j.organization_id LIMIT 1');
        $stmt->execute(['public_id' => $publicId, 'org' => $this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        if (isset($row['source_scope_json']) && is_string($row['source_scope_json'])) {
            $row['source_scope'] = json_decode($row['source_scope_json'], true);
        }
        if (isset($row['target_options_json']) && is_string($row['target_options_json'])) {
            $row['target_options'] = json_decode($row['target_options_json'], true);
        }
        if (isset($row['progress_json']) && is_string($row['progress_json'])) {
            $row['progress'] = json_decode($row['progress_json'], true);
        }
        if (isset($row['summary_json']) && is_string($row['summary_json'])) {
            $row['summary'] = json_decode($row['summary_json'], true);
        }
        return $row;
    }

    /** Atomically claim one queued or abandoned job and persist a fencing token. */
    public function claimNextJob(int $leaseSeconds = 300): ?array
    {
        $this->organizationId = 0;
        $this->workerLeaseToken = null;
        $leaseSeconds = max(60, min(900, $leaseSeconds));
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            $this->pdo->beginTransaction();
            $now = $this->now();
            $sql = "SELECT j.id,j.public_id,j.organization_id,j.created_by_user_id FROM module_jira_jobs j JOIN module_jira_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.organization_id IS NOT NULL AND (j.status='queued' OR (j.status='running' AND (j.lease_until IS NULL OR j.lease_until<=:expired_before))) ORDER BY j.created_at ASC LIMIT 1";
            if ($driver === 'mysql') {
                $sql .= ' FOR UPDATE';
            }
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['expired_before' => $now]);
            $candidate = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($candidate)) {
                $this->pdo->commit();
                return null;
            }

            $token = bin2hex(random_bytes(32));
            $until = gmdate('Y-m-d H:i:s', time() + $leaseSeconds);
            $update = $this->pdo->prepare("UPDATE module_jira_jobs SET status='running',lease_token=:token,lease_until=:until,started_at=COALESCE(started_at,:started),updated_at=:updated WHERE id=:id AND organization_id=:org AND (status='queued' OR (status='running' AND (lease_until IS NULL OR lease_until<=:expired_before)))");
            $update->execute(['token' => $token, 'until' => $until, 'started' => $now, 'updated' => $now, 'id' => (int)$candidate['id'], 'org' => (int)$candidate['organization_id'], 'expired_before' => $now]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }
            $this->pdo->commit();
            $this->organizationId = (int)$candidate['organization_id'];
            $this->workerLeaseToken = $token;
            $job = $this->getJob((string)$candidate['public_id']);
            if ($job === null) {
                $this->releaseJobLease((string)$candidate['public_id']);
                return null;
            }
            $job['lease_token'] = $token;
            return $job;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            // A competing SQLite writer can hold the database lock. The next cron tick retries.
            if ($driver === 'sqlite' && str_contains(strtolower($e->getMessage()), 'locked')) {
                return null;
            }
            throw $e;
        }
    }

    /** Renew an unexpired lease only when the same fencing token still owns it. */
    public function heartbeatJobLease(string $jobPublicId, int $leaseSeconds = 300): bool
    {
        $token = $this->workerLeaseToken;
        if ($token === null || $this->organizationId < 1) {
            return false;
        }
        $now = $this->now();
        $until = gmdate('Y-m-d H:i:s', time() + max(60, min(900, $leaseSeconds)));
        $stmt = $this->pdo->prepare("UPDATE module_jira_jobs SET lease_until=:until,updated_at=:updated WHERE public_id=:pub AND organization_id=:org AND status='running' AND lease_token=:token AND lease_until>:unexpired");
        $stmt->execute(['until' => $until, 'updated' => $now, 'pub' => $jobPublicId, 'org' => $this->organizationId, 'token' => $token, 'unexpired' => $now]);
        if ($stmt->rowCount() === 1) {
            return true;
        }
        $check = $this->pdo->prepare("SELECT id FROM module_jira_jobs WHERE public_id=:pub AND organization_id=:org AND status='running' AND lease_token=:token AND lease_until>:now LIMIT 1");
        $check->execute(['pub' => $jobPublicId, 'org' => $this->organizationId, 'token' => $token, 'now' => $now]);
        return $check->fetchColumn() !== false;
    }

    /** Release only the current worker's lease; a stale worker cannot clear a successor's token. */
    public function releaseJobLease(string $jobPublicId): bool
    {
        $token = $this->workerLeaseToken;
        if ($token === null || $this->organizationId < 1) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE module_jira_jobs SET lease_token=NULL,lease_until=NULL,updated_at=:now WHERE public_id=:pub AND organization_id=:org AND lease_token=:token');
        $stmt->execute(['now' => $this->now(), 'pub' => $jobPublicId, 'org' => $this->organizationId, 'token' => $token]);
        $released = $stmt->rowCount() === 1;
        if ($released) {
            $this->workerLeaseToken = null;
        }
        return $released;
    }

    public function createJob(array $data): array { $org=(int)($data['organization_id']??$this->organizationId);$owner=(int)($data['created_by_user_id']??0);if($org<1||$owner<1)throw new \RuntimeException('JIRA_WORKSPACE_REQUIRED');$pid=$this->publicId('jij');$now=$this->now();$q=$this->pdo->prepare("INSERT INTO module_jira_jobs (public_id,organization_id,connection_id,status,mode,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) SELECT :pid,:org,c.id,'draft',:mode,:scope,:options,u.id,:created,:updated FROM module_jira_connections c JOIN users u ON u.id=:owner AND u.is_active=1 WHERE c.id=:conn AND c.organization_id=:check_org AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=c.organization_id))");$q->execute(['pid'=>$pid,'org'=>$org,'mode'=>$data['mode'],'scope'=>json_encode($data['source_scope_json'],JSON_UNESCAPED_UNICODE),'options'=>json_encode($data['target_options_json']??[],JSON_UNESCAPED_UNICODE),'owner'=>$owner,'created'=>$now,'updated'=>$now,'conn'=>$data['connection_id'],'check_org'=>$org]);if($q->rowCount()!==1)throw new \RuntimeException('JIRA_CONNECTION_NOT_FOUND');$this->organizationId=$org;return$this->getJob($pid)??['public_id'=>$pid,'organization_id'=>$org]; }

    public function updateJobStatus(string $publicId, string $status): void
    {
        $now = $this->now();
        $sets = ['status = :status', 'updated_at = :updated_at'];
        $params = ['public_id' => $publicId, 'status' => $status, 'updated_at' => $now, 'org' => $this->organizationId];

        if ($status === 'running' || $status === 'queued') {
            $sets[] = 'started_at = COALESCE(started_at, :started_at)';
            $params['started_at'] = $now;
        }
        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            $sets[] = 'finished_at = :finished_at';
            $params['finished_at'] = $now;
        }

        $where = ' WHERE public_id = :public_id AND organization_id=:org';
        if ($this->workerLeaseToken !== null) {
            $where .= ' AND lease_token=:lease_token AND lease_until>:lease_now';
            if ($status === 'cancelled') {
                $where .= " AND status IN ('running','cancelling')";
            } elseif (in_array($status, ['completed', 'failed'], true)) {
                $where .= " AND status='running'";
            }
            $params['lease_token'] = $this->workerLeaseToken;
            $params['lease_now'] = $now;
        }
        $stmt = $this->pdo->prepare('UPDATE module_jira_jobs SET ' . implode(', ', $sets) . $where);
        $stmt->execute($params);
        if ($this->workerLeaseToken !== null && $stmt->rowCount() === 0 && !$this->workerLeaseMatches($publicId, $now)) {
            throw new \RuntimeException('JIRA_JOB_LEASE_LOST');
        }
    }

    /** Compare-and-set for user initiated job transitions; returns false on a state race. */
    public function transitionJobStatus(string $publicId, array $expectedStatuses, string $targetStatus): bool
    {
        $allowedTargets = ['queued', 'paused', 'cancelling', 'cancelled', 'completed'];
        $expectedStatuses = array_values(array_unique(array_filter(array_map('strval', $expectedStatuses))));
        if ($this->organizationId < 1 || $expectedStatuses === [] || !in_array($targetStatus, $allowedTargets, true)) {
            return false;
        }
        $now = $this->now();
        $params = ['public_id' => $publicId, 'org' => $this->organizationId, 'updated_at' => $now];
        $holders = [];
        foreach ($expectedStatuses as $i => $status) {
            $key = 'expected_' . $i;
            $holders[] = ':' . $key;
            $params[$key] = $status;
        }
        $sets = ['status=:target_status', 'updated_at=:updated_at'];
        $params['target_status'] = $targetStatus;
        if ($targetStatus === 'queued') {
            $sets[] = 'lease_token=NULL';
            $sets[] = 'lease_until=NULL';
            $sets[] = 'started_at=NULL';
            $sets[] = 'finished_at=NULL';
        } elseif ($targetStatus === 'paused' || $targetStatus === 'cancelled') {
            // Stop stale worker writes immediately. A running cancel uses `cancelling`
            // so its current lease can finish at a safe checkpoint.
            $sets[] = 'lease_token=NULL';
            $sets[] = 'lease_until=NULL';
        }
        if ($targetStatus === 'cancelled' || $targetStatus === 'completed') {
            $sets[] = 'finished_at=:finished_at';
            $params['finished_at'] = $now;
        }
        $stmt = $this->pdo->prepare('UPDATE module_jira_jobs SET ' . implode(',', $sets) . ' WHERE public_id=:public_id AND organization_id=:org AND status IN (' . implode(',', $holders) . ')');
        $stmt->execute($params);
        return $stmt->rowCount() === 1;
    }

    /** Lock expected terminal state, reset failed records and queue retry as one transaction. */
    public function retryFailedJob(string $publicId, string $expectedStatus): ?int
    {
        $allowed = ['completed', 'failed', 'cancelled'];
        if ($this->organizationId < 1 || !in_array($expectedStatus, $allowed, true)) { return null; }
        $driver = (string)$this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        try {
            $this->pdo->beginTransaction();
            $lock = $driver === 'mysql' ? ' FOR UPDATE' : '';
            $select = $this->pdo->prepare('SELECT id,status FROM module_jira_jobs WHERE public_id=:pub AND organization_id=:org LIMIT 1' . $lock);
            $select->execute(['pub' => $publicId, 'org' => $this->organizationId]);
            $job = $select->fetch(PDO::FETCH_ASSOC);
            if (!is_array($job) || (string)$job['status'] !== $expectedStatus) {
                $this->pdo->rollBack();
                return null;
            }
            $reset = $this->pdo->prepare("UPDATE module_jira_job_items SET status='pending',error_code=NULL,error_message=NULL,attempts=0 WHERE job_id=:job_id AND status='failed' AND EXISTS (SELECT 1 FROM module_jira_jobs j WHERE j.id=module_jira_job_items.job_id AND j.organization_id=:org AND j.status=:expected)");
            $reset->execute(['job_id' => (int)$job['id'], 'org' => $this->organizationId, 'expected' => $expectedStatus]);
            $count = $reset->rowCount();
            $now = $this->now();
            $update = $this->pdo->prepare("UPDATE module_jira_jobs SET status='queued',lease_token=NULL,lease_until=NULL,started_at=NULL,finished_at=NULL,updated_at=:now WHERE id=:id AND organization_id=:org AND status=:expected");
            $update->execute(['now' => $now, 'id' => (int)$job['id'], 'org' => $this->organizationId, 'expected' => $expectedStatus]);
            if ($update->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }
            $this->pdo->commit();
            return $count;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw $e;
        }
    }

    public function updateJobProgress(string $publicId, string $step, float $percent, array $stats): void
    {
        $now = $this->now();
        $params = [
            'org' => $this->organizationId,
            'step' => $step,
            'percent' => $percent,
            'stats' => json_encode($stats, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
            'public_id' => $publicId,
        ];
        $where = ' WHERE public_id=:public_id AND organization_id=:org';
        if ($this->workerLeaseToken !== null) {
            $where .= " AND status='running' AND lease_token=:lease_token AND lease_until>:lease_now";
            $params['lease_token'] = $this->workerLeaseToken;
            $params['lease_now'] = $now;
        }
        $stmt = $this->pdo->prepare('UPDATE module_jira_jobs SET current_step=:step,progress_percent=:percent,progress_json=:stats,updated_at=:updated_at' . $where);
        $stmt->execute($params);
        if ($this->workerLeaseToken !== null && $stmt->rowCount() === 0 && !$this->workerLeaseMatches($publicId, $now)) {
            throw new \RuntimeException('JIRA_JOB_LEASE_LOST');
        }
    }

    private function workerLeaseMatches(string $publicId, string $now): bool
    {
        if ($this->workerLeaseToken === null || $this->organizationId < 1) { return false; }
        $stmt = $this->pdo->prepare("SELECT id FROM module_jira_jobs WHERE public_id=:pub AND organization_id=:org AND status='running' AND lease_token=:token AND lease_until>:unexpired LIMIT 1");
        $stmt->execute(['pub' => $publicId, 'org' => $this->organizationId, 'token' => $this->workerLeaseToken, 'unexpired' => $now]);
        return $stmt->fetchColumn() !== false;
    }

    public function resetFailedItems(string $publicId): int
    {
        $stmt = $this->pdo->prepare("UPDATE module_jira_job_items SET status = 'pending', error_code = NULL, error_message = NULL, attempts = 0 WHERE job_id = (SELECT id FROM module_jira_jobs WHERE public_id = :public_id AND organization_id=:org) AND status = 'failed'");
        $stmt->execute(['public_id' => $publicId, 'org' => $this->organizationId]);
        return $stmt->rowCount();
    }

    // ── Job Items ──

    public function listJobItems(string $jobPublicId, string $sourceType = '', string $status = '', int $limit = 50, int $page = 1): array
    {
        $where = ['i.job_id = (SELECT id FROM module_jira_jobs WHERE public_id = :job_pub AND organization_id=:org)'];
        $params = ['job_pub' => $jobPublicId, 'org' => $this->organizationId];
        if ($sourceType !== '') {
            $where[] = 'i.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }
        if ($status !== '') {
            $where[] = 'i.status = :status';
            $params['status'] = $status;
        }
        $offset = max(0, ($page - 1) * $limit);
        $stmt = $this->pdo->prepare('SELECT i.id, i.job_id, i.source_type, i.source_id, i.source_key, i.source_parent_id, i.target_type, i.target_public_id, i.status, i.checksum, i.source_updated_at, i.error_code, i.error_message, i.attempts, i.payload_json, i.created_at, i.updated_at FROM module_jira_job_items i WHERE ' . implode(' AND ', $where) . ' ORDER BY i.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertJobItem(int $jobId, string $sourceType, string $sourceId, array $data): void
    { if(!$this->jobInWorkspace($jobId))throw new \RuntimeException('JIRA_JOB_NOT_FOUND');
        $now = $this->now();
        $existing = $this->findJobItem($jobId, $sourceType, $sourceId);
        if ($existing) {
            $sets = ['updated_at = :updated_at'];
            $params = ['id' => $existing['id'], 'updated_at' => $now];
            foreach (['source_key', 'source_parent_id', 'target_type', 'target_public_id', 'status', 'checksum', 'source_updated_at', 'error_code', 'error_message', 'attempts', 'payload_json'] as $field) {
                if (array_key_exists($field, $data)) {
                    $sets[] = "$field = :$field";
                    $params[$field] = $data[$field];
                }
            }
            $this->pdo->prepare('UPDATE module_jira_job_items SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO module_jira_job_items (job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at) VALUES (:job_id, :source_type, :source_id, :source_key, :source_parent_id, :target_type, :target_public_id, :status, :checksum, :source_updated_at, :error_code, :error_message, :attempts, :payload_json, :created_at, :updated_at)');
            $stmt->execute([
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
    { if(!$this->jobInWorkspace($jobId))return null;
        $stmt = $this->pdo->prepare('SELECT id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at FROM module_jira_job_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id AND EXISTS (SELECT 1 FROM module_jira_jobs j WHERE j.id=module_jira_job_items.job_id AND j.organization_id=:org) LIMIT 1');
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'org'=>$this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function findJobItemsByStatus(int $jobId, string $status, int $limit = 500): array
    { if(!$this->jobInWorkspace($jobId))return[];
        $stmt = $this->pdo->prepare('SELECT id, job_id, source_type, source_id, source_key, source_parent_id, target_type, target_public_id, status, checksum, source_updated_at, error_code, error_message, attempts, payload_json, created_at, updated_at FROM module_jira_job_items WHERE job_id = :job_id AND status = :status AND EXISTS (SELECT 1 FROM module_jira_jobs j WHERE j.id=module_jira_job_items.job_id AND j.organization_id=:org) ORDER BY id ASC LIMIT ' . $limit);
        $stmt->execute(['job_id' => $jobId, 'status' => $status, 'org'=>$this->organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countJobItemsByStatus(int $jobId): array
    { if(!$this->jobInWorkspace($jobId))return[];
        $stmt = $this->pdo->prepare("SELECT status, COUNT(*) AS cnt FROM module_jira_job_items WHERE job_id = :job_id AND EXISTS (SELECT 1 FROM module_jira_jobs j WHERE j.id=module_jira_job_items.job_id AND j.organization_id=:org) GROUP BY status");
        $stmt->execute(['job_id' => $jobId, 'org'=>$this->organizationId]);
        $result = ['pending' => 0, 'processing' => 0, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'unsupported' => 0, 'unresolved' => 0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $result[(string)$row['status']] = (int)$row['cnt'];
        }
        return $result;
    }

    public function findTargetForSource(int $jobId, string $sourceType, string $sourceId): ?string
    { if(!$this->jobInWorkspace($jobId))return null;
        $stmt = $this->pdo->prepare("SELECT target_public_id FROM module_jira_job_items WHERE job_id = :job_id AND source_type = :source_type AND source_id = :source_id AND status IN ('imported', 'updated') AND EXISTS (SELECT 1 FROM module_jira_jobs j WHERE j.id=module_jira_job_items.job_id AND j.organization_id=:org) LIMIT 1");
        $stmt->execute(['job_id' => $jobId, 'source_type' => $sourceType, 'source_id' => $sourceId, 'org'=>$this->organizationId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (string)$val : null;
    }

    // ── Job Logs ──

    public function addJobLog(string $jobPublicId, string $level, ?string $step, string $message, array $context = []): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_jira_job_logs (job_id, level, step, message, context_json, created_at) VALUES ((SELECT id FROM module_jira_jobs WHERE public_id = :pub AND organization_id=:org), :level, :step, :message, :context, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'org' => $this->organizationId,
            'level' => $level,
            'step' => $step,
            'message' => mb_substr($message, 0, 2000),
            'context' => $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
        ]);
    }

    public function listJobLogs(string $jobPublicId, string $level = '', string $step = '', int $limit = 50, int $page = 1): array
    {
        $where = ['l.job_id = (SELECT id FROM module_jira_jobs WHERE public_id = :job_pub AND organization_id=:org)'];
        $params = ['job_pub' => $jobPublicId, 'org' => $this->organizationId];
        if ($level !== '') {
            $where[] = 'l.level = :level';
            $params['level'] = $level;
        }
        if ($step !== '') {
            $where[] = 'l.step = :step';
            $params['step'] = $step;
        }
        $offset = max(0, ($page - 1) * $limit);
        $stmt = $this->pdo->prepare('SELECT l.id, l.job_id, l.level, l.step, l.message, l.context_json, l.created_at FROM module_jira_job_logs l WHERE ' . implode(' AND ', $where) . ' ORDER BY l.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Report ──

    public function getJobReport(string $jobPublicId): array
    {
        $stmt = $this->pdo->prepare('SELECT j.id, j.public_id, j.organization_id, j.connection_id, j.status, j.mode, j.source_scope_json, j.target_options_json, j.current_step, j.progress_percent, j.progress_json, j.created_by_user_id, j.started_at, j.finished_at, j.created_at, j.updated_at, j.summary_json FROM module_jira_jobs j WHERE j.public_id = :pub AND j.organization_id=:org LIMIT 1');
        $stmt->execute(['pub' => $jobPublicId, 'org' => $this->organizationId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return [];
        }

        $itemCounts = $this->countJobItemsByStatus((int)$job['id']);

        $unresolved = $this->pdo->prepare('SELECT COUNT(*) FROM module_jira_unresolved_entities WHERE job_id = :job_id');
        $unresolved->execute(['job_id' => (int)$job['id']]);

        $unsupported = $this->pdo->prepare('SELECT COUNT(*) FROM module_jira_unsupported_fields WHERE job_id = :job_id');
        $unsupported->execute(['job_id' => (int)$job['id']]);

        $progress = json_decode((string)($job['progress_json'] ?? '{}'), true) ?? [];

        return [
            'job_public_id' => $jobPublicId,
            'mode' => $job['mode'],
            'status' => $job['status'],
            'current_step' => $job['current_step'],
            'progress_percent' => (float)($job['progress_percent'] ?? 0),
            'items' => $itemCounts,
            'unresolved_count' => (int)$unresolved->fetchColumn(),
            'unsupported_count' => (int)$unsupported->fetchColumn(),
            'progress' => $progress,
            'started_at' => $job['started_at'],
            'finished_at' => $job['finished_at'],
            'created_at' => $job['created_at'],
        ];
    }

    // ── Mappings ──

    public function listMappings(int $connectionId): array
    { if(!$this->connectionInWorkspace($connectionId))return[];
        $stmt = $this->pdo->prepare('SELECT m.id, m.public_id, m.connection_id, m.jira_subject_type, m.jira_subject_id, m.jira_subject_name, m.status, m.crm_subject_type, m.crm_subject_public_id, m.created_at, m.updated_at FROM module_jira_identity_mappings m JOIN module_jira_connections c ON c.id=m.connection_id AND c.organization_id=:org WHERE m.connection_id = :conn_id ORDER BY m.jira_subject_name ASC');
        $stmt->execute(['conn_id' => $connectionId, 'org' => $this->organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function upsertMapping(int $connectionId, string $subjectType, string $subjectId, string $subjectName): void
    { if(!$this->connectionInWorkspace($connectionId))return;
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_jira_identity_mappings (connection_id, jira_subject_type, jira_subject_id, jira_subject_name, status, created_at, updated_at) SELECT c.id, :type, :sid, :name, :status, :created_at, :updated_at FROM module_jira_connections c WHERE c.id=:conn_id AND c.organization_id=:org ON DUPLICATE KEY UPDATE jira_subject_name = VALUES(jira_subject_name), updated_at = VALUES(updated_at)');
        $stmt->execute([
            'conn_id' => $connectionId,
            'org' => $this->organizationId,
            'type' => $subjectType,
            'sid' => $subjectId,
            'name' => $subjectName,
            'status' => 'unresolved',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function updateMapping(string $publicId, ?string $crmSubjectType, ?string $crmSubjectPublicId, string $status): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('UPDATE module_jira_identity_mappings SET crm_subject_type = :stype, crm_subject_public_id = :spub, status = :status, updated_at = :updated_at WHERE public_id = :public_id AND connection_id IN (SELECT id FROM module_jira_connections WHERE organization_id=:org)');
        $stmt->execute([
            'stype' => $crmSubjectType,
            'spub' => $crmSubjectPublicId,
            'status' => $status,
            'updated_at' => $now,
            'public_id' => $publicId,
            'org' => $this->organizationId,
        ]);
    }

    // ── Unresolved Entities ──

    public function addUnresolvedEntity(string $jobPublicId, string $sourceType, string $sourceId, string $reasonCode, string $reasonText, array $payload = []): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_jira_unresolved_entities (job_id, source_type, source_id, reason_code, reason_text, payload_json, created_at) VALUES ((SELECT id FROM module_jira_jobs WHERE public_id = :pub AND organization_id=:org), :source_type, :source_id, :reason_code, :reason_text, :payload, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'org' => $this->organizationId,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'reason_code' => $reasonCode,
            'reason_text' => $reasonText,
            'payload' => $payload !== [] ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            'created_at' => $now,
        ]);
    }

    public function listUnresolvedEntities(string $jobPublicId): array
    {
        if ($jobPublicId === '') {
            return [];
        }
        $stmt = $this->pdo->prepare('SELECT id, job_id, source_type, source_id, reason_code, reason_text, payload_json, created_at FROM module_jira_unresolved_entities WHERE job_id = (SELECT id FROM module_jira_jobs WHERE public_id = :pub AND organization_id=:org) ORDER BY created_at DESC');
        $stmt->execute(['pub' => $jobPublicId, 'org' => $this->organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ── Unsupported Fields ──

    public function addUnsupportedField(string $jobPublicId, ?string $issueId, string $fieldId, ?string $fieldName, string $handling, array $schema = [], ?string $sample = null): void
    {
        $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_jira_unsupported_fields (job_id, issue_id, field_id, field_name, field_schema_json, handling, sample_json, created_at) VALUES ((SELECT id FROM module_jira_jobs WHERE public_id = :pub AND organization_id=:org), :issue_id, :field_id, :field_name, :schema, :handling, :sample, :created_at)');
        $stmt->execute([
            'pub' => $jobPublicId,
            'org' => $this->organizationId,
            'issue_id' => $issueId,
            'field_id' => $fieldId,
            'field_name' => $fieldName,
            'schema' => $schema !== [] ? json_encode($schema, JSON_UNESCAPED_UNICODE) : null,
            'handling' => $handling,
            'sample' => $sample,
            'created_at' => $now,
        ]);
    }

    // ── Rate Limits ──

    public function getRateLimit(int $connectionId): ?array
    { if(!$this->connectionInWorkspace($connectionId))return null;
        $stmt = $this->pdo->prepare('SELECT r.id, r.connection_id, r.requests_made, r.window_started_at, r.retry_after_until, r.updated_at FROM module_jira_rate_limits r JOIN module_jira_connections c ON c.id=r.connection_id AND c.organization_id=:org WHERE r.connection_id = :conn_id LIMIT 1');
        $stmt->execute(['conn_id' => $connectionId, 'org' => $this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function initRateLimit(int $connectionId): void
    { if(!$this->connectionInWorkspace($connectionId))return;
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('INSERT IGNORE INTO module_jira_rate_limits (connection_id, requests_made, window_started_at, updated_at) SELECT c.id,0,:now,:now FROM module_jira_connections c WHERE c.id=:conn_id AND c.organization_id=:org')
            ->execute(['conn_id' => $connectionId, 'now' => $now, 'org'=>$this->organizationId]);
    }

    public function updateRateLimitAfterRequest(int $connectionId, bool $reset, ?string $retryAfterUntil = null): void
    { if(!$this->connectionInWorkspace($connectionId))return;
        $now = gmdate('Y-m-d H:i:s');
        if ($reset) {
            $stmt = $this->pdo->prepare('UPDATE module_jira_rate_limits SET requests_made = 1, window_started_at = :now, retry_after_until = :retry, updated_at = :now WHERE connection_id = :conn_id AND connection_id IN (SELECT id FROM module_jira_connections WHERE organization_id=:org)');
        } else {
            $stmt = $this->pdo->prepare('UPDATE module_jira_rate_limits SET requests_made = requests_made + 1, retry_after_until = COALESCE(:retry, retry_after_until), updated_at = :now WHERE connection_id = :conn_id AND connection_id IN (SELECT id FROM module_jira_connections WHERE organization_id=:org)');
        }
        $stmt->execute(['conn_id' => $connectionId, 'now' => $now, 'retry' => $retryAfterUntil, 'org'=>$this->organizationId]);
    }

    public function resetRateLimit(int $connectionId): void
    { if(!$this->connectionInWorkspace($connectionId))return;
        $now = gmdate('Y-m-d H:i:s');
        $this->pdo->prepare('UPDATE module_jira_rate_limits SET requests_made = 0, window_started_at = :now, retry_after_until = NULL, updated_at = :now WHERE connection_id = :conn_id AND connection_id IN (SELECT id FROM module_jira_connections WHERE organization_id=:org)')
            ->execute(['conn_id' => $connectionId, 'now' => $now, 'org'=>$this->organizationId]);
    }

    // ── Settings ──

    public function getModuleSettings(): array
    {
        $defaults = [
            'max_attachment_size_mb' => 20,
            'allowed_jira_hosts' => ['*.atlassian.net'],
            'custom_domain_allowlist' => [],
            'default_batch_size' => 100,
            'request_timeout_seconds' => 60,
            'max_retries' => 3,
            'jql_default_max_results' => 100,
        ];
        $stmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM module_jira_settings WHERE module_name = 'crm.jira-migration'");
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
        $stmt = $this->pdo->prepare("INSERT INTO module_jira_settings (module_name, setting_key, setting_value, updated_at) VALUES ('crm.jira-migration', :skey, :svalue, :updated_at) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)");
        $stmt->execute([
            'skey' => $key,
            'svalue' => $jsonValue,
            'updated_at' => $now,
        ]);
    }

    // ── Rate limit helpers ──

    public function getRateLimitState(int $connectionId): ?array
    { if(!$this->connectionInWorkspace($connectionId))return null;
        $stmt = $this->pdo->prepare('SELECT r.id, r.connection_id, r.requests_made, r.window_started_at, r.retry_after_until, r.updated_at FROM module_jira_rate_limits r JOIN module_jira_connections c ON c.id=r.connection_id AND c.organization_id=:org WHERE r.connection_id = :conn_id LIMIT 1');
        $stmt->execute(['conn_id' => $connectionId, 'org' => $this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }
}
