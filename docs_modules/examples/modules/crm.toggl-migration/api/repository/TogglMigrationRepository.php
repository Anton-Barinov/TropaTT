<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Repository;

use PDO;

final class TogglMigrationRepository
{
    private int $organizationId = 0;
    private string $workerLeaseToken = '';

    public function __construct(private readonly PDO $pdo, int $organizationId = 0)
    { $this->organizationId = max(0, $organizationId); }

    public function setOrganizationId(int $organizationId): void
    { if ($organizationId < 1) throw new \RuntimeException('TOGGL_WORKSPACE_REQUIRED'); $this->organizationId = $organizationId; }

    private function requireWorkspace(): void
    { if ($this->organizationId < 1) throw new \RuntimeException('TOGGL_WORKSPACE_REQUIRED'); }

    private function connectionInWorkspace(int $connectionId): bool
    { if($connectionId<1||$this->organizationId<1)return false;$s=$this->pdo->prepare('SELECT id FROM module_toggl_connections WHERE id=:id AND organization_id=:organization LIMIT 1');$s->execute(['id'=>$connectionId,'organization'=>$this->organizationId]);return $s->fetchColumn()!==false; }

    private function jobInWorkspace(int $jobId): bool
    { if ($jobId < 1 || $this->organizationId < 1) return false; $s=$this->pdo->prepare('SELECT id FROM module_toggl_jobs WHERE id=:id AND organization_id=:organization'); $s->execute(['id'=>$jobId,'organization'=>$this->organizationId]); return $s->fetchColumn()!==false; }

