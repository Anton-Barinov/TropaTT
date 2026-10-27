<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration\Repository;

use PDO;

final class Bitrix24MigrationRepository
{
    private int $organizationId = 0;
    public function __construct(private readonly PDO $pdo) {}
    public function setOrganizationId(int $organizationId): void { $this->organizationId = max(0, $organizationId); }
    private function jobInWorkspace(int $jobId): bool
    { if($jobId<1||$this->organizationId<1)return false;$q=$this->pdo->prepare('SELECT id FROM module_bitrix24_jobs WHERE id=:id AND organization_id=:org LIMIT 1');$q->execute(['id'=>$jobId,'org'=>$this->organizationId]);return$q->fetchColumn()!==false; }
    private function connectionInWorkspace(int $connectionId): bool
    { if($connectionId<1||$this->organizationId<1)return false;$q=$this->pdo->prepare('SELECT id FROM module_bitrix24_connections WHERE id=:id AND organization_id=:org LIMIT 1');$q->execute(['id'=>$connectionId,'org'=>$this->organizationId]);return$q->fetchColumn()!==false; }
    private function id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function json(mixed $value): string { return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

    public function actor(int $id, int $organizationId): ?array
    {
        if ($id < 1 || $organizationId < 1) return null;
        $stmt = $this->pdo->prepare('SELECT u.id,u.public_id,u.login,u.full_name,u.is_root,u.is_active FROM users u WHERE u.id=:id AND u.is_active=1 AND (u.is_root=1 OR EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:org)) LIMIT 1');
        $stmt->execute(['id'=>$id,'org'=>$organizationId]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) return null; $row['organization_id']=$organizationId; return $row;
    }
    public function activeUserPublicId(string $id, int $organizationId): ?string
    { if($organizationId<1)return null;$s=$this->pdo->prepare('SELECT public_id FROM users u WHERE public_id=:id AND is_active=1 AND (is_root=1 OR EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:org)) LIMIT 1');$s->execute(['id'=>$id,'org'=>$organizationId]);$v=$s->fetchColumn();return$v===false?null:(string)$v; }
    public function listConnections(int $actorId,bool $manager): array { if($this->organizationId<1)return[];$sql='SELECT id,public_id,name,auth_type,portal_url,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_bitrix24_connections WHERE organization_id=:org';$p=['org'=>$this->organizationId];if(!$manager){$sql.=' AND created_by_user_id=:o';$p['o']=$actorId;}$sql.=' ORDER BY created_at DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function getConnection(string $id): ?array { if($this->organizationId<1)return null;$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_connections WHERE public_id=:id AND organization_id=:org LIMIT 1');$s->execute(['id'=>$id,'org'=>$this->organizationId]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function getConnectionById(int $id, ?int $organizationId=null): ?array { $org=$organizationId??$this->organizationId;if($id<1||$org<1)return null;$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_connections WHERE id=:id AND organization_id=:org LIMIT 1');$s->execute(['id'=>$id,'org'=>$org]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function createConnection(array $d): array { $org=(int)($d['organization_id']??$this->organizationId);$owner=(int)($d['created_by_user_id']??0);if($org<1||$owner<1)throw new \RuntimeException('BITRIX24_WORKSPACE_REQUIRED');$id=$this->id('b24c');$now=$this->now();$s=$this->pdo->prepare("INSERT INTO module_bitrix24_connections (public_id,organization_id,name,auth_type,portal_url,webhook_url_encrypted,access_token_encrypted,refresh_token_encrypted,client_id_encrypted,client_secret_encrypted,status,created_by_user_id,created_at,updated_at) SELECT :p,:org,:n,:a,:u,:w,:at,:rt,:ci,:cs,'draft',u.id,:cr,:up FROM users u WHERE u.id=:o AND u.is_active=1 AND (u.is_root=1 OR EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=:org_check))");$s->execute(['p'=>$id,'org'=>$org,'n'=>$d['name'],'a'=>$d['auth_type'],'u'=>$d['portal_url'],'w'=>$d['webhook_url_encrypted']??null,'at'=>$d['access_token_encrypted']??null,'rt'=>$d['refresh_token_encrypted']??null,'ci'=>$d['client_id_encrypted']??null,'cs'=>$d['client_secret_encrypted']??null,'o'=>$owner,'cr'=>$now,'up'=>$now,'org_check'=>$org]);if($s->rowCount()!==1)throw new \RuntimeException('BITRIX24_WORKSPACE_ACCESS_REVOKED');$this->organizationId=$org;return$this->getConnection($id)??['public_id'=>$id,'organization_id'=>$org]; }
    public function updateConnection(string $id,array $d): void { if($this->organizationId<1)throw new \RuntimeException('BITRIX24_WORKSPACE_REQUIRED');$allowed=['name','portal_url','webhook_url_encrypted','access_token_encrypted','refresh_token_encrypted','client_id_encrypted','client_secret_encrypted'];$set=[];$p=['id'=>$id,'u'=>$this->now(),'org'=>$this->organizationId];foreach($allowed as $f)if(array_key_exists($f,$d)){$set[]=$f.'=:'.$f;$p[$f]=$d[$f];}if($set===[])return;$set[]='updated_at=:u';$s=$this->pdo->prepare('UPDATE module_bitrix24_connections SET '.implode(',',$set).' WHERE public_id=:id AND organization_id=:org');$s->execute($p);if($s->rowCount()!==1)throw new \RuntimeException('BITRIX24_CONNECTION_NOT_FOUND'); }
    public function updateConnectionCheck(string $id,bool $ok,string $error=''): void { if($this->organizationId<1)return;$s=$this->pdo->prepare('UPDATE module_bitrix24_connections SET status=:s,last_checked_at=UTC_TIMESTAMP(),last_error=:e,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org');$s->execute(['s'=>$ok?'active':'failed','e'=>$error!==''?mb_substr($error,0,1000):null,'id'=>$id,'org'=>$this->organizationId]); }
    public function updateOAuthTokens(int $id,string $access,string $refresh): void { $this->pdo->prepare('UPDATE module_bitrix24_connections SET access_token_encrypted=:a,refresh_token_encrypted=:r,updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:org')->execute(['a'=>$access,'r'=>$refresh,'id'=>$id,'org'=>$this->organizationId]); }
    public function hasRunningJobs(int $connection): bool { $s=$this->pdo->prepare("SELECT id FROM module_bitrix24_jobs WHERE connection_id=:c AND organization_id=:org AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1");$s->execute(['c'=>$connection,'org'=>$this->organizationId]);return$s->fetchColumn()!==false; }
    public function deleteConnection(int $connection): void { if($this->organizationId<1)throw new \RuntimeException('BITRIX24_CONNECTION_NOT_FOUND');$this->pdo->beginTransaction();try{$s=$this->pdo->prepare('SELECT id FROM module_bitrix24_connections WHERE id=:c AND organization_id=:org LIMIT 1 FOR UPDATE');$s->execute(['c'=>$connection,'org'=>$this->organizationId]);if($s->fetchColumn()===false)throw new \RuntimeException('BITRIX24_CONNECTION_NOT_FOUND');foreach(['module_bitrix24_unresolved_entities'=>'job_id IN (SELECT id FROM module_bitrix24_jobs WHERE connection_id=:c AND organization_id=:org)','module_bitrix24_job_logs'=>'job_id IN (SELECT id FROM module_bitrix24_jobs WHERE connection_id=:c AND organization_id=:org)','module_bitrix24_job_items'=>'job_id IN (SELECT id FROM module_bitrix24_jobs WHERE connection_id=:c AND organization_id=:org)','module_bitrix24_jobs'=>'connection_id=:c AND organization_id=:org','module_bitrix24_source_mappings'=>'connection_id=:c','module_bitrix24_user_mappings'=>'connection_id=:c','module_bitrix24_rate_limits'=>'connection_id=:c'] as $t=>$w)$this->pdo->prepare('DELETE FROM '.$t.' WHERE '.$w)->execute(['c'=>$connection,'org'=>$this->organizationId]);$this->pdo->prepare('DELETE FROM module_bitrix24_connections WHERE id=:c AND organization_id=:org')->execute(['c'=>$connection,'org'=>$this->organizationId]);$this->pdo->commit();}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;} }
    private function decodeJob(array $r): array { foreach(['source_scope_json'=>'source_scope','target_options_json'=>'target_options','progress_json'=>'progress','summary_json'=>'summary'] as $a=>$b)if(array_key_exists($a,$r))$r[$b]=json_decode((string)$r[$a],true)?:[];return$r; }
    public function createJob(array $d): array { $org=(int)($d['organization_id']??$this->organizationId);$owner=(int)($d['created_by_user_id']??0);if($org<1||$owner<1)throw new \RuntimeException('BITRIX24_WORKSPACE_REQUIRED');$id=$this->id('b24j');$now=$this->now();$s=$this->pdo->prepare("INSERT INTO module_bitrix24_jobs (public_id,organization_id,connection_id,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) SELECT :p,:org,c.id,:m,'draft',:s,:o,u.id,:cr,:up FROM module_bitrix24_connections c JOIN users u ON u.id=:owner AND u.is_active=1 WHERE c.id=:c AND c.organization_id=:org_check AND (u.is_root=1 OR EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=c.organization_id))");$s->execute(['p'=>$id,'org'=>$org,'m'=>$d['mode']??'import','s'=>$this->json($d['source_scope']??[]),'o'=>$this->json($d['target_options']??[]),'owner'=>$owner,'cr'=>$now,'up'=>$now,'c'=>$d['connection_id'],'org_check'=>$org]);if($s->rowCount()!==1)throw new \RuntimeException('BITRIX24_CONNECTION_NOT_FOUND');$this->organizationId=$org;return$this->getJob($id)??['public_id'=>$id,'organization_id'=>$org]; }
    public function getJob(string $id, ?int $organizationId=null): ?array { $org=$organizationId??$this->organizationId;if($org<1)return null;$s=$this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_bitrix24_jobs j JOIN module_bitrix24_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.public_id=:id AND j.organization_id=:org LIMIT 1');$s->execute(['id'=>$id,'org'=>$org]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$this->decodeJob($r):null; }
    public function listJobs(int $actorId,bool $manager): array { if($this->organizationId<1)return[];$sql='SELECT j.*,c.name AS connection_name FROM module_bitrix24_jobs j JOIN module_bitrix24_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.organization_id=:org';$p=['org'=>$this->organizationId];if(!$manager){$sql.=' AND j.created_by_user_id=:o';$p['o']=$actorId;}$sql.=' ORDER BY j.created_at DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return array_map([$this,'decodeJob'],$s->fetchAll(PDO::FETCH_ASSOC)?:[]); }
    public function requestStatus(string $id,string $status): bool { if($this->organizationId<1)return false;$allowed="'draft','queued','running','paused','pausing','cancelling','failed','cancelled','completed_with_warnings'";$set='status=:s,updated_at=UTC_TIMESTAMP()';if($status==='cancelled')$set.=',finished_at=UTC_TIMESTAMP(),lease_token=NULL,lease_until=NULL';$s=$this->pdo->prepare("UPDATE module_bitrix24_jobs SET {$set} WHERE public_id=:id AND organization_id=:org AND status IN ({$allowed})");$s->execute(['s'=>$status,'id'=>$id,'org'=>$this->organizationId]);return$s->rowCount()===1; }
    public function updateJobStatus(string $id,string $status,?string $lease=null): void { $set=['status=:s','updated_at=UTC_TIMESTAMP()'];$p=['id'=>$id,'s'=>$status];if(in_array($status,['queued','running'],true))$set[]='started_at=COALESCE(started_at,UTC_TIMESTAMP())';if(in_array($status,['completed','completed_with_warnings','failed','cancelled','rolled_back','rolled_back_with_warnings','rollback_failed'],true))$set[]='finished_at=UTC_TIMESTAMP()';$where=' WHERE public_id=:id AND organization_id=:org';$p['org']=$this->organizationId;if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling','rolling_back')";$p['l']=$lease;}$s=$this->pdo->prepare('UPDATE module_bitrix24_jobs SET '.implode(',',$set).$where);$s->execute($p);if($lease!==null&&!$this->ownsLease($id,$lease))throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST'); }
    public function claimNextJob(int $seconds=240): ?array { $this->pdo->beginTransaction();try{$sql="SELECT j.id,j.organization_id FROM module_bitrix24_jobs j JOIN module_bitrix24_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.organization_id IS NOT NULL AND (j.status='queued' OR (j.status IN ('running','pausing','cancelling','rolling_back') AND j.lease_until<UTC_TIMESTAMP())) ORDER BY j.created_at ASC LIMIT 1 FOR UPDATE";if($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite')$sql=str_replace(' FOR UPDATE','',$sql);$s=$this->pdo->query($sql);$row=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($row)){$this->pdo->commit();return null;}$id=(int)$row['id'];$org=(int)$row['organization_id'];$l=bin2hex(random_bytes(16));$u=gmdate('Y-m-d H:i:s',time()+max(60,$seconds));$this->pdo->prepare("UPDATE module_bitrix24_jobs SET status=CASE WHEN status='queued' THEN 'running' ELSE status END,lease_token=:l,lease_until=:u,started_at=COALESCE(started_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:org")->execute(['l'=>$l,'u'=>$u,'id'=>$id,'org'=>$org]);$this->pdo->commit();$this->organizationId=$org;$s=$this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_bitrix24_jobs j JOIN module_bitrix24_connections c ON c.id=j.connection_id AND c.organization_id=j.organization_id WHERE j.id=:id AND j.organization_id=:org');$s->execute(['id'=>$id,'org'=>$org]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$this->decodeJob($r):null;}catch(\Throwable$e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw$e;} }
    /**
     * Reserve a bounded rollback batch. Cursor state is durable so a request
     * timeout can resume without replaying already-marked targets.
     */
    public function claimRollbackJob(string $publicId, int $seconds = 240): array
    {
        if ($this->organizationId < 1) throw new \RuntimeException('BITRIX24_WORKSPACE_REQUIRED');
        $this->pdo->beginTransaction();
        try {
            $sql = 'SELECT id,status,last_source_cursor,lease_token,lease_until FROM module_bitrix24_jobs WHERE public_id=:id AND organization_id=:org LIMIT 1';
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') $sql .= ' FOR UPDATE';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $publicId, 'org' => $this->organizationId]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($job)) throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');

            $status = (string)$job['status'];
            $allowed = ['completed','completed_with_warnings','failed','cancelled','rolled_back','rolled_back_with_warnings','rollback_failed','rolling_back'];
            if (!in_array($status, $allowed, true)) throw new \RuntimeException('BITRIX24_ROLLBACK_JOB_NOT_FINISHED');
            $now = gmdate('Y-m-d H:i:s');
            if ($status === 'rolling_back' && !empty($job['lease_token']) && (string)($job['lease_until'] ?? '') >= $now) {
                throw new \RuntimeException('BITRIX24_ROLLBACK_IN_PROGRESS');
            }

            if ($status === 'rolling_back') {
                $cursor = json_decode((string)($job['last_source_cursor'] ?? ''), true);
                if (!is_array($cursor) || ($cursor['phase'] ?? '') !== 'rollback' || !isset($cursor['priority'], $cursor['before_id'])) {
                    throw new \RuntimeException('BITRIX24_ROLLBACK_CURSOR_INVALID');
                }
                $cursor['priority'] = max(0, min(100, (int)$cursor['priority']));
                $cursor['before_id'] = max(0, (int)$cursor['before_id']);
            } else {
                // A fresh rollback or explicit retry starts at the top of the ordered target set.
                $cursor = ['phase' => 'rollback', 'priority' => 0, 'before_id' => PHP_INT_MAX];
            }

            $lease = bin2hex(random_bytes(16));
            $until = gmdate('Y-m-d H:i:s', time() + max(60, $seconds));
            $update = $this->pdo->prepare("UPDATE module_bitrix24_jobs SET status='rolling_back',lease_token=:lease,lease_until=:until,last_source_cursor=:cursor,finished_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:id AND organization_id=:org");
            $update->execute(['lease' => $lease, 'until' => $until, 'cursor' => $this->json($cursor), 'id' => (int)$job['id'], 'org' => $this->organizationId]);
            $this->pdo->commit();
            $fresh = $this->getJob($publicId);
            if (!$fresh) throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');
            $fresh['rollback_lease_token'] = $lease;
            $fresh['rollback_cursor'] = $cursor;
            return $fresh;
        } catch (\Throwable $error) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $error;
        }
    }

    /** Fetch a bounded, dependency-ordered page after the durable rollback cursor. */
    public function rollbackItemsBatch(int $job, int $priority, int $beforeId, int $limit = 50): array
    {
        if (!$this->jobInWorkspace($job)) return [];
        $rank = "CASE i.source_type WHEN 'file' THEN 10 WHEN 'product_row' THEN 10 WHEN 'comment' THEN 20 WHEN 'timeline_comment' THEN 20 WHEN 'calendar_event' THEN 30 WHEN 'event' THEN 30 WHEN 'activity' THEN 30 WHEN 'subtask' THEN 40 WHEN 'task' THEN 50 WHEN 'file_task' THEN 50 WHEN 'project' THEN 60 WHEN 'task_project' THEN 60 WHEN 'contact' THEN 70 WHEN 'counterparty' THEN 70 WHEN 'company' THEN 70 WHEN 'department' THEN 70 WHEN 'tag' THEN 70 ELSE 80 END";
        $sql = "SELECT i.*, {$rank} AS rollback_priority FROM module_bitrix24_job_items i INNER JOIN module_bitrix24_jobs j ON j.id=i.job_id WHERE i.job_id=:job AND j.organization_id=:org AND i.created_by_job=1 AND i.target_public_id IS NOT NULL AND i.status NOT IN ('rolled_back','rollback_preserved_shared') AND ({$rank}>:priority_after OR ({$rank}=:priority_current AND i.id<:before_id)) ORDER BY rollback_priority ASC,i.id DESC LIMIT " . max(1,min(100,$limit));
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':job', $job, PDO::PARAM_INT);
        $stmt->bindValue(':org', $this->organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':priority_after', $priority, PDO::PARAM_INT);
        $stmt->bindValue(':priority_current', $priority, PDO::PARAM_INT);
        $stmt->bindValue(':before_id', $beforeId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markRollbackItem(int $job, string $type, string $source, string $status, ?string $errorCode, ?string $errorMessage, string $lease): void
    {
        if (!in_array($status, ['rolled_back','rollback_failed','rollback_preserved_shared'], true)) {
            throw new \InvalidArgumentException('Invalid rollback item status.');
        }
        $stmt = $this->pdo->prepare("UPDATE module_bitrix24_job_items SET status=:status,error_code=:error_code,error_message=:error_message,updated_at=UTC_TIMESTAMP() WHERE job_id=:job AND source_type=:type AND source_id=:source AND EXISTS (SELECT 1 FROM module_bitrix24_jobs j WHERE j.id=module_bitrix24_job_items.job_id AND j.organization_id=:org AND j.status='rolling_back' AND j.lease_token=:lease AND j.lease_until>=UTC_TIMESTAMP())");
        $stmt->execute(['status'=>$status,'error_code'=>$errorCode,'error_message'=>$errorMessage,'job'=>$job,'type'=>$type,'source'=>$source,'org'=>$this->organizationId,'lease'=>$lease]);
        if ($stmt->rowCount() !== 1) {
            $check = $this->pdo->prepare("SELECT 1 FROM module_bitrix24_job_items i INNER JOIN module_bitrix24_jobs j ON j.id=i.job_id WHERE i.job_id=:job AND i.source_type=:type AND i.source_id=:source AND j.organization_id=:org AND j.status='rolling_back' AND j.lease_token=:lease AND j.lease_until>=UTC_TIMESTAMP() LIMIT 1");
            $check->execute(['job'=>$job,'type'=>$type,'source'=>$source,'org'=>$this->organizationId,'lease'=>$lease]);
            if ($check->fetchColumn() === false) throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');
        }
    }

    public function finishRollbackJob(string $publicId, string $lease, string $status, array $summary, array $progress): void
    {
        if (!in_array($status, ['rolled_back','rolled_back_with_warnings'], true)) throw new \InvalidArgumentException('Invalid rollback terminal status.');
        $stmt = $this->pdo->prepare("UPDATE module_bitrix24_jobs SET status=:status,current_step=:step,progress_percent=100,progress_json=:progress,summary_json=:summary,finished_at=UTC_TIMESTAMP(),lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org AND status='rolling_back' AND lease_token=:lease AND lease_until>=UTC_TIMESTAMP()");
        $stmt->execute(['status'=>$status,'step'=>$status,'progress'=>$this->json($progress),'summary'=>$this->json($summary),'id'=>$publicId,'org'=>$this->organizationId,'lease'=>$lease]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');
    }

    public function rollbackTargetCount(int $job): int
    {
        if (!$this->jobInWorkspace($job)) throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM module_bitrix24_job_items i INNER JOIN module_bitrix24_jobs j ON j.id=i.job_id WHERE i.job_id=:job AND j.organization_id=:org AND i.created_by_job=1 AND i.target_public_id IS NOT NULL AND i.status NOT IN ('rolled_back','rollback_preserved_shared')");
        $stmt->execute(['job'=>$job,'org'=>$this->organizationId]);
        return (int)$stmt->fetchColumn();
    }

    public function rollbackPreservedCount(int $job): int
    {
        if (!$this->jobInWorkspace($job)) throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM module_bitrix24_job_items i INNER JOIN module_bitrix24_jobs j ON j.id=i.job_id WHERE i.job_id=:job AND j.organization_id=:org AND i.status='rollback_preserved_shared'");
        $stmt->execute(['job'=>$job,'org'=>$this->organizationId]);
        return (int)$stmt->fetchColumn();
    }

    public function rollbackTargetTotal(int $job): int
    {
        if (!$this->jobInWorkspace($job)) throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM module_bitrix24_job_items i INNER JOIN module_bitrix24_jobs j ON j.id=i.job_id WHERE i.job_id=:job AND j.organization_id=:org AND i.created_by_job=1 AND i.target_public_id IS NOT NULL');
        $stmt->execute(['job'=>$job,'org'=>$this->organizationId]);
        return (int)$stmt->fetchColumn();
    }

    public function rollbackHasOtherReference(int $job, string $targetType, string $targetPublicId): bool
    {
        if (!$this->jobInWorkspace($job) || $targetPublicId === '') return false;
        $stmt = $this->pdo->prepare("SELECT 1 FROM module_bitrix24_job_items i INNER JOIN module_bitrix24_jobs j ON j.id=i.job_id WHERE j.organization_id=:org AND i.job_id<>:job AND i.target_type=:type AND i.target_public_id=:target AND i.status NOT IN ('rolled_back','rollback_preserved_shared') LIMIT 1");
        $stmt->execute(['org'=>$this->organizationId,'job'=>$job,'type'=>$targetType,'target'=>$targetPublicId]);
        return $stmt->fetchColumn() !== false;
    }

    public function saveRollbackCursor(string $publicId, array $cursor, string $lease): void
    {
        $stmt = $this->pdo->prepare("UPDATE module_bitrix24_jobs SET last_source_cursor=:cursor,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org AND status='rolling_back' AND lease_token=:lease AND lease_until>=UTC_TIMESTAMP()");
        $stmt->execute(['cursor'=>$this->json($cursor),'id'=>$publicId,'org'=>$this->organizationId,'lease'=>$lease]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');
    }

    public function saveRollbackProgress(string $publicId, string $lease, array $progress, float $percent): void
    {
        $stmt = $this->pdo->prepare("UPDATE module_bitrix24_jobs SET progress_percent=:percent,progress_json=:progress,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org AND status='rolling_back' AND lease_token=:lease AND lease_until>=UTC_TIMESTAMP()");
        $stmt->execute(['percent'=>max(0,min(100,$percent)),'progress'=>$this->json($progress),'id'=>$publicId,'org'=>$this->organizationId,'lease'=>$lease]);
        if ($stmt->rowCount() !== 1) throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');
    }

    public function releaseRollbackLease(string $publicId, string $lease): void
    {
        $stmt = $this->pdo->prepare("UPDATE module_bitrix24_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org AND status='rolling_back' AND lease_token=:lease");
        $stmt->execute(['id'=>$publicId,'org'=>$this->organizationId,'lease'=>$lease]);
    }

    public function heartbeat(string $id,string $lease,int $seconds=240): bool { $s=$this->pdo->prepare("UPDATE module_bitrix24_jobs SET lease_until=:u,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org AND lease_token=:l AND status IN ('running','pausing','cancelling','rolling_back')");$s->execute(['u'=>gmdate('Y-m-d H:i:s',time()+max(60,$seconds)),'id'=>$id,'org'=>$this->organizationId,'l'=>$lease]);return$this->ownsLease($id,$lease); }
    public function ownsLease(string $id,string $lease): bool { $s=$this->pdo->prepare("SELECT id FROM module_bitrix24_jobs WHERE public_id=:id AND organization_id=:org AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling','rolling_back') LIMIT 1");$s->execute(['id'=>$id,'org'=>$this->organizationId,'l'=>$lease]);return$s->fetchColumn()!==false; }
    public function releaseLease(string $id,string $lease): void {$this->pdo->prepare('UPDATE module_bitrix24_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND organization_id=:org AND lease_token=:l')->execute(['id'=>$id,'org'=>$this->organizationId,'l'=>$lease]);}
    public function updateProgress(string $id,string $step,float $percent,array $progress,?string $lease=null): void {$p=['id'=>$id,'s'=>$step,'pc'=>max(0,min(100,$percent)),'pr'=>$this->json($progress)];$where=' WHERE public_id=:id AND organization_id=:org';$p['org']=$this->organizationId;if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling','rolling_back')";$p['l']=$lease;}$this->pdo->prepare('UPDATE module_bitrix24_jobs SET current_step=:s,progress_percent=:pc,progress_json=:pr,updated_at=UTC_TIMESTAMP()'.$where)->execute($p);if($lease!==null&&!$this->ownsLease($id,$lease))throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');}
    public function updateSummary(string $id,array $summary,?string $lease=null): void {$p=['id'=>$id,'s'=>$this->json($summary)];$where=' WHERE public_id=:id AND organization_id=:org';$p['org']=$this->organizationId;if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling','rolling_back')";$p['l']=$lease;}$this->pdo->prepare('UPDATE module_bitrix24_jobs SET summary_json=:s,updated_at=UTC_TIMESTAMP()'.$where)->execute($p);if($lease!==null&&!$this->ownsLease($id,$lease))throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');}
    public function updateCursor(string $id,array $cursor,?string $lease=null): void {$p=['id'=>$id,'c'=>$this->json($cursor)];$where=' WHERE public_id=:id AND organization_id=:org';$p['org']=$this->organizationId;if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling','rolling_back')";$p['l']=$lease;}$this->pdo->prepare('UPDATE module_bitrix24_jobs SET last_source_cursor=:c,updated_at=UTC_TIMESTAMP()'.$where)->execute($p);if($lease!==null&&!$this->ownsLease($id,$lease))throw new \RuntimeException('BITRIX24_JOB_LEASE_LOST');}

    public function findItem(int $job,string $type,string $source): ?array { if(!$this->jobInWorkspace($job))return null;$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_job_items WHERE job_id=:j AND source_type=:t AND source_id=:s LIMIT 1');$s->execute(['j'=>$job,'t'=>$type,'s'=>$source]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
    public function upsertItem(int $job,string $type,string $source,array $d): void { if(!$this->jobInWorkspace($job))throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');$old=$this->findItem($job,$type,$source);$fields=['source_parent_id','target_type','target_public_id','created_by_job','status','checksum','source_updated_at','payload_json','attempts','error_code','error_message'];if($old){if(($d['status']??null)==='pending'&&(string)($old['checksum']??'')===(string)($d['checksum']??'')&&in_array((string)$old['status'],['imported','updated','skipped'],true))unset($d['status']);$set=['updated_at=UTC_TIMESTAMP()'];$p=['id'=>$old['id']];foreach($fields as $f)if(array_key_exists($f,$d)){$set[]=$f.'=:'.$f;$p[$f]=$f==='payload_json'&&is_array($d[$f])?$this->json($d[$f]):$d[$f];}$this->pdo->prepare('UPDATE module_bitrix24_job_items SET '.implode(',',$set).' WHERE id=:id')->execute($p);return;}$s=$this->pdo->prepare('INSERT INTO module_bitrix24_job_items (job_id,source_type,source_id,source_parent_id,target_type,target_public_id,created_by_job,status,checksum,source_updated_at,attempts,error_code,error_message,payload_json,created_at,updated_at) VALUES (:j,:t,:s,:p,:tt,:tp,:cb,:st,:c,:su,:a,:ec,:em,:pl,UTC_TIMESTAMP(),UTC_TIMESTAMP())');$s->execute(['j'=>$job,'t'=>$type,'s'=>$source,'p'=>$d['source_parent_id']??null,'tt'=>$d['target_type']??null,'tp'=>$d['target_public_id']??null,'cb'=>!empty($d['created_by_job'])?1:0,'st'=>$d['status']??'pending','c'=>$d['checksum']??null,'su'=>$d['source_updated_at']??null,'a'=>$d['attempts']??0,'ec'=>$d['error_code']??null,'em'=>$d['error_message']??null,'pl'=>isset($d['payload_json'])?(is_array($d['payload_json'])?$this->json($d['payload_json']):$d['payload_json']):null]);}
    public function items(int $job,?string $status=null,int $limit=1000): array { if(!$this->jobInWorkspace($job))return[];$sql='SELECT * FROM module_bitrix24_job_items WHERE job_id=:j';$p=['j'=>$job];if($status!==null&&$status!==''){$sql.=' AND status=:s';$p['s']=$status;}$sql.=' ORDER BY id ASC LIMIT '.max(1,min(10000,$limit));$s=$this->pdo->prepare($sql);$s->execute($p);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function itemCounts(int $job): array { if(!$this->jobInWorkspace($job))return[];$s=$this->pdo->prepare('SELECT status,COUNT(*) AS count FROM module_bitrix24_job_items WHERE job_id=:j GROUP BY status');$s->execute(['j'=>$job]);$r=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$r[(string)$row['status']]=(int)$row['count'];return$r;}
    public function importItemsBatch(int $job,int $priority=0,int $lastId=0,int $limit=100): array { if(!$this->jobInWorkspace($job))return[];$sql="SELECT * FROM (SELECT i.*,CASE i.source_type WHEN 'department' THEN 10 WHEN 'user' THEN 15 WHEN 'company' THEN 20 WHEN 'contact' THEN 25 WHEN 'lead' THEN 30 WHEN 'project' THEN 35 WHEN 'task_project' THEN 35 WHEN 'deal' THEN 40 WHEN 'task' THEN 50 WHEN 'subtask' THEN 55 WHEN 'comment' THEN 60 WHEN 'timeline_comment' THEN 60 WHEN 'activity' THEN 65 WHEN 'event' THEN 65 WHEN 'file' THEN 70 WHEN 'product' THEN 80 WHEN 'invoice' THEN 85 WHEN 'quote' THEN 85 ELSE 90 END AS import_priority FROM module_bitrix24_job_items i WHERE i.job_id=:j AND i.status='pending') x WHERE x.import_priority>:p OR (x.import_priority=:p AND x.id>:i) ORDER BY x.import_priority ASC,x.id ASC LIMIT ".max(1,min(500,$limit))."";$s=$this->pdo->prepare($sql);$s->execute(['j'=>$job,'p'=>$priority,'i'=>$lastId]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function findMapping(int $connection,string $type,string $source): ?array { if(!$this->connectionInWorkspace($connection))return null;$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_source_mappings WHERE connection_id=:c AND source_type=:t AND source_id=:s LIMIT 1');$s->execute(['c'=>$connection,'t'=>$type,'s'=>$source]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
    public function upsertMapping(int $connection,string $type,string $source,array $d): array { if(!$this->connectionInWorkspace($connection))throw new \RuntimeException('BITRIX24_CONNECTION_NOT_FOUND');$old=$this->findMapping($connection,$type,$source);$now=$this->now();if($old){$s=$this->pdo->prepare('UPDATE module_bitrix24_source_mappings SET source_parent_id=:p,target_type=:tt,target_public_id=:tp,source_checksum=:sc,state=:st,created_by_job_id=COALESCE(created_by_job_id,:j),last_seen_at=:ls,updated_at=:u WHERE id=:i');$s->execute(['p'=>$d['source_parent_id']??$old['source_parent_id'],'tt'=>$d['target_type']??$old['target_type'],'tp'=>$d['target_public_id']??$old['target_public_id'],'sc'=>$d['source_checksum']??$old['source_checksum'],'st'=>$d['state']??'active','j'=>$d['created_by_job_id']??$old['created_by_job_id'],'ls'=>$now,'u'=>$now,'i'=>$old['id']]);return$this->findMapping($connection,$type,$source)??$old;}$id=$this->id('b24m');$s=$this->pdo->prepare('INSERT INTO module_bitrix24_source_mappings (public_id,connection_id,source_type,source_id,source_parent_id,target_type,target_public_id,source_checksum,state,created_by_job_id,last_seen_at,created_at,updated_at) VALUES (:i,:c,:t,:s,:p,:tt,:tp,:sc,:st,:j,:ls,:cr,:u)');$s->execute(['i'=>$id,'c'=>$connection,'t'=>$type,'s'=>$source,'p'=>$d['source_parent_id']??null,'tt'=>$d['target_type']??null,'tp'=>$d['target_public_id']??null,'sc'=>$d['source_checksum']??null,'st'=>$d['state']??'active','j'=>$d['created_by_job_id']??null,'ls'=>$now,'cr'=>$now,'u'=>$now]);return$this->findMapping($connection,$type,$source)??['public_id'=>$id];}
    public function markMappingRolledBack(int $connection,string $type,string $source): void { if(!$this->connectionInWorkspace($connection))return;$this->pdo->prepare("UPDATE module_bitrix24_source_mappings SET target_type=NULL,target_public_id=NULL,state='rolled_back',updated_at=UTC_TIMESTAMP() WHERE connection_id=:c AND source_type=:t AND source_id=:s")->execute(['c'=>$connection,'t'=>$type,'s'=>$source]);}
    public function upsertUserMapping(int $connection,array $user): void {$id=trim((string)($user['ID']??$user['id']??''));if($id==='')return;$name=trim(implode(' ',array_filter([(string)($user['NAME']??''),(string)($user['LAST_NAME']??''),(string)($user['SECOND_NAME']??'')])))?:($user['FULL_NAME']??$user['name']??$id);$s=$this->pdo->prepare("INSERT INTO module_bitrix24_user_mappings (connection_id,bitrix_user_id,display_name,email,mapping_status,created_at,updated_at) VALUES (:c,:i,:n,:e,'unmapped',UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),email=VALUES(email),updated_at=UTC_TIMESTAMP()");$s->execute(['c'=>$connection,'i'=>$id,'n'=>mb_substr($name,0,255),'e'=>$user['EMAIL']??$user['email']??null]);}
    public function mappedUserId(int $connection,string $source): ?int { if(!$this->connectionInWorkspace($connection))return null;$s=$this->pdo->prepare("SELECT u.id FROM module_bitrix24_user_mappings m JOIN module_bitrix24_connections c ON c.id=m.connection_id JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:c AND c.organization_id=:org AND m.bitrix_user_id=:i AND m.mapping_status='mapped' AND u.is_active=1 AND (u.is_root=1 OR EXISTS (SELECT 1 FROM organization_memberships om WHERE om.user_id=u.id AND om.organization_id=c.organization_id)) LIMIT 1");$s->execute(['c'=>$connection,'org'=>$this->organizationId,'i'=>$source]);$v=$s->fetchColumn();return$v===false?null:(int)$v;}
    public function listUserMappings(int $connection): array { if(!$this->connectionInWorkspace($connection))return[];$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_user_mappings WHERE connection_id=:c ORDER BY display_name ASC');$s->execute(['c'=>$connection]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function updateUserMapping(int $connection,int $id,?string $crm): bool { if(!$this->connectionInWorkspace($connection))return false;$s=$this->pdo->prepare("UPDATE module_bitrix24_user_mappings SET crm_user_public_id=:u,mapping_status=:s,updated_at=UTC_TIMESTAMP() WHERE id=:i AND connection_id=:c");$s->execute(['u'=>$crm?:null,'s'=>$crm?'mapped':'unmapped','i'=>$id,'c'=>$connection]);if($s->rowCount()>0)return true;$s=$this->pdo->prepare('SELECT id FROM module_bitrix24_user_mappings WHERE id=:i AND connection_id=:c');$s->execute(['i'=>$id,'c'=>$connection]);return$s->fetchColumn()!==false;}
    public function addLog(int $job,string $level,string $step,string $message,array $context=[]): void { if(!$this->jobInWorkspace($job))throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');$s=$this->pdo->prepare('INSERT INTO module_bitrix24_job_logs (job_id,level,step,message,context_json,created_at) VALUES (:j,:l,:s,:m,:c,UTC_TIMESTAMP())');$s->execute(['j'=>$job,'l'=>$level,'s'=>$step,'m'=>mb_substr($message,0,2000),'c'=>$context===[]?null:$this->json($context)]);}
    public function logs(int $job,int $limit=100): array { if(!$this->jobInWorkspace($job))return[];$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_job_logs WHERE job_id=:j ORDER BY id DESC LIMIT '.max(1,min(1000,$limit)));$s->execute(['j'=>$job]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function unresolved(int $job,string $type,string $source,string $reason,string $text,array $payload=[]): void { if(!$this->jobInWorkspace($job))throw new \RuntimeException('BITRIX24_JOB_NOT_FOUND');$this->pdo->prepare('DELETE FROM module_bitrix24_unresolved_entities WHERE job_id=:j AND source_type=:t AND source_id=:s AND reason_code=:r')->execute(['j'=>$job,'t'=>$type,'s'=>$source,'r'=>$reason]);$this->pdo->prepare('INSERT INTO module_bitrix24_unresolved_entities (job_id,source_type,source_id,reason_code,reason_text,payload_json,created_at) VALUES (:j,:t,:s,:r,:x,:p,UTC_TIMESTAMP())')->execute(['j'=>$job,'t'=>$type,'s'=>$source,'r'=>$reason,'x'=>mb_substr($text,0,2000),'p'=>$payload===[]?null:$this->json($payload)]);}
    public function unresolvedItems(int $job): array { if(!$this->jobInWorkspace($job))return[];$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_unresolved_entities WHERE job_id=:j ORDER BY id DESC LIMIT 1000');$s->execute(['j'=>$job]);return$s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    public function report(string $id): array { if($this->organizationId<1)return[];$j=$this->getJob($id);if(!$j)return[];$j['items']=$this->itemCounts((int)$j['id']);$j['logs']=$this->logs((int)$j['id']);$j['unresolved']=$this->unresolvedItems((int)$j['id']);return$j;}
    public function resetFailed(string $id): ?int { if($this->organizationId<1)return null;$s=$this->pdo->prepare("SELECT id,status FROM module_bitrix24_jobs WHERE public_id=:p AND organization_id=:org AND status IN ('failed','completed_with_warnings','cancelled') LIMIT 1");$s->execute(['p'=>$id,'org'=>$this->organizationId]);$j=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($j))return null;$u=$this->pdo->prepare("UPDATE module_bitrix24_job_items SET status='pending',error_code=NULL,error_message=NULL WHERE job_id=:j AND status='failed'");$u->execute(['j'=>$j['id']]);$n=$u->rowCount();$this->pdo->prepare("UPDATE module_bitrix24_jobs SET status='queued',last_source_cursor=:c,current_step='import_retry',finished_at=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:j AND organization_id=:org")->execute(['c'=>$this->json(['phase'=>'import','priority'=>0,'id'=>0]),'j'=>$j['id'],'org'=>$this->organizationId]);return$n;}
    public function rateState(int $id): ?array { if(!$this->connectionInWorkspace($id))return null;$s=$this->pdo->prepare('SELECT * FROM module_bitrix24_rate_limits WHERE connection_id=:c LIMIT 1');$s->execute(['c'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;}
    public function attempts(int $id): int { $r=$this->rateState($id);return(int)($r['requests_made']??0); }
    public function recordRequest(int $id,int $status,array $headers): void { if(!$this->connectionInWorkspace($id))return;$r=$this->rateState($id)?:[];$this->upsertRate($id,['requests_made'=>(int)($r['requests_made']??0)+1,'window_started_at'=>$r['window_started_at']??$this->now(),'retry_after_until'=>$r['retry_after_until']??null,'last_http_status'=>$status]);}
    public function recordRetryAfter(int $id,int $seconds): void { if(!$this->connectionInWorkspace($id))return;$r=$this->rateState($id)?:[];$this->upsertRate($id,array_merge($r,['retry_after_until'=>gmdate('Y-m-d H:i:s',time()+max(1,$seconds))]));}
    private function upsertRate(int $id,array $d): void {if(!$this->connectionInWorkspace($id))return;$s=$this->pdo->prepare('INSERT INTO module_bitrix24_rate_limits (connection_id,requests_made,window_started_at,retry_after_until,last_http_status,updated_at) VALUES (:c,:n,:w,:r,:s,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE requests_made=VALUES(requests_made),window_started_at=VALUES(window_started_at),retry_after_until=VALUES(retry_after_until),last_http_status=VALUES(last_http_status),updated_at=UTC_TIMESTAMP()');$s->execute(['c'=>$id,'n'=>(int)($d['requests_made']??0),'w'=>$d['window_started_at']??$this->now(),'r'=>$d['retry_after_until']??null,'s'=>$d['last_http_status']??null]);}
}
