<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Repository;

use PDO;
use RuntimeException;

final class ClickUpMigrationRepository
{
    public function __construct(private readonly PDO $pdo) {}
    private function id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function utcTimestamp(string $value): ?int
    {
        $value = trim($value);
        if ($value === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('UTC'));
        return $date instanceof \DateTimeImmutable ? $date->getTimestamp() : null;
    }
    private function json(mixed $value): string { return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

    public function actor(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id,public_id,login,full_name,is_root,is_active FROM users WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]); return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ?: ['id' => $id, 'is_root' => 0];
    }
    public function activeUserPublicId(string $id): ?string
    { $stmt=$this->pdo->prepare('SELECT public_id FROM users WHERE public_id=:id AND is_active=1 LIMIT 1'); $stmt->execute(['id'=>$id]); $v=$stmt->fetchColumn(); return $v===false?null:(string)$v; }
    public function mappedUserId(int $connectionId,string $sourceId): ?int
    { $stmt=$this->pdo->prepare("SELECT u.id FROM module_clickup_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:c AND m.clickup_user_id=:s AND m.mapping_status='mapped' AND u.is_active=1 LIMIT 1"); $stmt->execute(['c'=>$connectionId,'s'=>$sourceId]); $v=$stmt->fetchColumn(); return $v===false?null:(int)$v; }
    public function mappedUserPublicId(int $connectionId,string $sourceId): ?string
    { $stmt=$this->pdo->prepare("SELECT u.public_id FROM module_clickup_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:c AND m.clickup_user_id=:s AND m.mapping_status='mapped' AND u.is_active=1 LIMIT 1"); $stmt->execute(['c'=>$connectionId,'s'=>$sourceId]); $v=$stmt->fetchColumn(); return $v===false?null:(string)$v; }

    public function listConnections(int $actorId,bool $manager): array
    { $sql='SELECT id,public_id,name,auth_type,account_id,account_name,status,last_checked_at,last_error,created_by_user_id,created_at,updated_at FROM module_clickup_connections';$p=[];if(!$manager){$sql.=' WHERE created_by_user_id=:o';$p['o']=$actorId;}$sql.=' ORDER BY created_at DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function getConnection(string $id): ?array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_connections WHERE public_id=:id LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function getConnectionById(int $id): ?array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_connections WHERE id=:id LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function createConnection(array $d): array
    { $pid=$this->id('cuc');$n=$this->now();$s=$this->pdo->prepare("INSERT INTO module_clickup_connections (public_id,name,auth_type,access_token_encrypted,refresh_token_encrypted,client_id_encrypted,client_secret_encrypted,status,created_by_user_id,created_at,updated_at) VALUES (:p,:n,:a,:t,:r,:ci,:cs,'draft',:o,:c,:u)");$s->execute(['p'=>$pid,'n'=>$d['name'],'a'=>$d['auth_type']??'pat','t'=>$d['access_token_encrypted']??null,'r'=>$d['refresh_token_encrypted']??null,'ci'=>$d['client_id_encrypted']??null,'cs'=>$d['client_secret_encrypted']??null,'o'=>$d['created_by_user_id'],'c'=>$n,'u'=>$n]);return $this->getConnection($pid)??['public_id'=>$pid]; }
    public function updateConnection(string $id,array $d): void
    { $allowed=['name','auth_type','access_token_encrypted','refresh_token_encrypted','client_id_encrypted','client_secret_encrypted'];$set=[];$p=['id'=>$id,'u'=>$this->now()];foreach($allowed as $f)if(array_key_exists($f,$d)){$set[]="$f=:$f";$p[$f]=$d[$f];}if($set===[])return;$set[]='updated_at=:u';$this->pdo->prepare('UPDATE module_clickup_connections SET '.implode(',',$set).' WHERE public_id=:id')->execute($p); }
    public function markConnectionUnverified(string $id): void
    { $this->pdo->prepare("UPDATE module_clickup_connections SET status='draft',last_checked_at=NULL,last_error=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id")->execute(['id'=>$id]); }
    public function updateConnectionTokens(string $id, string $accessTokenEncrypted, ?string $refreshTokenEncrypted = null): void
    {
        $set = ['access_token_encrypted=:a', 'updated_at=:u'];
        $params = ['a' => $accessTokenEncrypted, 'u' => $this->now(), 'id' => $id];
        if ($refreshTokenEncrypted !== null) { $set[] = 'refresh_token_encrypted=:r'; $params['r'] = $refreshTokenEncrypted; }
        $this->pdo->prepare('UPDATE module_clickup_connections SET ' . implode(',', $set) . ' WHERE public_id=:id')->execute($params);
    }

    public function updateConnectionCheck(string $id,bool $ok,string $error='',?array $account=null): void
    { $set=['status=:s','last_checked_at=:c','last_error=:e','updated_at=:u'];$p=['s'=>$ok?'active':'failed','c'=>$this->now(),'e'=>$error!==''?mb_substr($error,0,1000):null,'u'=>$this->now(),'id'=>$id];if($account!==null){$set[]='account_id=:aid';$set[]='account_name=:an';$p['aid']=(string)($account['id']??'');$p['an']=mb_substr((string)($account['name']??$account['email']??''),0,255);} $this->pdo->prepare('UPDATE module_clickup_connections SET '.implode(',',$set).' WHERE public_id=:id')->execute($p); }
    public function deleteConnection(int $id): void
    { $this->pdo->beginTransaction();try{foreach(['module_clickup_unresolved_entities'=>'job_id IN (SELECT id FROM module_clickup_jobs WHERE connection_id=:id)','module_clickup_job_logs'=>'job_id IN (SELECT id FROM module_clickup_jobs WHERE connection_id=:id)','module_clickup_job_items'=>'job_id IN (SELECT id FROM module_clickup_jobs WHERE connection_id=:id)','module_clickup_jobs'=>'connection_id=:id','module_clickup_source_mappings'=>'connection_id=:id','module_clickup_user_mappings'=>'connection_id=:id','module_clickup_rate_limits'=>'connection_id=:id'] as $t=>$w)$this->pdo->prepare('DELETE FROM '.$t.' WHERE '.$w)->execute(['id'=>$id]);$this->pdo->prepare('DELETE FROM module_clickup_connections WHERE id=:id')->execute(['id'=>$id]);$this->pdo->commit();}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;} }
    public function hasRunningJobs(int $id): bool
    { $s=$this->pdo->prepare("SELECT id FROM module_clickup_jobs WHERE connection_id=:id AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1");$s->execute(['id'=>$id]);return $s->fetchColumn()!==false; }

    private function decodeJob(array $r): array { foreach(['source_scope_json'=>'source_scope','target_options_json'=>'target_options','progress_json'=>'progress','summary_json'=>'summary'] as $a=>$b)if(array_key_exists($a,$r))$r[$b]=json_decode((string)$r[$a],true)?:[];return $r; }
    public function createJob(array $d): array
    { $pid=$this->id('cuj');$n=$this->now();$s=$this->pdo->prepare("INSERT INTO module_clickup_jobs (public_id,connection_id,mode,status,source_scope_json,target_options_json,created_by_user_id,created_at,updated_at) VALUES (:p,:c,:m,'draft',:ss,:to,:o,:n,:n2)");$s->execute(['p'=>$pid,'c'=>$d['connection_id'],'m'=>$d['mode']??'import','ss'=>$this->json($d['source_scope']??[]),'to'=>$this->json($d['target_options']??[]),'o'=>$d['created_by_user_id'],'n'=>$n,'n2'=>$n]);return $this->getJob($pid)??['public_id'=>$pid]; }
    public function getJob(string $id): ?array
    { $s=$this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_clickup_jobs j JOIN module_clickup_connections c ON c.id=j.connection_id WHERE j.public_id=:id LIMIT 1');$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$this->decodeJob($r):null; }
    public function listJobs(int $actorId,bool $manager): array
    { $sql='SELECT j.*,c.name AS connection_name FROM module_clickup_jobs j JOIN module_clickup_connections c ON c.id=j.connection_id';$p=[];if(!$manager){$sql.=' WHERE j.created_by_user_id=:o';$p['o']=$actorId;}$sql.=' ORDER BY j.created_at DESC';$s=$this->pdo->prepare($sql);$s->execute($p);return array_map([$this,'decodeJob'],$s->fetchAll(PDO::FETCH_ASSOC)?:[]); }
    public function requestStatus(string $id,string $status): bool
    { $s=$this->pdo->prepare("UPDATE module_clickup_jobs SET status=:s,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND status IN ('draft','queued','running','paused','pausing','cancelling','failed','cancelled')");$s->execute(['id'=>$id,'s'=>$status]);return $s->rowCount()===1; }
    public function updateJobStatus(string $id,string $status,?string $lease=null): void
    {
        $set = ['status=:s', 'updated_at=:u'];
        $params = ['id' => $id, 's' => $status, 'u' => $this->now()];
        if (in_array($status, ['queued', 'running'], true)) {
            $set[] = 'started_at=COALESCE(started_at,:st)';
            $params['st'] = $this->now();
        }
        if (in_array($status, ['completed', 'completed_with_warnings', 'failed', 'cancelled', 'rolled_back', 'rolled_back_with_warnings', 'rollback_failed'], true)) {
            $set[] = 'finished_at=:f';
            $params['f'] = $this->now();
        }

        $where = ' WHERE public_id=:id';
        if ($lease !== null) {
            // Validate ownership in the same UPDATE that changes the status.
            // A follow-up lease lookup is incorrect here: after a successful
            // terminal transition the row is no longer running/rolling_back.
            $where .= " AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back','pausing','cancelling')";
            $params['l'] = $lease;
        }

        $statement = $this->pdo->prepare('UPDATE module_clickup_jobs SET ' . implode(',', $set) . $where);
        $statement->execute($params);
        if ($lease !== null && $statement->rowCount() !== 1) {
            throw new RuntimeException('CLICKUP_JOB_LEASE_LOST');
        }
    }
    private function leaseTokenMatches(string $id,string $lease): bool
    { $s=$this->pdo->prepare('SELECT id FROM module_clickup_jobs WHERE public_id=:id AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() LIMIT 1');$s->execute(['id'=>$id,'l'=>$lease]);return $s->fetchColumn()!==false; }
    public function beginRollback(string $id, int $seconds = 900): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $s = $this->pdo->prepare('SELECT id,status,last_source_cursor,lease_until,(lease_until IS NOT NULL AND lease_until < UTC_TIMESTAMP()) AS lease_expired FROM module_clickup_jobs WHERE public_id=:id LIMIT 1 FOR UPDATE');
            $s->execute(['id' => $id]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $this->pdo->commit();
                return null;
            }

            $status = (string)$row['status'];
            $expiredRollback = $status === 'rolling_back' && (int)($row['lease_expired'] ?? 0) === 1;
            $terminal = in_array($status, ['completed', 'completed_with_warnings', 'failed', 'cancelled', 'rolled_back_with_warnings', 'rollback_failed'], true);
            if (!$terminal && !$expiredRollback) {
                $this->pdo->commit();
                return null;
            }

            $token = bin2hex(random_bytes(16));
            $until = gmdate('Y-m-d H:i:s', time() + max(60, $seconds));
            $cursor = (string)($row['last_source_cursor'] ?? '');
            $decoded = json_decode($cursor, true);
            // A completed rollback with warnings may have left individual
            // items in rollback_failed. Restart its scan from the top so a
            // later explicit rollback can retry those items instead of
            // inheriting a cursor that already passed them.
            $retryRollback = in_array($status, ['rolled_back_with_warnings', 'rollback_failed'], true);
            if ($retryRollback || !$terminal || !is_array($decoded) || ($decoded['phase'] ?? '') !== 'rollback') {
                $cursor = json_encode(['phase' => 'rollback', 'before_id' => PHP_INT_MAX], JSON_UNESCAPED_UNICODE);
            }
            $whereStatus = $expiredRollback ? "status='rolling_back'" : 'status=:s';
            $params = ['l' => $token, 'u' => $until, 'c' => $cursor, 'id' => (int)$row['id']];
            if (!$expiredRollback) $params['s'] = $status;
            $u = $this->pdo->prepare("UPDATE module_clickup_jobs SET status='rolling_back',lease_token=:l,lease_until=:u,last_source_cursor=:c,updated_at=UTC_TIMESTAMP() WHERE id=:id AND {$whereStatus}");
            $u->execute($params);
            if ($u->rowCount() !== 1) {
                $this->pdo->rollBack();
                return null;
            }
            $this->pdo->commit();
            return $this->getJob($id);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function updateProgress(string $id,string $step,float $percent,array $progress,?string $lease=null): void
    { $p=['id'=>$id,'s'=>$step,'pc'=>max(0,min(100,$percent)),'pr'=>$this->json($progress)];$w=' WHERE public_id=:id';if($lease!==null){$w.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back')";$p['l']=$lease;}$this->pdo->prepare('UPDATE module_clickup_jobs SET current_step=:s,progress_percent=:pc,progress_json=:pr,updated_at=UTC_TIMESTAMP()'.$w)->execute($p);if($lease!==null&&!$this->leaseTokenMatches($id,$lease))throw new RuntimeException('CLICKUP_JOB_LEASE_LOST'); }
    public function updateSummary(string $id,array $summary,?string $lease=null): void
    { $p=['id'=>$id,'s'=>$this->json($summary)];$w=' WHERE public_id=:id';if($lease!==null){$w.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back')";$p['l']=$lease;}$this->pdo->prepare('UPDATE module_clickup_jobs SET summary_json=:s,updated_at=UTC_TIMESTAMP()'.$w)->execute($p);if($lease!==null&&!$this->leaseTokenMatches($id,$lease))throw new RuntimeException('CLICKUP_JOB_LEASE_LOST'); }
    public function claimNextJob(int $seconds=240): ?array
    { $this->pdo->beginTransaction();try{$s=$this->pdo->query("SELECT j.id FROM module_clickup_jobs j WHERE (j.status='queued' OR (j.status='running' AND j.lease_until<UTC_TIMESTAMP())) AND NOT EXISTS (SELECT 1 FROM module_clickup_jobs a WHERE a.connection_id=j.connection_id AND (a.status='rolling_back' OR (a.status='running' AND a.lease_until>=UTC_TIMESTAMP()))) ORDER BY j.created_at ASC LIMIT 1 FOR UPDATE");$id=$s->fetchColumn();if($id===false){$this->pdo->commit();return null;}$l=bin2hex(random_bytes(16));$u=gmdate('Y-m-d H:i:s',time()+max(60,$seconds));$this->pdo->prepare("UPDATE module_clickup_jobs SET status='running',lease_token=:l,lease_until=:u,started_at=COALESCE(started_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['l'=>$l,'u'=>$u,'id'=>$id]);$this->pdo->commit();$s=$this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_clickup_jobs j JOIN module_clickup_connections c ON c.id=j.connection_id WHERE j.id=:id');$s->execute(['id'=>$id]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$this->decodeJob($r):null;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;} }
    public function heartbeat(string $id,string $lease,int $seconds=240): bool
    { $s=$this->pdo->prepare("UPDATE module_clickup_jobs SET lease_until=:u,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back')");$s->execute(['u'=>gmdate('Y-m-d H:i:s',time()+max(60,$seconds)),'id'=>$id,'l'=>$lease]);return $this->ownsLease($id,$lease); }
    public function ownsLease(string $id,string $lease): bool
    { $s=$this->pdo->prepare("SELECT id FROM module_clickup_jobs WHERE public_id=:id AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back') LIMIT 1");$s->execute(['id'=>$id,'l'=>$lease]);return $s->fetchColumn()!==false; }
    public function releaseLease(string $id,string $lease): void
    { $this->pdo->prepare('UPDATE module_clickup_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:id AND lease_token=:l')->execute(['id'=>$id,'l'=>$lease]); }
    public function retryJob(string $id): ?int
    { $this->pdo->beginTransaction();try{$s=$this->pdo->prepare("SELECT id FROM module_clickup_jobs WHERE public_id=:id AND status IN ('completed_with_warnings','failed','cancelled') LIMIT 1 FOR UPDATE");$s->execute(['id'=>$id]);$j=$s->fetch(PDO::FETCH_ASSOC);if(!is_array($j)){$this->pdo->commit();return null;}$u=$this->pdo->prepare("UPDATE module_clickup_job_items SET status='pending',error_code=NULL,error_message=NULL,updated_at=UTC_TIMESTAMP() WHERE job_id=:j AND status='failed'");$u->execute(['j'=>(int)$j['id']]);$count=$u->rowCount();$this->pdo->prepare("UPDATE module_clickup_jobs SET status='queued',last_source_cursor=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['id'=>(int)$j['id']]);$this->pdo->commit();return $count;}catch(\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;} }

    public function findItem(int $job,string $type,string $source): ?array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_job_items WHERE job_id=:j AND source_type=:t AND source_id=:s LIMIT 1');$s->execute(['j'=>$job,'t'=>$type,'s'=>$source]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function upsertItem(int $job,string $type,string $source,array $d): void
    { $old=$this->findItem($job,$type,$source);$fields=['source_parent_id','source_project_id','target_type','target_public_id','created_by_job','status','checksum','source_updated_at','attempts','error_code','error_message','payload_json'];if($old){if(($d['status']??null)==='pending'&&(string)($old['checksum']??'')===(string)($d['checksum']??'')&&in_array((string)$old['status'],['imported','updated','skipped'],true))unset($d['status']);$set=['updated_at=:u'];$p=['id'=>$old['id'],'u'=>$this->now()];foreach($fields as $f)if(array_key_exists($f,$d)){$set[]="$f=:$f";$p[$f]=$f==='payload_json'&&is_array($d[$f])?$this->json($d[$f]):$d[$f];}$this->pdo->prepare('UPDATE module_clickup_job_items SET '.implode(',',$set).' WHERE id=:id')->execute($p);return;} $s=$this->pdo->prepare('INSERT INTO module_clickup_job_items (job_id,source_type,source_id,source_parent_id,source_project_id,target_type,target_public_id,created_by_job,status,checksum,source_updated_at,attempts,error_code,error_message,payload_json,created_at,updated_at) VALUES (:j,:t,:s,:p,:sp,:tt,:tp,:cb,:st,:c,:su,:a,:ec,:em,:pl,:cr,:u)');$s->execute(['j'=>$job,'t'=>$type,'s'=>$source,'p'=>$d['source_parent_id']??null,'sp'=>$d['source_project_id']??null,'tt'=>$d['target_type']??null,'tp'=>$d['target_public_id']??null,'cb'=>!empty($d['created_by_job'])?1:0,'st'=>$d['status']??'pending','c'=>$d['checksum']??null,'su'=>$d['source_updated_at']??null,'a'=>$d['attempts']??0,'ec'=>$d['error_code']??null,'em'=>$d['error_message']??null,'pl'=>isset($d['payload_json'])?(is_array($d['payload_json'])?$this->json($d['payload_json']):$d['payload_json']):null,'cr'=>$this->now(),'u'=>$this->now()]); }
    public function items(int $job,?string $status=null,int $limit=200): array
    { $sql='SELECT * FROM module_clickup_job_items WHERE job_id=:j';$p=['j'=>$job];if($status!==null&&$status!==''){$sql.=' AND status=:s';$p['s']=$status;}$sql.=' ORDER BY id ASC LIMIT '.max(1,min(1000,$limit));$s=$this->pdo->prepare($sql);$s->execute($p);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function itemCount(int $job): int
    { $s=$this->pdo->prepare('SELECT COUNT(*) FROM module_clickup_job_items WHERE job_id=:j');$s->execute(['j'=>$job]);return (int)$s->fetchColumn(); }
    public function itemCounts(int $job): array
    { $s=$this->pdo->prepare('SELECT status,COUNT(*) count FROM module_clickup_job_items WHERE job_id=:j GROUP BY status');$s->execute(['j'=>$job]);$r=[];foreach($s->fetchAll(PDO::FETCH_ASSOC)?:[] as $x)$r[(string)$x['status']]=(int)$x['count'];return $r; }
    public function resetParentFailures(int $job): int
    {
        $sql = "UPDATE module_clickup_job_items i
                JOIN module_clickup_jobs j ON j.id = i.job_id
                SET i.status='pending', i.error_code=NULL, i.error_message=NULL, i.updated_at=UTC_TIMESTAMP()
                WHERE i.job_id=:j AND i.source_type='task' AND i.status='failed'
                  AND i.error_code='PARENT_TASK_NOT_READY'
                  AND EXISTS (
                    SELECT 1 FROM module_clickup_source_mappings m
                    WHERE m.connection_id=j.connection_id
                      AND m.source_type='task'
                      AND m.source_id=i.source_parent_id
                      AND m.target_public_id IS NOT NULL
                      AND m.target_public_id<>''
                  )";
        $s = $this->pdo->prepare($sql);
        $s->execute(['j' => $job]);
        return $s->rowCount();
    }
    public function importItemsBatch(int $job,int $priority=0,int $lastId=0,int $limit=100): array
    { $limit=max(1,min(500,$limit));$sql="SELECT * FROM (SELECT i.*,CASE i.source_type WHEN 'team' THEN 5 WHEN 'space' THEN 10 WHEN 'folder' THEN 20 WHEN 'list' THEN 25 WHEN 'custom_field' THEN 30 WHEN 'tag' THEN 35 WHEN 'task' THEN CASE WHEN i.source_parent_id IS NULL OR i.source_parent_id='' THEN 40 ELSE 45 END WHEN 'checklist' THEN 50 WHEN 'checklist_item' THEN 55 WHEN 'comment' THEN 60 WHEN 'time_entry' THEN 70 WHEN 'attachment' THEN 75 WHEN 'dependency' THEN 80 WHEN 'goal' THEN 90 WHEN 'user' THEN 95 ELSE 100 END import_priority FROM module_clickup_job_items i WHERE i.job_id=:j AND i.status='pending') x WHERE x.import_priority>:p OR (x.import_priority=:sp AND x.id>:i) ORDER BY x.import_priority ASC,x.id ASC LIMIT {$limit}";$s=$this->pdo->prepare($sql);$s->execute(['j'=>$job,'p'=>$priority,'sp'=>$priority,'i'=>$lastId]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function rollbackItemsBatch(int $job, int $before, int $limit = 100): array
    {
        $sql = "SELECT * FROM module_clickup_job_items WHERE job_id=:j AND target_public_id IS NOT NULL AND status NOT IN ('rolled_back','rollback_preserved_shared') AND id<:b AND (created_by_job=1 OR (source_type='comment' AND target_type='project' AND status IN ('updated','rollback_failed'))) ORDER BY id DESC LIMIT " . max(1, min(500, $limit));
        $s = $this->pdo->prepare($sql);
        $s->execute(['j' => $job, 'b' => $before]);
        return $s->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    public function targetReferencedByOtherJob(int $jobId, string $targetType, string $targetPublicId): bool
    {
        $stmt=$this->pdo->prepare("SELECT i.id FROM module_clickup_job_items i JOIN module_clickup_jobs j ON j.id=i.job_id WHERE i.job_id<>:job AND i.target_type=:type AND i.target_public_id=:target AND i.status NOT IN ('rolled_back','rollback_failed') LIMIT 1");
        $stmt->execute(['job'=>$jobId,'type'=>$targetType,'target'=>$targetPublicId]);
        return $stmt->fetchColumn()!==false;
    }

    public function updateCursor(string $id,string $cursor,?string $lease=null): void
    { $p=['id'=>$id,'c'=>$cursor];$w=' WHERE public_id=:id';if($lease!==null){$w.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','rolling_back')";$p['l']=$lease;}$this->pdo->prepare('UPDATE module_clickup_jobs SET last_source_cursor=:c,updated_at=UTC_TIMESTAMP()'.$w)->execute($p);if($lease!==null&&!$this->leaseTokenMatches($id,$lease))throw new RuntimeException('CLICKUP_JOB_LEASE_LOST'); }

    public function findMapping(int $connection,string $type,string $source): ?array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_source_mappings WHERE connection_id=:c AND source_type=:t AND source_id=:s LIMIT 1');$s->execute(['c'=>$connection,'t'=>$type,'s'=>$source]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function findLabelMappingByName(int $connection,string $name): ?array
    {
        $s=$this->pdo->prepare("SELECT m.* FROM module_clickup_source_mappings m JOIN module_clickup_job_items i ON i.source_type='label' AND i.source_id=m.source_id AND i.job_id=m.created_by_job_id WHERE m.connection_id=:c AND JSON_UNQUOTE(JSON_EXTRACT(i.payload_json,'$.name'))=:n ORDER BY m.updated_at DESC LIMIT 1");
        $s->execute(['c'=>$connection,'n'=>$name]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null;
    }

    public function upsertMapping(int $connection,string $type,string $source,array $d): array
    { $old=$this->findMapping($connection,$type,$source);$n=$this->now();if($old){$s=$this->pdo->prepare('UPDATE module_clickup_source_mappings SET source_parent_id=:p,target_type=:tt,target_public_id=:tp,source_checksum=:sc,target_checksum=:tc,state=:st,created_by_job_id=COALESCE(created_by_job_id,:j),last_seen_at=:ls,updated_at=:u WHERE id=:id');$s->execute(['p'=>$d['source_parent_id']??$old['source_parent_id'],'tt'=>$d['target_type']??$old['target_type'],'tp'=>$d['target_public_id']??$old['target_public_id'],'sc'=>$d['source_checksum']??$old['source_checksum'],'tc'=>$d['target_checksum']??$old['target_checksum'],'st'=>$d['state']??'active','j'=>$d['created_by_job_id']??$old['created_by_job_id'],'ls'=>$n,'u'=>$n,'id'=>$old['id']]);return $this->findMapping($connection,$type,$source)??$old;} $pid=$this->id('cum');$s=$this->pdo->prepare('INSERT INTO module_clickup_source_mappings (public_id,connection_id,source_type,source_id,source_parent_id,target_type,target_public_id,source_checksum,target_checksum,state,created_by_job_id,last_seen_at,created_at,updated_at) VALUES (:p,:c,:t,:s,:sp,:tt,:tp,:sc,:tc,:st,:j,:ls,:cr,:u)');$s->execute(['p'=>$pid,'c'=>$connection,'t'=>$type,'s'=>$source,'sp'=>$d['source_parent_id']??null,'tt'=>$d['target_type']??null,'tp'=>$d['target_public_id']??null,'sc'=>$d['source_checksum']??null,'tc'=>$d['target_checksum']??null,'st'=>$d['state']??'active','j'=>$d['created_by_job_id']??null,'ls'=>$n,'cr'=>$n,'u'=>$n]);return $this->findMapping($connection,$type,$source)??['public_id'=>$pid]; }
    public function upsertUserMapping(int $connection,array $user): void
    { $s=$this->pdo->prepare("INSERT INTO module_clickup_user_mappings (connection_id,clickup_user_id,display_name,email,mapping_status,created_at,updated_at) VALUES (:c,:i,:n,:e,'unmapped',:cr,:u) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),email=VALUES(email),updated_at=VALUES(updated_at)");$s->execute(['c'=>$connection,'i'=>(string)($user['id']??$user['user_id']??''),'n'=>$user['name']??$user['full_name']??null,'e'=>$user['email']??null,'cr'=>$this->now(),'u'=>$this->now()]); }
    public function listUserMappings(int $connection): array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_user_mappings WHERE connection_id=:c ORDER BY display_name ASC');$s->execute(['c'=>$connection]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function updateUserMapping(int $connection, int $id, ?string $crm): bool
    {
        $s = $this->pdo->prepare('UPDATE module_clickup_user_mappings SET crm_user_public_id=:u,mapping_status=:s,updated_at=UTC_TIMESTAMP() WHERE id=:i AND connection_id=:c');
        $s->execute(['u' => $crm ?: null, 's' => $crm ? 'mapped' : 'unmapped', 'i' => $id, 'c' => $connection]);
        if ($s->rowCount() > 0) return true;
        $check = $this->pdo->prepare('SELECT id FROM module_clickup_user_mappings WHERE id=:i AND connection_id=:c LIMIT 1');
        $check->execute(['i' => $id, 'c' => $connection]);
        return $check->fetchColumn() !== false;
    }
    public function addLog(int $job,string $level,string $step,string $message,array $context=[]): void
    { $s=$this->pdo->prepare('INSERT INTO module_clickup_job_logs (job_id,level,step,message,context_json,created_at) VALUES (:j,:l,:s,:m,:c,:n)');$s->execute(['j'=>$job,'l'=>$level,'s'=>$step,'m'=>mb_substr($message,0,2000),'c'=>$context===[]?null:$this->json($context),'n'=>$this->now()]); }
    public function logs(int $job,int $limit=100): array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_job_logs WHERE job_id=:j ORDER BY id DESC LIMIT '.max(1,min(1000,$limit)));$s->execute(['j'=>$job]);return $s->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function report(string $id): array
    { $j=$this->getJob($id);if(!$j)return[];$j['items']=$this->itemCounts((int)$j['id']);$j['logs']=$this->logs((int)$j['id']);return $j; }
    public function unresolved(int $job,string $type,string $source,string $code,string $text,array $payload=[]): void
    { $s=$this->pdo->prepare('INSERT INTO module_clickup_unresolved_entities (job_id,source_type,source_id,reason_code,reason_text,payload_json,created_at) VALUES (:j,:t,:s,:c,:x,:p,:n)');$s->execute(['j'=>$job,'t'=>$type,'s'=>$source,'c'=>$code,'x'=>$text,'p'=>$payload===[]?null:$this->json($payload),'n'=>$this->now()]); }
    public function rateState(int $connection): ?array
    { $s=$this->pdo->prepare('SELECT * FROM module_clickup_rate_limits WHERE connection_id=:c LIMIT 1');$s->execute(['c'=>$connection]);$r=$s->fetch(PDO::FETCH_ASSOC);return is_array($r)?$r:null; }
    public function recordRequest(int $connection,int $status,?int $retryAfter=null): void
    {
        $old = $this->rateState($connection) ?? [];
        $start = (string)($old['window_started_at'] ?? '');
        $startTimestamp = $this->utcTimestamp($start);
        $now = time();
        $windowActive = $startTimestamp !== null && $startTimestamp <= $now && ($now - $startTimestamp) < 900;
        $windowStart = $windowActive ? $start : $this->now();
        $count = $windowActive ? (int)($old['requests_made'] ?? 0) : 0;
        $this->upsertRate($connection, [
            'requests_made' => $count + 1,
            'window_started_at' => $windowStart,
            'retry_after_until' => $retryAfter !== null ? gmdate('Y-m-d H:i:s', $now + $retryAfter) : null,
            'last_http_status' => $status,
        ]);
    }
    private function upsertRate(int $connection,array $d): void
    { $s=$this->pdo->prepare('INSERT INTO module_clickup_rate_limits (connection_id,requests_made,window_started_at,retry_after_until,last_http_status,updated_at) VALUES (:c,:r,:w,:ra,:s,:u) ON DUPLICATE KEY UPDATE requests_made=VALUES(requests_made),window_started_at=VALUES(window_started_at),retry_after_until=VALUES(retry_after_until),last_http_status=VALUES(last_http_status),updated_at=VALUES(updated_at)');$s->execute(['c'=>$connection,'r'=>(int)($d['requests_made']??0),'w'=>$d['window_started_at']??$this->now(),'ra'=>$d['retry_after_until']??null,'s'=>$d['last_http_status']??null,'u'=>$this->now()]); }
}