    private function requireJobLease(int $jobId): void
    { if ($this->workerLeaseToken === '') return; $s=$this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE id=:id AND organization_id=:organization AND lease_token=:lease AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back','pausing','cancelling')"); $s->execute(['id'=>$jobId,'organization'=>$this->organizationId,'lease'=>$this->workerLeaseToken]); if($s->fetchColumn()===false) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST'); }

    private function forUpdate(): string { return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : ''; }

    private function id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function json(mixed $value): string { return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

    public function actor(int $id, ?int $organizationId = null): ?array
    {
        $organizationId = $organizationId ?? $this->organizationId;
        if ($id < 1 || $organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT id, public_id, login, full_name, is_root, is_active FROM users WHERE id=:id AND is_active=1 LIMIT 1');
        $stmt->execute(['id'=>$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC); if(!is_array($row)) return null;
        if (empty($row['is_root'])) { $membership=$this->pdo->prepare('SELECT 1 FROM organization_memberships WHERE user_id=:user AND organization_id=:organization LIMIT 1'); $membership->execute(['user'=>$id,'organization'=>$organizationId]); if($membership->fetchColumn()===false)return null; }
        $row['organization_id']=$organizationId; return $row;
    }

    public function listConnections(int $actorId, bool $manager): array
    {
        $this->requireWorkspace(); $sql = 'SELECT id,public_id,organization_id,name,auth_type,workspace_gid,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_toggl_connections WHERE organization_id=:organization';
        $params = ['organization'=>$this->organizationId];
        if (!$manager) { $sql .= ' AND created_by_user_id=:owner'; $params['owner'] = $actorId; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        $this->requireWorkspace(); $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_connections WHERE public_id=:id AND organization_id=:organization LIMIT 1');
        $stmt->execute(['id' => $publicId,'organization'=>$this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }

    public function getConnectionById(int $id): ?array
    {
        $this->requireWorkspace(); $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_connections WHERE id=:id AND organization_id=:organization LIMIT 1');
        $stmt->execute(['id' => $id,'organization'=>$this->organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }

    public function createConnection(array $data): array
    {
        $this->requireWorkspace(); if($this->actor((int)($data['created_by_user_id']??0))===null) throw new \RuntimeException('TOGGL_WORKSPACE_MEMBERSHIP_REQUIRED'); $publicId = $this->id('tgc'); $now = $this->now();
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_connections (organization_id,public_id,name,auth_type,access_token_encrypted,refresh_token_encrypted,client_id_encrypted,client_secret_encrypted,status,created_by_user_id,created_at,updated_at) VALUES (:organization,:public_id,:name,:auth,:access,:refresh,:client,:secret,\'draft\',:owner,:created,:updated)');
        $stmt->execute(['organization'=>$this->organizationId,'public_id' => $publicId, 'name' => $data['name'], 'auth' => $data['auth_type'] ?? 'pat', 'access' => $data['access_token_encrypted'] ?? null, 'refresh' => $data['refresh_token_encrypted'] ?? null, 'client' => $data['client_id_encrypted'] ?? null, 'secret' => $data['client_secret_encrypted'] ?? null, 'owner' => $data['created_by_user_id'], 'created' => $now, 'updated' => $now]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function updateConnection(string $publicId, array $data): void
    {
        $allowed = ['name','access_token_encrypted','refresh_token_encrypted','client_id_encrypted','client_secret_encrypted','workspace_gid'];
        $sets = []; $params = ['id' => $publicId, 'updated' => $this->now()];
        foreach ($allowed as $field) if (array_key_exists($field, $data)) { $sets[] = $field . '=:' . $field; $params[$field] = $data[$field]; }
        if ($sets === []) return;
        $sets[] = 'updated_at=:updated';
        $this->pdo->prepare('UPDATE module_toggl_connections SET ' . implode(',', $sets) . ' WHERE public_id=:id AND organization_id=:organization')->execute($params + ['organization'=>$this->organizationId]);
    }

    public function updateConnectionCheck(string $publicId, bool $ok, string $error = '', ?string $workspaceGid = null): void
    {
        $sets = ['status=:status','last_checked_at=:checked','last_error=:error','updated_at=:updated'];
        $params = ['status' => $ok ? 'active' : 'failed', 'checked' => $this->now(), 'error' => $error !== '' ? mb_substr($error, 0, 1000) : null, 'updated' => $this->now(), 'id' => $publicId,'organization'=>$this->organizationId];
        if ($workspaceGid !== null) { $sets[] = 'workspace_gid=:workspace'; $params['workspace'] = $workspaceGid; }
        $this->pdo->prepare('UPDATE module_toggl_connections SET ' . implode(',', $sets) . ' WHERE public_id=:id AND organization_id=:organization')->execute($params + ['organization'=>$this->organizationId]);
    }

    public function deleteConnection(int $connectionId): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->requireWorkspace(); $lock = $this->pdo->prepare('SELECT id,status FROM module_toggl_connections WHERE id=:id AND organization_id=:organization LIMIT 1' . $this->forUpdate());
            $lock->execute(['id' => $connectionId,'organization'=>$this->organizationId]);
            if (!is_array($lock->fetch(PDO::FETCH_ASSOC))) throw new \RuntimeException('TOGGL_CONNECTION_NOT_FOUND');
            // Hold the connection row in a deleting state while checking and
            // removing its metadata. Job creation and worker claiming exclude
            // this state, avoiding a check-then-delete race.
            $this->pdo->prepare("UPDATE module_toggl_connections SET status='deleting',updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:organization")->execute(['id'=>$connectionId,'organization'=>$this->organizationId]);
            $running = $this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE connection_id=:id AND organization_id=:organization AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1");
            $running->execute(['id' => $connectionId,'organization'=>$this->organizationId]);
            if ($running->fetchColumn() !== false) throw new \RuntimeException('TOGGL_CONNECTION_HAS_RUNNING_JOBS');
            if ($this->hasImportedTargets($connectionId)) throw new \RuntimeException('TOGGL_CONNECTION_HAS_IMPORTED_TARGETS');
            foreach (['module_toggl_unresolved_entities' => ['organization_id=:organization AND job_id IN (SELECT id FROM module_toggl_jobs WHERE connection_id=:id AND organization_id=:job_organization)', true], 'module_toggl_job_logs' => ['organization_id=:organization AND job_id IN (SELECT id FROM module_toggl_jobs WHERE connection_id=:id AND organization_id=:job_organization)', true], 'module_toggl_job_items' => ['organization_id=:organization AND job_id IN (SELECT id FROM module_toggl_jobs WHERE connection_id=:id AND organization_id=:job_organization)', true], 'module_toggl_jobs' => ['connection_id=:id AND organization_id=:organization', false], 'module_toggl_source_mappings' => ['connection_id=:id AND organization_id=:organization', false], 'module_toggl_user_mappings' => ['connection_id=:id AND organization_id=:organization', false], 'module_toggl_rate_limits' => ['connection_id=:id AND organization_id=:organization', false]] as $table => [$where, $throughJobs]) { $params=['id'=>$connectionId,'organization'=>$this->organizationId]; if($throughJobs)$params['job_organization']=$this->organizationId; $this->pdo->prepare('DELETE FROM ' . $table . ' WHERE ' . $where)->execute($params); }
            $this->pdo->prepare('DELETE FROM module_toggl_connections WHERE id=:id AND organization_id=:organization')->execute(['id' => $connectionId,'organization'=>$this->organizationId]);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    public function hasRunningJobs(int $connectionId): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE connection_id=:id AND organization_id=:organization AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1"); $stmt->execute(['id' => $connectionId,'organization'=>$this->organizationId]); return $stmt->fetchColumn() !== false;
    }

    /**
     * A connection with imported targets must not be deleted before rollback:
     * deleting its mappings would make those CRM records impossible to trace
     * or remove through this module.
     */
    public function hasImportedTargets(int $connectionId): bool
    {
        $lastId = 0;
        do {
            $stmt = $this->pdo->prepare("SELECT i.id,i.target_type,i.target_public_id FROM module_toggl_job_items i INNER JOIN module_toggl_jobs j ON j.id=i.job_id WHERE j.connection_id=:connection AND j.organization_id=:organization AND i.organization_id=:organization AND i.created_by_job=1 AND i.target_public_id IS NOT NULL AND i.status NOT IN ('rolled_back') AND i.id>:last_id ORDER BY i.id ASC LIMIT 500");
            $stmt->execute(['connection' => $connectionId, 'organization'=>$this->organizationId, 'last_id' => $lastId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($items as $item) {
                $type = (string)($item['target_type'] ?? '');
                $target = (string)($item['target_public_id'] ?? '');
                // A shared target may have been removed by the job that still
                // owned the last live reference; stale metadata must not make
                // the connection undeletable forever. Unknown types remain
                // fail-closed rather than being silently discarded.
                $knownType = in_array($type, ['client', 'project', 'task', 'tag', 'worklog'], true);
                if ($target !== '' && (!$knownType || $this->targetExists($type, $target))) return true;
                $lastId = (int)$item['id'];
            }
            if (count($items) < 500) return false;
        } while (true);
    }

    public function rateState(int $connectionId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_rate_limits WHERE connection_id=:id AND organization_id=:organization LIMIT 1'); $stmt->execute(['id' => $connectionId,'organization'=>$this->organizationId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null;
    }

    public function recordRequest(int $connectionId, int $status, array $headers): void
    {
        $state = $this->rateState($connectionId) ?: [];
        $started = (string)($state['window_started_at'] ?? '');
        if ($started === '' || time() - (strtotime($started) ?: time()) >= 60) { $started = $this->now(); $count = 0; } else $count = (int)($state['requests_made'] ?? 0);
        $this->upsertRateState($connectionId, ['requests_made' => $count + 1, 'window_started_at' => $started, 'retry_after_until' => $state['retry_after_until'] ?? null, 'last_http_status' => $status, 'last_request_at' => $this->now()]);
    }

    public function recordRetryAfter(int $connectionId, int $seconds): void
    {
        $state = $this->rateState($connectionId) ?: [];
        $this->upsertRateState($connectionId, array_merge($state, ['retry_after_until' => gmdate('Y-m-d H:i:s', time() + max(1, $seconds))]));
    }

    private function upsertRateState(int $connectionId, array $data): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_rate_limits (organization_id,connection_id,requests_made,window_started_at,retry_after_until,last_http_status,last_request_at,updated_at) VALUES (:organization,:id,:requests,:window,:retry,:status,:last_request,:updated) ON DUPLICATE KEY UPDATE requests_made=VALUES(requests_made),window_started_at=VALUES(window_started_at),retry_after_until=VALUES(retry_after_until),last_http_status=VALUES(last_http_status),last_request_at=VALUES(last_request_at),updated_at=VALUES(updated_at)');
        $stmt->execute(['organization'=>$this->organizationId,'id' => $connectionId, 'requests' => (int)($data['requests_made'] ?? 0), 'window' => $data['window_started_at'] ?? $this->now(), 'retry' => $data['retry_after_until'] ?? null, 'status' => $data['last_http_status'] ?? null, 'last_request' => $data['last_request_at'] ?? null, 'updated' => $this->now()]);
    }

    public function createJob(array $data): array
    {
        $this->requireWorkspace();
        $connection=$this->getConnectionById((int)($data['connection_id']??0));
        if(!$connection || $this->actor((int)($data['created_by_user_id']??0))===null) throw new \RuntimeException('TOGGL_WORKSPACE_ACCESS_REQUIRED');
        $publicId=$this->id('tgj');$now=$this->now();
        $this->pdo->beginTransaction();
        try {
            $lock=$this->pdo->prepare('SELECT id,status FROM module_toggl_connections WHERE id=:id AND organization_id=:organization LIMIT 1' . $this->forUpdate());
            $lock->execute(['id'=>$connection['id'],'organization'=>$this->organizationId]);$row=$lock->fetch(PDO::FETCH_ASSOC);
            if(!is_array($row))throw new \RuntimeException('TOGGL_CONNECTION_NOT_FOUND');
            if((string)$row['status']==='deleting')throw new \RuntimeException('TOGGL_CONNECTION_DELETE_IN_PROGRESS');
            $stmt=$this->pdo->prepare("INSERT INTO module_toggl_jobs (organization_id,public_id,connection_id,workspace_gid,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) VALUES (:organization,:public_id,:connection,:workspace,:mode,'draft',:scope,:options,:owner,:created,:updated)");
            $stmt->execute(['organization'=>$this->organizationId,'public_id'=>$publicId,'connection'=>$connection['id'],'workspace'=>$data['workspace_gid'],'mode'=>$data['mode']??'import','scope'=>$this->json($data['source_scope']??[]),'options'=>$this->json($data['target_options']??[]),'owner'=>$data['created_by_user_id'],'created'=>$now,'updated'=>$now]);
            $this->pdo->commit();
        } catch(\Throwable $e) { if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e; }
        return $this->getJob($publicId)??['public_id'=>$publicId];
    }

    private function decodeJob(array $row): array
    {
        foreach (['source_scope_json' => 'source_scope','target_options_json' => 'target_options','progress_json' => 'progress','summary_json' => 'summary'] as $from => $to) if (array_key_exists($from, $row)) $row[$to] = json_decode((string)$row[$from], true) ?: [];
        return $row;
    }

    public function getJob(string $publicId): ?array
    {
        if($this->organizationId<1)return null;
        $stmt=$this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_toggl_jobs j JOIN module_toggl_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.public_id=:id AND j.organization_id=:organization LIMIT 1');
        $stmt->execute(['id'=>$publicId,'organization'=>$this->organizationId]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return is_array($row)?$this->decodeJob($row):null;
    }

    public function listJobs(int $actorId, bool $manager): array
    {
        $this->requireWorkspace();$sql='SELECT j.*,c.name AS connection_name FROM module_toggl_jobs j JOIN module_toggl_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.organization_id=:organization';$params=['organization'=>$this->organizationId];
        if(!$manager){$sql.=' AND j.created_by_user_id=:owner';$params['owner']=$actorId;}
        $sql.=' ORDER BY j.created_at DESC';$stmt=$this->pdo->prepare($sql);$stmt->execute($params);return array_map([$this,'decodeJob'],$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]);
    }

    public function updateJobStatus(string $publicId, string $status, ?string $leaseToken=null, ?string $expectedStatus=null): bool
    {
        $this->requireWorkspace();$sets=['status=:status','updated_at=UTC_TIMESTAMP()'];$params=['id'=>$publicId,'organization'=>$this->organizationId,'status'=>$status];
        if(in_array($status,['queued','running'],true))$sets[]='started_at=COALESCE(started_at,UTC_TIMESTAMP())';
        if(in_array($status,['completed','completed_with_warnings','failed','cancelled','rolled_back','rolled_back_with_warnings','rollback_failed'],true))$sets[]='finished_at=UTC_TIMESTAMP()';
        $where=' WHERE public_id=:id AND organization_id=:organization';if($expectedStatus!==null){$where.=' AND status=:expected';$params['expected']=$expectedStatus;}
        if($leaseToken!==null){$where.=" AND lease_token=:lease AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back','pausing','cancelling')";$params['lease']=$leaseToken;}
        $stmt=$this->pdo->prepare('UPDATE module_toggl_jobs SET '.implode(',',$sets).$where);$stmt->execute($params);
        if($leaseToken!==null&&$stmt->rowCount()!==1)throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');return $stmt->rowCount()===1;
    }

    public function requestStatus(string $publicId,string $expectedStatus,string $status): bool
    {
        $this->requireWorkspace();$s=$this->pdo->prepare('UPDATE module_toggl_jobs SET status=:status,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:organization AND status=:expected AND (lease_token IS NULL OR lease_until<UTC_TIMESTAMP())');
        $s->execute(['id'=>$publicId,'organization'=>$this->organizationId,'expected'=>$expectedStatus,'status'=>$status]);return $s->rowCount()===1;
    }

    public function beginRollback(string $publicId, int $leaseSeconds = 900): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $this->requireWorkspace(); $stmt=$this->pdo->prepare('SELECT id,status,lease_until,last_source_cursor FROM module_toggl_jobs WHERE public_id=:id AND organization_id=:organization LIMIT 1' . $this->forUpdate());
            $stmt->execute(['id' => $publicId,'organization'=>$this->organizationId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) { $this->pdo->commit(); return null; }
            $status=(string)$row['status'];
            $terminal=in_array($status,['completed','completed_with_warnings','failed','cancelled','rolled_back_with_warnings','rollback_failed'],true);
            $leaseUntil = (string)($row['lease_until'] ?? '');
            $expiredRollback = false;
            if ($status === 'rolling_back' && $leaseUntil !== '') {
                try { $expiredRollback = new \DateTimeImmutable($leaseUntil, new \DateTimeZone('UTC')) < new \DateTimeImmutable('now', new \DateTimeZone('UTC')); } catch (\Throwable) { $expiredRollback = true; }
            }
            if (!$terminal && !$expiredRollback) { $this->pdo->commit(); return null; }
            $token=bin2hex(random_bytes(16));$until=gmdate('Y-m-d H:i:s',time()+max(60,$leaseSeconds));
            $cursor=$expiredRollback?(string)($row['last_source_cursor']??''):json_encode(['phase'=>'rollback','before_id'=>PHP_INT_MAX],JSON_UNESCAPED_UNICODE);
            $update=$this->pdo->prepare("UPDATE module_toggl_jobs SET status='rolling_back',lease_token=:token,lease_until=:until,last_source_cursor=:cursor,updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:organization AND status=:expected");
            $update->execute(['token'=>$token,'until'=>$until,'cursor'=>$cursor,'id'=>(int)$row['id'],'organization'=>$this->organizationId,'expected'=>$status]);
            if ($update->rowCount() !== 1) { $this->pdo->rollBack(); return null; }
            $this->pdo->commit();
            $this->workerLeaseToken=$token;
            return $this->getJob($publicId);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateProgress(string $publicId, string $step, float $percent, array $progress, ?string $leaseToken = null): void
    {
        $this->requireWorkspace(); $params = ['step' => $step, 'percent' => max(0, min(100, $percent)), 'progress' => $this->json($progress), 'updated' => $this->now(), 'id' => $publicId,'organization'=>$this->organizationId]; $where = ' WHERE public_id=:id AND organization_id=:organization';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\',\'pausing\',\'cancelling\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET current_step=:step,progress_percent=:percent,progress_json=:progress,updated_at=:updated' . $where); $stmt->execute($params);
        if ($leaseToken !== null && !$this->ownsLease($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function updateSummary(string $publicId, array $summary, ?string $leaseToken = null): void
    {
        $this->requireWorkspace(); $params = ['summary' => $this->json($summary), 'updated' => $this->now(), 'id' => $publicId,'organization'=>$this->organizationId]; $where = ' WHERE public_id=:id AND organization_id=:organization';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\',\'pausing\',\'cancelling\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET summary_json=:summary,updated_at=:updated' . $where); $stmt->execute($params);
        if ($leaseToken !== null && !$this->ownsLease($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function claimNextJob(int $leaseSeconds=240): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $sql="SELECT j.id,j.organization_id FROM module_toggl_jobs j JOIN module_toggl_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id AND c.status<>'deleting' WHERE j.organization_id IS NOT NULL AND (j.status='queued' OR (j.status IN ('running','pausing','cancelling') AND (j.lease_until IS NULL OR j.lease_until<UTC_TIMESTAMP()))) AND (j.lease_token IS NULL OR j.lease_until<UTC_TIMESTAMP()) AND NOT EXISTS (SELECT 1 FROM module_toggl_jobs a WHERE a.connection_id=j.connection_id AND a.organization_id=j.organization_id AND (a.status='rolling_back' OR (a.status IN ('running','pausing','cancelling') AND a.lease_until>=UTC_TIMESTAMP()))) ORDER BY j.created_at ASC LIMIT 1";
            if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql')$sql.=' FOR UPDATE';
            $row=$this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);if(!is_array($row)){$this->pdo->commit();return null;}
            $org=(int)$row['organization_id'];$id=(int)$row['id'];if($org<1||$id<1){$this->pdo->commit();return null;}
            $token=bin2hex(random_bytes(16));$until=gmdate('Y-m-d H:i:s',time()+max(60,$leaseSeconds));
            $claim=$this->pdo->prepare("UPDATE module_toggl_jobs SET status=CASE WHEN status IN ('pausing','cancelling') THEN status ELSE 'running' END,lease_token=:token,lease_until=:until,started_at=COALESCE(started_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:organization AND (status='queued' OR (status IN ('running','pausing','cancelling') AND (lease_until IS NULL OR lease_until<UTC_TIMESTAMP()))) AND (lease_token IS NULL OR lease_until<UTC_TIMESTAMP())");
            $claim->execute(['token'=>$token,'until'=>$until,'id'=>$id,'organization'=>$org]);if($claim->rowCount()!==1){$this->pdo->rollBack();return null;}
            $this->pdo->commit();$this->organizationId=$org;$this->workerLeaseToken=$token;return $this->getJobByNumericId($id);
        }catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function getJobByNumericId(int $id): ?array
    { if($this->organizationId<1)return null;$s=$this->pdo->prepare('SELECT public_id FROM module_toggl_jobs WHERE id=:id AND organization_id=:organization LIMIT 1');$s->execute(['id'=>$id,'organization'=>$this->organizationId]);$pub=$s->fetchColumn();return $pub===false?null:$this->getJob((string)$pub); }

    public function heartbeat(string $publicId,string $leaseToken,int $leaseSeconds=240): bool
    { if($this->organizationId<1||$leaseToken==='')return false;$s=$this->pdo->prepare("UPDATE module_toggl_jobs SET lease_until=:until,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:organization AND lease_token=:token AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back','pausing','cancelling')");$s->execute(['until'=>gmdate('Y-m-d H:i:s',time()+max(60,$leaseSeconds)),'id'=>$publicId,'organization'=>$this->organizationId,'token'=>$leaseToken]);return $this->ownsLease($publicId,$leaseToken); }

    public function ownsLease(string $publicId,string $leaseToken): bool
    { if($this->organizationId<1||$leaseToken==='')return false;$s=$this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE public_id=:id AND organization_id=:organization AND lease_token=:token AND status IN ('running','rolling_back','pausing','cancelling') AND lease_until>=UTC_TIMESTAMP() LIMIT 1");$s->execute(['id'=>$publicId,'organization'=>$this->organizationId,'token'=>$leaseToken]);return $s->fetchColumn()!==false; }

    private function leaseTokenMatches(string $publicId,string $leaseToken): bool
    { return $this->ownsLease($publicId,$leaseToken); }

    public function releaseLease(string $publicId,string $leaseToken): void
    { if($this->organizationId<1||$leaseToken==='')return;$this->pdo->prepare('UPDATE module_toggl_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:organization AND lease_token=:token')->execute(['id'=>$publicId,'organization'=>$this->organizationId,'token'=>$leaseToken]); }

    public function retryJob(string $publicId): ?int
    {
        $this->pdo->beginTransaction();
        try {
            $jobStmt=$this->pdo->prepare("SELECT id FROM module_toggl_jobs WHERE public_id=:id AND organization_id=:organization AND status IN ('completed_with_warnings','failed','cancelled') AND (lease_token IS NULL OR lease_until<UTC_TIMESTAMP()) LIMIT 1" . $this->forUpdate());
            $this->requireWorkspace(); $jobStmt->execute(['id'=>$publicId,'organization'=>$this->organizationId]);$job=$jobStmt->fetch(PDO::FETCH_ASSOC);
            if(!is_array($job)){$this->pdo->commit();return null;}
            $items=$this->pdo->prepare("UPDATE module_toggl_job_items SET status='pending',error_code=NULL,error_message=NULL,updated_at=UTC_TIMESTAMP() WHERE job_id=:job AND organization_id=:organization AND status='failed'");$items->execute(['job'=>(int)$job['id'],'organization'=>$this->organizationId]);$count=$items->rowCount();
            $this->pdo->prepare("UPDATE module_toggl_jobs SET status='queued',last_source_cursor=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:organization")->execute(['id'=>(int)$job['id'],'organization'=>$this->organizationId]);
            $this->pdo->commit();return $count;
        } catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function resetFailedItems(string $publicId): int
    { return $this->retryJob($publicId) ?? 0; }

    public function upsertItem(int $jobId, string $type, string $sourceId, array $data): void
    {
        $this->requireWorkspace(); if(!$this->jobInWorkspace($jobId))throw new \RuntimeException('TOGGL_JOB_NOT_FOUND'); $this->requireJobLease($jobId);
        $existing = $this->findItem($jobId, $type, $sourceId); $fields = ['source_parent_id','source_project_id','target_type','target_public_id','created_by_job','status','checksum','source_updated_at','attempts','error_code','error_message','payload_json'];
        if ($existing) {
            if (($data['status'] ?? null) === 'pending' && (string)($existing['checksum'] ?? '') === (string)($data['checksum'] ?? '') && in_array((string)$existing['status'], ['imported','updated','skipped'], true)) unset($data['status']);
            $sets = ['updated_at=:updated']; $params = ['id' => $existing['id'], 'organization'=>$this->organizationId, 'updated' => $this->now()];
            foreach ($fields as $field) if (array_key_exists($field, $data)) { $sets[] = $field . '=:' . $field; $params[$field] = $field === 'payload_json' && is_array($data[$field]) ? $this->json($data[$field]) : $data[$field]; }
            $this->pdo->prepare('UPDATE module_toggl_job_items SET ' . implode(',', $sets) . ' WHERE id=:id AND organization_id=:organization')->execute($params); return;
        }
        $stmt = $this->pdo->prepare('INSERT INTO module_toggl_job_items (organization_id,job_id,source_type,source_id,source_parent_id,source_project_id,target_type,target_public_id,created_by_job,status,checksum,source_updated_at,attempts,error_code,error_message,payload_json,created_at,updated_at) VALUES (:organization,:job,:type,:source,:parent,:project,:target_type,:target,:created_by,:status,:checksum,:source_updated,:attempts,:error_code,:error_message,:payload,:created,:updated)');
        $stmt->execute(['organization'=>$this->organizationId,'job' => $jobId, 'type' => $type, 'source' => $sourceId, 'parent' => $data['source_parent_id'] ?? null, 'project' => $data['source_project_id'] ?? null, 'target_type' => $data['target_type'] ?? null, 'target' => $data['target_public_id'] ?? null, 'created_by' => !empty($data['created_by_job']) ? 1 : 0, 'status' => $data['status'] ?? 'pending', 'checksum' => $data['checksum'] ?? null, 'source_updated' => $data['source_updated_at'] ?? null, 'attempts' => $data['attempts'] ?? 0, 'error_code' => $data['error_code'] ?? null, 'error_message' => $data['error_message'] ?? null, 'payload' => isset($data['payload_json']) ? (is_array($data['payload_json']) ? $this->json($data['payload_json']) : $data['payload_json']) : null, 'created' => $this->now(), 'updated' => $this->now()]);
    }

    public function findItem(int $jobId, string $type, string $sourceId): ?array
    { $stmt = $this->pdo->prepare('SELECT * FROM module_toggl_job_items WHERE job_id=:job AND organization_id=:organization AND source_type=:type AND source_id=:source LIMIT 1'); $stmt->execute(['organization'=>$this->organizationId,'job' => $jobId, 'type' => $type, 'source' => $sourceId]); $row = $stmt->fetch(PDO::FETCH_ASSOC); return is_array($row) ? $row : null; }

    public function items(int $jobId, ?string $status = null, int $limit = 5000): array
    { if(!$this->jobInWorkspace($jobId))return []; $sql = 'SELECT * FROM module_toggl_job_items WHERE job_id=:job AND organization_id=:organization'; $params = ['job' => $jobId,'organization'=>$this->organizationId]; if ($status !== null && $status !== '') { $sql .= ' AND status=:status'; $params['status'] = $status; } $sql .= ' ORDER BY id ASC LIMIT ' . max(1, min(10000, $limit)); $stmt = $this->pdo->prepare($sql); $stmt->execute($params); return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }

    /** @return array<int,array<string,mixed>> */
    public function importItemsBatch(int $jobId, int $priority = 0, int $lastId = 0, int $limit = 250): array
    {
        $limit = max(1, min(1000, $limit));
        $sql = "SELECT * FROM (SELECT i.*, CASE i.source_type WHEN 'client' THEN 10 WHEN 'project' THEN 20 WHEN 'tag' THEN 30 WHEN 'task' THEN 40 WHEN 'time_entry' THEN 50 ELSE 90 END AS import_priority FROM module_toggl_job_items i WHERE i.job_id=:job AND i.organization_id=:organization AND i.status='pending') AS ordered_items WHERE (ordered_items.import_priority>:priority OR (ordered_items.import_priority=:same_priority AND ordered_items.id>:last_id)) ORDER BY ordered_items.import_priority ASC, ordered_items.id ASC LIMIT {$limit}";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['job' => $jobId,'organization'=>$this->organizationId, 'priority' => $priority, 'same_priority' => $priority, 'last_id' => $lastId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function rollbackItemsBatch(int $jobId, int $beforeId, int $limit = 250): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM module_toggl_job_items WHERE job_id=:job AND organization_id=:organization AND created_by_job=1 AND target_public_id IS NOT NULL AND status NOT IN ('rolled_back','rollback_preserved_shared') AND id<:before_id ORDER BY id DESC LIMIT " . max(1, min(1000, $limit)));
        $stmt->execute(['job' => $jobId,'organization'=>$this->organizationId, 'before_id' => $beforeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Do not delete a target during rollback when another job still refers to
     * the same CRM record. This protects a later sync/import from losing its
     * shared target merely because an older job was rolled back.
     */
    public function targetReferencedByOtherJob(int $jobId, string $targetType, string $targetPublicId): bool
    {
        $stmt = $this->pdo->prepare("SELECT i.id FROM module_toggl_job_items i INNER JOIN module_toggl_jobs j ON j.id=i.job_id WHERE j.organization_id=:organization AND i.organization_id=:organization AND i.job_id<>:job AND i.target_type=:type AND i.target_public_id=:target AND i.status NOT IN ('rolled_back','rollback_preserved_shared') LIMIT 1");
        $stmt->execute(['organization'=>$this->organizationId,'job' => $jobId, 'type' => $targetType, 'target' => $targetPublicId]);
        return $stmt->fetchColumn() !== false;
    }

    public function updateCursor(string $publicId, string $cursor, ?string $leaseToken = null): void
    {
        $this->requireWorkspace(); $params = ['id' => $publicId,'organization'=>$this->organizationId, 'cursor' => $cursor];
        $where = ' WHERE public_id=:id AND organization_id=:organization';
        if ($leaseToken !== null) { $where .= ' AND lease_token=:lease AND lease_until >= UTC_TIMESTAMP() AND status IN (\'running\',\'rolling_back\',\'pausing\',\'cancelling\')'; $params['lease'] = $leaseToken; }
        $stmt = $this->pdo->prepare('UPDATE module_toggl_jobs SET last_source_cursor=:cursor,updated_at=UTC_TIMESTAMP()' . $where);
        $stmt->execute($params);
        if ($leaseToken !== null && !$this->ownsLease($publicId, $leaseToken)) throw new \RuntimeException('TOGGL_JOB_LEASE_LOST');
    }

    public function itemCounts(int $jobId): array
    { if(!$this->jobInWorkspace($jobId))return []; $stmt = $this->pdo->prepare('SELECT status,COUNT(*) AS count FROM module_toggl_job_items WHERE job_id=:job AND organization_id=:organization GROUP BY status'); $stmt->execute(['job' => $jobId,'organization'=>$this->organizationId]); $out = []; foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $out[(string)$row['status']]=(int)$row['count']; return $out; }

    public function itemCount(int $jobId): int
    { if(!$this->jobInWorkspace($jobId))return 0; $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM module_toggl_job_items WHERE job_id=:job AND organization_id=:organization'); $stmt->execute(['job' => $jobId,'organization'=>$this->organizationId]); return (int)$stmt->fetchColumn(); }

    public function findMapping(int $connectionId,string $workspace,string $type,string $sourceId): ?array
    { if(!$this->connectionInWorkspace($connectionId))return null;$s=$this->pdo->prepare('SELECT * FROM module_toggl_source_mappings WHERE organization_id=:organization AND connection_id=:connection AND workspace_gid=:workspace AND source_type=:type AND source_id=:source LIMIT 1');$s->execute(['organization'=>$this->organizationId,'connection'=>$connectionId,'workspace'=>$workspace,'type'=>$type,'source'=>$sourceId]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }

    public function upsertMapping(int $connectionId,string $workspace,string $type,string $sourceId,array $data): array
    {
        $this->requireWorkspace();if(!$this->connectionInWorkspace($connectionId))throw new \RuntimeException('TOGGL_CONNECTION_NOT_FOUND');
        if($this->workerLeaseToken!==''&&!empty($data['created_by_job_id'])){$job=$this->getJobByNumericId((int)$data['created_by_job_id']);if(!$job)throw new \RuntimeException('TOGGL_JOB_NOT_FOUND');$this->requireJobLease((int)$data['created_by_job_id']);}
        $existing=$this->findMapping($connectionId,$workspace,$type,$sourceId);$now=$this->now();
        if($existing){$target=$data['target_public_id']??$existing['target_public_id'];$same=(string)($existing['target_public_id']??'')!==''&&(string)$existing['target_public_id']===(string)$target;$ownerJob=$same&&!empty($existing['created_by_job_id'])?$existing['created_by_job_id']:($data['created_by_job_id']??$existing['created_by_job_id']);$stmt=$this->pdo->prepare('UPDATE module_toggl_source_mappings SET source_parent_id=:parent,target_type=:target_type,target_public_id=:target,source_checksum=:source_checksum,target_checksum=:target_checksum,state=:state,created_by_job_id=:job,last_seen_at=:seen,updated_at=:updated WHERE id=:id AND organization_id=:organization AND connection_id=:connection');$stmt->execute(['parent'=>$data['source_parent_id']??$existing['source_parent_id'],'target_type'=>$data['target_type']??$existing['target_type'],'target'=>$target,'source_checksum'=>$data['source_checksum']??$existing['source_checksum'],'target_checksum'=>$data['target_checksum']??$existing['target_checksum'],'state'=>$data['state']??'active','job'=>$ownerJob,'seen'=>$now,'updated'=>$now,'id'=>$existing['id'],'organization'=>$this->organizationId,'connection'=>$connectionId]);return $this->findMapping($connectionId,$workspace,$type,$sourceId)??$existing;}
        $publicId=$this->id('tgm');$stmt=$this->pdo->prepare('INSERT INTO module_toggl_source_mappings (organization_id,public_id,connection_id,workspace_gid,source_type,source_id,source_parent_id,target_type,target_public_id,source_checksum,target_checksum,state,created_by_job_id,last_seen_at,created_at,updated_at) VALUES (:organization,:public_id,:connection,:workspace,:type,:source,:parent,:target_type,:target,:source_checksum,:target_checksum,:state,:job,:seen,:created,:updated)');$stmt->execute(['organization'=>$this->organizationId,'public_id'=>$publicId,'connection'=>$connectionId,'workspace'=>$workspace,'type'=>$type,'source'=>$sourceId,'parent'=>$data['source_parent_id']??null,'target_type'=>$data['target_type']??null,'target'=>$data['target_public_id']??null,'source_checksum'=>$data['source_checksum']??null,'target_checksum'=>$data['target_checksum']??null,'state'=>$data['state']??'active','job'=>$data['created_by_job_id']??null,'seen'=>$now,'created'=>$now,'updated'=>$now]);return $this->findMapping($connectionId,$workspace,$type,$sourceId)??['public_id'=>$publicId];
    }

    public function upsertUserMapping(int $connectionId,array $user): void
    {
        $this->requireWorkspace();if(!$this->connectionInWorkspace($connectionId))throw new \RuntimeException('TOGGL_CONNECTION_NOT_FOUND');$gid=trim((string)($user['gid']??$user['id']??''));if($gid==='')return;$email=trim((string)($user['email']??''));$crm=null;
        if($email!==''){$stmt=$this->pdo->prepare('SELECT u.public_id FROM users u WHERE u.email=:email AND u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization)) ORDER BY u.id ASC LIMIT 2');$stmt->execute(['email'=>$email,'organization'=>$this->organizationId]);$matches=$stmt->fetchAll(PDO::FETCH_COLUMN)?:[];if(count($matches)===1)$crm=(string)$matches[0];}
        $status=$crm!==null?'mapped':'unmapped';$stmt=$this->pdo->prepare("INSERT INTO module_toggl_user_mappings (organization_id,connection_id,toggl_user_gid,display_name,email,crm_user_public_id,mapping_status,created_at,updated_at) VALUES (:organization,:connection,:gid,:name,:email,:crm,:status,:created,:updated) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),email=VALUES(email),crm_user_public_id=COALESCE(crm_user_public_id,VALUES(crm_user_public_id)),mapping_status=CASE WHEN crm_user_public_id IS NOT NULL THEN 'mapped' ELSE VALUES(mapping_status) END,updated_at=VALUES(updated_at)");$stmt->execute(['organization'=>$this->organizationId,'connection'=>$connectionId,'gid'=>$gid,'name'=>$user['name']??$user['fullname']??null,'email'=>$email!==''?$email:null,'crm'=>$crm,'status'=>$status,'created'=>$this->now(),'updated'=>$this->now()]);
    }

    public function listCrmUsers(): array
    { $this->requireWorkspace();$s=$this->pdo->prepare('SELECT u.id,u.public_id,u.login,u.full_name,u.email FROM users u WHERE u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization)) ORDER BY u.full_name');$s->execute(['organization'=>$this->organizationId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function listUserMappings(int $connectionId): array
    { if(!$this->connectionInWorkspace($connectionId))return [];$s=$this->pdo->prepare('SELECT * FROM module_toggl_user_mappings WHERE organization_id=:organization AND connection_id=:id ORDER BY display_name ASC');$s->execute(['organization'=>$this->organizationId,'id'=>$connectionId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function updateUserMapping(int $connectionId,int $mappingId,?string $crmPublicId): bool
    { if(!$this->connectionInWorkspace($connectionId)||($crmPublicId!==null&&$this->activeUserPublicId($crmPublicId)===null))return false;$s=$this->pdo->prepare('UPDATE module_toggl_user_mappings SET crm_user_public_id=:crm,mapping_status=:status,updated_at=UTC_TIMESTAMP() WHERE id=:id AND connection_id=:connection AND organization_id=:organization');$s->execute(['crm'=>$crmPublicId,'status'=>$crmPublicId?'mapped':'unmapped','id'=>$mappingId,'connection'=>$connectionId,'organization'=>$this->organizationId]);return $s->rowCount()===1; }

    public function activeUserPublicId(string $publicId): ?string
    { if($this->organizationId<1)return null;$s=$this->pdo->prepare('SELECT u.public_id FROM users u WHERE u.public_id=:id AND u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization)) LIMIT 1');$s->execute(['id'=>$publicId,'organization'=>$this->organizationId]);$v=$s->fetchColumn();return $v===false?null:(string)$v; }

    public function mappedUserId(int $connectionId,string $togglGid): ?int
    { if(!$this->connectionInWorkspace($connectionId))return null;$s=$this->pdo->prepare("SELECT u.id FROM module_toggl_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.organization_id=:organization AND m.connection_id=:connection AND m.toggl_user_gid=:gid AND m.mapping_status='mapped' AND u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization)) LIMIT 1");$s->execute(['organization'=>$this->organizationId,'connection'=>$connectionId,'gid'=>$togglGid]);$v=$s->fetchColumn();return $v===false?null:(int)$v; }

    public function mappedUserPublicId(int $connectionId,string $togglGid): ?string
    { if(!$this->connectionInWorkspace($connectionId))return null;$s=$this->pdo->prepare("SELECT u.public_id FROM module_toggl_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.organization_id=:organization AND m.connection_id=:connection AND m.toggl_user_gid=:gid AND m.mapping_status='mapped' AND u.is_active=1 AND (u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization)) LIMIT 1");$s->execute(['organization'=>$this->organizationId,'connection'=>$connectionId,'gid'=>$togglGid]);$v=$s->fetchColumn();return $v===false?null:(string)$v; }

    public function addLog(int $jobId,string $level,string $step,string $message,array $context=[]): void
    { $this->requireWorkspace();if(!$this->jobInWorkspace($jobId))throw new \RuntimeException('TOGGL_JOB_NOT_FOUND');$this->requireJobLease($jobId);$s=$this->pdo->prepare('INSERT INTO module_toggl_job_logs (organization_id,job_id,level,step,message,context_json,created_at) VALUES (:organization,:job,:level,:step,:message,:context,:created)');$s->execute(['organization'=>$this->organizationId,'job'=>$jobId,'level'=>$level,'step'=>$step,'message'=>mb_substr($message,0,2000),'context'=>$context===[]?null:$this->json($context),'created'=>$this->now()]); }

    public function logs(int $jobId,int $limit=100): array
    { if(!$this->jobInWorkspace($jobId))return [];$s=$this->pdo->prepare('SELECT * FROM module_toggl_job_logs WHERE organization_id=:organization AND job_id=:job ORDER BY id DESC LIMIT '.max(1,min(1000,$limit)));$s->execute(['organization'=>$this->organizationId,'job'=>$jobId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function report(string $publicId): array
    { $job=$this->getJob($publicId);if(!$job)return [];$job['items']=$this->itemCounts((int)$job['id']);$job['logs']=$this->logs((int)$job['id']);return $job; }

    public function unresolved(int $jobId,string $type,string $sourceId,string $code,string $text,array $payload=[]): void
    { $this->requireWorkspace();if(!$this->jobInWorkspace($jobId))throw new \RuntimeException('TOGGL_JOB_NOT_FOUND');$this->requireJobLease($jobId);$s=$this->pdo->prepare('INSERT INTO module_toggl_unresolved_entities (organization_id,job_id,source_type,source_id,reason_code,reason_text,payload_json,created_at) VALUES (:organization,:job,:type,:source,:code,:text,:payload,:created)');$s->execute(['organization'=>$this->organizationId,'job'=>$jobId,'type'=>$type,'source'=>$sourceId,'code'=>$code,'text'=>$text,'payload'=>$payload===[]?null:$this->json($payload),'created'=>$this->now()]); }

    public function taskInternalId(string $publicId): ?int
    { if($this->organizationId<1)return null;$s=$this->pdo->prepare('SELECT id FROM tasks WHERE public_id=:id AND organization_id=:organization AND deleted_at IS NULL LIMIT 1');$s->execute(['id'=>$publicId,'organization'=>$this->organizationId]);$v=$s->fetchColumn();return $v===false?null:(int)$v; }

    public function targetExists(string $targetType,string $publicId): bool
    {
        if($this->organizationId<1)return false;
        $table=match($targetType){'client'=>'counterparties','project'=>'projects','task'=>'tasks','tag'=>'tags',default=>''};
        if($targetType==='worklog'){$s=$this->pdo->prepare('SELECT w.id FROM work_logs w LEFT JOIN tasks t ON t.id=w.task_id WHERE w.public_id=:id AND (t.organization_id=:organization OR w.user_id IN (SELECT u.id FROM users u WHERE u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization))) LIMIT 1');$s->execute(['id'=>$publicId,'organization'=>$this->organizationId]);return $s->fetchColumn()!==false;}
        if($table==='')return false;$where=$table==='tasks'?' AND deleted_at IS NULL':'';$s=$this->pdo->prepare('SELECT id FROM '.$table.' WHERE public_id=:id AND organization_id=:organization'.$where.' LIMIT 1');$s->execute(['id'=>$publicId,'organization'=>$this->organizationId]);return $s->fetchColumn()!==false;
    }

    public function createWorklog(array $data): string
    {
        $this->requireWorkspace();$userId=(int)($data['user_id']??0);if($this->actor($userId)===null)throw new \RuntimeException('TOGGL_WORKLOG_USER_FORBIDDEN');$taskId=isset($data['task_id'])?(int)$data['task_id']:0;if($taskId>0){$task=$this->pdo->prepare('SELECT id FROM tasks WHERE id=:id AND organization_id=:organization AND deleted_at IS NULL LIMIT 1');$task->execute(['id'=>$taskId,'organization'=>$this->organizationId]);if($task->fetchColumn()===false)throw new \RuntimeException('TOGGL_WORKLOG_TASK_FORBIDDEN');}
        $publicId = $this->id('wlg');
        $stmt = $this->pdo->prepare('INSERT INTO work_logs (public_id,user_id,task_id,minutes_spent,note,logged_at,started_at,ended_at,created_at) VALUES (:public_id,:user_id,:task_id,:minutes,:note,:logged_at,:started,:ended,:created)');
        $stmt->execute(['public_id'=>$publicId,'user_id'=>(int)$data['user_id'],'task_id'=>$data['task_id']??null,'minutes'=>(int)$data['minutes_spent'],'note'=>(string)($data['note']??''),'logged_at'=>(string)$data['logged_at'],'started'=>$data['started_at']??null,'ended'=>$data['ended_at']??null,'created'=>$this->now()]);
        return $publicId;
    }

    public function updateWorklogByPublicId(string $publicId, array $data): bool
    {
        $this->requireWorkspace();$userId=(int)($data['user_id']??0);if($this->actor($userId)===null)return false;$taskId=isset($data['task_id'])?(int)$data['task_id']:0;if($taskId>0){$task=$this->pdo->prepare('SELECT id FROM tasks WHERE id=:id AND organization_id=:organization AND deleted_at IS NULL LIMIT 1');$task->execute(['id'=>$taskId,'organization'=>$this->organizationId]);if($task->fetchColumn()===false)return false;}
        $set = ['user_id=:user_id','task_id=:task_id','minutes_spent=:minutes','note=:note','logged_at=:logged_at'];
        $params = ['id'=>$publicId,'user_id'=>(int)$data['user_id'],'task_id'=>$data['task_id']??null,'minutes'=>(int)$data['minutes_spent'],'note'=>(string)($data['note']??''),'logged_at'=>(string)$data['logged_at']];
        if (array_key_exists('started_at',$data)) { $set[]='started_at=:started'; $params['started']=$data['started_at']; }
        if (array_key_exists('ended_at',$data)) { $set[]='ended_at=:ended'; $params['ended']=$data['ended_at']; }
        $this->requireWorkspace();$stmt=$this->pdo->prepare('UPDATE work_logs SET '.implode(',',$set).' WHERE public_id=:id AND (task_id IN (SELECT id FROM tasks WHERE organization_id=:organization) OR user_id IN (SELECT u.id FROM users u WHERE u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization)))');$params['organization']=$this->organizationId;
        $stmt->execute($params);
        return $stmt->rowCount()>0 || $this->worklogExists($publicId);
    }

    public function worklogExists(string $publicId): bool
    { if($this->organizationId<1)return false;$stmt=$this->pdo->prepare('SELECT w.id FROM work_logs w LEFT JOIN tasks t ON t.id=w.task_id WHERE w.public_id=:id AND (t.organization_id=:organization OR w.user_id IN (SELECT u.id FROM users u WHERE u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:organization))) LIMIT 1');$stmt->execute(['id'=>$publicId,'organization'=>$this->organizationId]);return $stmt->fetchColumn()!==false; }

    public function deleteWorklogByPublicId(string $publicId): bool
    { if(!$this->worklogExists($publicId))return false;$stmt=$this->pdo->prepare('DELETE FROM work_logs WHERE public_id=:id AND (task_id IN (SELECT id FROM tasks WHERE organization_id=:organization) OR user_id IN (SELECT u.id FROM users u WHERE u.is_root=1 OR EXISTS(SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:membership_organization)))');$stmt->execute(['id'=>$publicId,'organization'=>$this->organizationId,'membership_organization'=>$this->organizationId]);return $stmt->rowCount()>0; }
}
