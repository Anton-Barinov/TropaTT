<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Repository;

use PDO;
use RuntimeException;

final class ShtabMigrationRepository
{
    public function __construct(private readonly PDO $pdo) {}
    private function id(string $prefix): string { return $prefix . '_' . bin2hex(random_bytes(10)); }
    private function now(): string { return gmdate('Y-m-d H:i:s'); }
    private function json(mixed $value): string { return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); }

    public function actor(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT id,public_id,login,full_name,is_root,is_active FROM users WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return ($row = $stmt->fetch(PDO::FETCH_ASSOC)) ?: ['id' => $id, 'is_root' => 0];
    }

    public function activeUserPublicId(string $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT public_id FROM users WHERE public_id=:id AND is_active=1 LIMIT 1');
        $stmt->execute(['id' => $id]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (string)$value;
    }

    public function mappedUserId(int $connection, string $sourceId): ?int
    {
        $stmt = $this->pdo->prepare("SELECT u.id FROM module_shtab_user_mappings m JOIN users u ON u.public_id=m.crm_user_public_id WHERE m.connection_id=:c AND m.shtab_user_id=:s AND m.mapping_status='mapped' AND u.is_active=1 LIMIT 1");
        $stmt->execute(['c' => $connection, 's' => $sourceId]);
        $value = $stmt->fetchColumn();
        return $value === false ? null : (int)$value;
    }

    public function listUserMappings(int $connection): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_shtab_user_mappings WHERE connection_id=:c ORDER BY display_name ASC');
        $stmt->execute(['c' => $connection]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int,array<string,mixed>> */
    public function activeCrmUsers(): array
    {
        $stmt=$this->pdo->query("SELECT id,public_id,login,full_name FROM users WHERE is_active=1 ORDER BY full_name ASC,login ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public function upsertUserMapping(int $connection, array $user): void
    {
        $source = trim((string)($user['id'] ?? $user['user_id'] ?? $user['email'] ?? ''));
        if ($source === '') return;
        $stmt = $this->pdo->prepare("INSERT INTO module_shtab_user_mappings (connection_id,shtab_user_id,display_name,email,mapping_status,created_at,updated_at) VALUES (:c,:s,:n,:e,'unmapped',:cr,:up) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),email=VALUES(email),updated_at=VALUES(updated_at)");
        $now = $this->now();
        $stmt->execute(['c' => $connection, 's' => $source, 'n' => $user['name'] ?? $user['full_name'] ?? $user['display_name'] ?? null, 'e' => $user['email'] ?? null, 'cr' => $now, 'up' => $now]);
    }

    public function updateUserMapping(int $connection, int $mappingId, ?string $crmPublicId): bool
    {
        $status = $crmPublicId !== null && $crmPublicId !== '' ? 'mapped' : 'unmapped';
        $stmt = $this->pdo->prepare('UPDATE module_shtab_user_mappings SET crm_user_public_id=:u,mapping_status=:s,updated_at=UTC_TIMESTAMP() WHERE id=:i AND connection_id=:c');
        $stmt->execute(['u' => $crmPublicId ?: null, 's' => $status, 'i' => $mappingId, 'c' => $connection]);
        if ($stmt->rowCount() > 0) return true;
        $check = $this->pdo->prepare('SELECT id FROM module_shtab_user_mappings WHERE id=:i AND connection_id=:c LIMIT 1');
        $check->execute(['i' => $mappingId, 'c' => $connection]);
        return $check->fetchColumn() !== false;
    }

    public function listConnections(int $actorId, bool $manager): array
    {
        $sql = 'SELECT id,public_id,name,status,created_by_user_id,created_at,updated_at FROM module_shtab_connections';
        $params = [];
        if (!$manager) { $sql .= ' WHERE created_by_user_id=:o'; $params['o'] = $actorId; }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function getConnection(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_shtab_connections WHERE public_id=:id LIMIT 1');
        $stmt->execute(['id' => $publicId]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function getConnectionById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM module_shtab_connections WHERE id=:id LIMIT 1');
        $stmt->execute(['id' => $id]); $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function createConnection(string $name, int $owner): array
    {
        $publicId = $this->id('shc'); $now = $this->now();
        $stmt = $this->pdo->prepare("INSERT INTO module_shtab_connections (public_id,name,status,created_by_user_id,created_at,updated_at) VALUES (:p,:n,'active',:o,:cr,:up)");
        $stmt->execute(['p' => $publicId, 'n' => $name, 'o' => $owner, 'cr' => $now, 'up' => $now]);
        return $this->getConnection($publicId) ?? ['public_id' => $publicId];
    }

    public function hasRunningJobs(int $connection): bool
    {
        $stmt = $this->pdo->prepare("SELECT id FROM module_shtab_jobs WHERE connection_id=:c AND status IN ('queued','running','pausing','cancelling','rolling_back') LIMIT 1");
        $stmt->execute(['c' => $connection]); return $stmt->fetchColumn() !== false;
    }

    public function cleanupJobFile(int $job): void
    {
        $stmt=$this->pdo->prepare('SELECT source_file_path FROM module_shtab_jobs WHERE id=:j LIMIT 1');
        $stmt->execute(['j'=>$job]);
        $path=$stmt->fetchColumn();
        if(is_string($path)&&$path!==''&&is_file($path))@unlink($path);
    }

    public function deleteConnection(int $connection): void
    {
        $this->pdo->beginTransaction();
        try {
            $files = $this->pdo->prepare('SELECT source_file_path FROM module_shtab_jobs WHERE connection_id=:c');
            $files->execute(['c' => $connection]);
            foreach ($files->fetchAll(PDO::FETCH_COLUMN) ?: [] as $path) if (is_string($path) && is_file($path)) @unlink($path);
            foreach (['module_shtab_unresolved_entities'=>'job_id IN (SELECT id FROM module_shtab_jobs WHERE connection_id=:c)','module_shtab_job_logs'=>'job_id IN (SELECT id FROM module_shtab_jobs WHERE connection_id=:c)','module_shtab_job_items'=>'job_id IN (SELECT id FROM module_shtab_jobs WHERE connection_id=:c)','module_shtab_jobs'=>'connection_id=:c','module_shtab_source_mappings'=>'connection_id=:c','module_shtab_user_mappings'=>'connection_id=:c'] as $table => $where) $this->pdo->prepare('DELETE FROM '.$table.' WHERE '.$where)->execute(['c' => $connection]);
            $this->pdo->prepare('DELETE FROM module_shtab_connections WHERE id=:c')->execute(['c' => $connection]);
            $this->pdo->commit();
        } catch (\Throwable $e) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); throw $e; }
    }

    private function decodeJob(array $row): array
    {
        foreach (['source_scope_json'=>'source_scope','progress_json'=>'progress','summary_json'=>'summary'] as $from => $to) if (array_key_exists($from, $row)) $row[$to] = json_decode((string)$row[$from], true) ?: [];
        return $row;
    }

    public function createJob(array $data): array
    {
        $publicId = $this->id('shj'); $now = $this->now();
        $stmt = $this->pdo->prepare("INSERT INTO module_shtab_jobs (public_id,connection_id,mode,status,source_scope_json,source_file_path,source_file_name,created_by_user_id,created_at,updated_at) VALUES (:p,:c,:m,'draft',:s,:f,:n,:o,:cr,:up)");
        $stmt->execute(['p'=>$publicId,'c'=>$data['connection_id'],'m'=>$data['mode'] ?? 'import','s'=>$this->json($data['source_scope'] ?? []),'f'=>$data['source_file_path'],'n'=>$data['source_file_name'],'o'=>$data['created_by_user_id'],'cr'=>$now,'up'=>$now]);
        return $this->getJob($publicId) ?? ['public_id'=>$publicId];
    }

    public function getJob(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_shtab_jobs j JOIN module_shtab_connections c ON c.id=j.connection_id WHERE j.public_id=:p LIMIT 1');
        $stmt->execute(['p'=>$publicId]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->decodeJob($row) : null;
    }

    public function listJobs(int $actorId, bool $manager): array
    {
        $sql='SELECT j.id,j.public_id,j.connection_id,j.mode,j.status,j.source_scope_json,j.source_file_name,j.progress_json,j.summary_json,j.current_step,j.progress_percent,j.started_at,j.finished_at,j.created_by_user_id,j.created_at,j.updated_at,c.name AS connection_name FROM module_shtab_jobs j JOIN module_shtab_connections c ON c.id=j.connection_id'; $params=[];
        if (!$manager) { $sql.=' WHERE j.created_by_user_id=:o'; $params['o']=$actorId; } $sql.=' ORDER BY j.created_at DESC';
        $stmt=$this->pdo->prepare($sql);$stmt->execute($params);return array_map([$this,'decodeJob'],$stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function requestStatus(string $publicId, string $status): bool
    {
        $set = 'status=:s,updated_at=UTC_TIMESTAMP()';
        if (in_array($status, ['cancelled'], true)) {
            $set .= ',finished_at=UTC_TIMESTAMP(),lease_token=NULL,lease_until=NULL';
        }
        $stmt=$this->pdo->prepare("UPDATE module_shtab_jobs SET {$set} WHERE public_id=:p AND status IN ('draft','queued','running','paused','pausing','cancelling','failed','cancelled','completed_with_warnings')");
        $stmt->execute(['s'=>$status,'p'=>$publicId]);
        return $stmt->rowCount()===1;
    }

    public function updateJobStatus(string $publicId, string $status, ?string $lease = null): void
    {
        $set=['status=:s','updated_at=:u'];$params=['p'=>$publicId,'s'=>$status,'u'=>$this->now()];
        if (in_array($status,['queued','running'],true)) {$set[]='started_at=COALESCE(started_at,:st)';$params['st']=$this->now();}
        if (in_array($status,['completed','completed_with_warnings','failed','cancelled','rolled_back','rolled_back_with_warnings'],true)) {$set[]='finished_at=:f';$params['f']=$this->now();}
        $where=' WHERE public_id=:p';
        if ($lease!==null) {$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling')";$params['l']=$lease;}
        $stmt=$this->pdo->prepare('UPDATE module_shtab_jobs SET '.implode(',',$set).$where);$stmt->execute($params);
        if ($lease!==null&&$stmt->rowCount()!==1) throw new RuntimeException('SHTAB_JOB_LEASE_LOST');
    }

    public function claimNextJob(int $seconds=240): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->query("SELECT id FROM module_shtab_jobs WHERE status='queued' OR (status IN ('running','pausing','cancelling') AND lease_until<UTC_TIMESTAMP()) ORDER BY created_at ASC LIMIT 1 FOR UPDATE");$id=$stmt->fetchColumn();
            if ($id===false){$this->pdo->commit();return null;}$lease=bin2hex(random_bytes(16));$until=gmdate('Y-m-d H:i:s',time()+max(60,$seconds));
            $this->pdo->prepare("UPDATE module_shtab_jobs SET status=CASE WHEN status='queued' THEN 'running' ELSE status END,lease_token=:l,lease_until=:u,started_at=COALESCE(started_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:i")->execute(['l'=>$lease,'u'=>$until,'i'=>$id]);$this->pdo->commit();
            $stmt=$this->pdo->prepare('SELECT j.*,c.name AS connection_name FROM module_shtab_jobs j JOIN module_shtab_connections c ON c.id=j.connection_id WHERE j.id=:i');$stmt->execute(['i'=>$id]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return is_array($row)?$this->decodeJob($row):null;
        } catch (\Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function heartbeat(string $publicId,string $lease,int $seconds=240): bool
    { $stmt=$this->pdo->prepare("UPDATE module_shtab_jobs SET lease_until=:u,updated_at=UTC_TIMESTAMP() WHERE public_id=:p AND lease_token=:l AND status IN ('running','pausing','cancelling')");$stmt->execute(['u'=>gmdate('Y-m-d H:i:s',time()+max(60,$seconds)),'p'=>$publicId,'l'=>$lease]);return $this->ownsLease($publicId,$lease); }
    public function ownsLease(string $publicId,string $lease): bool
    { $stmt=$this->pdo->prepare("SELECT id FROM module_shtab_jobs WHERE public_id=:p AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling') LIMIT 1");$stmt->execute(['p'=>$publicId,'l'=>$lease]);return $stmt->fetchColumn()!==false; }
    public function releaseLease(string $publicId,string $lease): void
    { $this->pdo->prepare('UPDATE module_shtab_jobs SET lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE public_id=:p AND lease_token=:l')->execute(['p'=>$publicId,'l'=>$lease]); }

    public function updateProgress(string $publicId,string $step,float $percent,array $progress,?string $lease=null): void
    { $params=['p'=>$publicId,'s'=>$step,'pc'=>max(0,min(100,$percent)),'pr'=>$this->json($progress)];$where=' WHERE public_id=:p';if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling')";$params['l']=$lease;}$stmt=$this->pdo->prepare('UPDATE module_shtab_jobs SET current_step=:s,progress_percent=:pc,progress_json=:pr,updated_at=UTC_TIMESTAMP()'.$where);$stmt->execute($params);if($lease!==null&&!$this->ownsLease($publicId,$lease))throw new RuntimeException('SHTAB_JOB_LEASE_LOST'); }
    public function updateSummary(string $publicId,array $summary,?string $lease=null): void
    { $params=['p'=>$publicId,'s'=>$this->json($summary)];$where=' WHERE public_id=:p';if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling')";$params['l']=$lease;}$stmt=$this->pdo->prepare('UPDATE module_shtab_jobs SET summary_json=:s,updated_at=UTC_TIMESTAMP()'.$where);$stmt->execute($params);if($lease!==null&&!$this->ownsLease($publicId,$lease))throw new RuntimeException('SHTAB_JOB_LEASE_LOST'); }
    public function updateCursor(string $publicId,string $cursor,?string $lease=null): void { $params=['p'=>$publicId,'c'=>$cursor];$where=' WHERE public_id=:p';if($lease!==null){$where.=" AND lease_token=:l AND lease_until>=UTC_TIMESTAMP() AND status IN ('running','pausing','cancelling')";$params['l']=$lease;}$stmt=$this->pdo->prepare('UPDATE module_shtab_jobs SET last_source_cursor=:c,updated_at=UTC_TIMESTAMP()'.$where);$stmt->execute($params);if($lease!==null&&!$this->ownsLease($publicId,$lease))throw new RuntimeException('SHTAB_JOB_LEASE_LOST'); }

    public function findItem(int $job,string $type,string $source): ?array
    { $stmt=$this->pdo->prepare('SELECT * FROM module_shtab_job_items WHERE job_id=:j AND source_type=:t AND source_id=:s LIMIT 1');$stmt->execute(['j'=>$job,'t'=>$type,'s'=>$source]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return is_array($row)?$row:null; }
    public function upsertItem(int $job,string $type,string $source,array $data): void
    { $old=$this->findItem($job,$type,$source);$fields=['source_parent_id','target_type','target_public_id','created_by_job','status','checksum','payload_json','attempts','error_code','error_message'];if($old){if(($data['status']??null)==='pending'&&(string)($old['checksum']??'')===(string)($data['checksum']??'')&&in_array((string)$old['status'],['imported','updated','skipped'],true))unset($data['status']);$set=['updated_at=:u'];$p=['id'=>$old['id'],'u'=>$this->now()];foreach($fields as $f)if(array_key_exists($f,$data)){$set[]=$f.'=:'.$f;$p[$f]=$f==='payload_json'&&is_array($data[$f])?$this->json($data[$f]):$data[$f];}$this->pdo->prepare('UPDATE module_shtab_job_items SET '.implode(',',$set).' WHERE id=:id')->execute($p);return;} $stmt=$this->pdo->prepare('INSERT INTO module_shtab_job_items (job_id,source_type,source_id,source_parent_id,target_type,target_public_id,created_by_job,status,checksum,payload_json,attempts,error_code,error_message,created_at,updated_at) VALUES (:j,:t,:s,:p,:tt,:tp,:cb,:st,:c,:pl,:a,:ec,:em,:cr,:u)');$stmt->execute(['j'=>$job,'t'=>$type,'s'=>$source,'p'=>$data['source_parent_id']??null,'tt'=>$data['target_type']??null,'tp'=>$data['target_public_id']??null,'cb'=>!empty($data['created_by_job'])?1:0,'st'=>$data['status']??'pending','c'=>$data['checksum']??null,'pl'=>isset($data['payload_json'])?(is_array($data['payload_json'])?$this->json($data['payload_json']):$data['payload_json']):null,'a'=>$data['attempts']??0,'ec'=>$data['error_code']??null,'em'=>$data['error_message']??null,'cr'=>$this->now(),'u'=>$this->now()]); }
    public function itemCount(int $job): int { $stmt=$this->pdo->prepare('SELECT COUNT(*) FROM module_shtab_job_items WHERE job_id=:j');$stmt->execute(['j'=>$job]);return(int)$stmt->fetchColumn(); }
    public function itemCounts(int $job): array { $stmt=$this->pdo->prepare('SELECT status,COUNT(*) AS count FROM module_shtab_job_items WHERE job_id=:j GROUP BY status');$stmt->execute(['j'=>$job]);$result=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC)?:[] as $row)$result[(string)$row['status']]=(int)$row['count'];return$result; }
    public function items(int $job,?string $status=null,int $limit=500): array { $sql='SELECT * FROM module_shtab_job_items WHERE job_id=:j';$p=['j'=>$job];if($status!==null&&$status!==''){$sql.=' AND status=:s';$p['s']=$status;}$sql.=' ORDER BY id ASC LIMIT '.max(1,min(100000,$limit));$stmt=$this->pdo->prepare($sql);$stmt->execute($p);return$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function rollbackItems(int $job,int $beforeId=PHP_INT_MAX,int $limit=500): array { $stmt=$this->pdo->prepare("SELECT * FROM module_shtab_job_items WHERE job_id=:j AND id<:before AND created_by_job=1 AND target_public_id IS NOT NULL AND status IN ('imported','updated','rollback_failed') ORDER BY id DESC LIMIT ".max(1,min(1000,$limit)));$stmt->execute(['j'=>$job,'before'=>$beforeId]);return$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function unresolvedItems(int $job,int $limit=500): array { $stmt=$this->pdo->prepare('SELECT id,source_type,source_id,reason_code,reason_text,payload_json,created_at FROM module_shtab_unresolved_entities WHERE job_id=:j ORDER BY id DESC LIMIT '.max(1,min(1000,$limit)));$stmt->execute(['j'=>$job]);$rows=$stmt->fetchAll(PDO::FETCH_ASSOC)?:[];foreach($rows as &$row){$row['payload']=json_decode((string)($row['payload_json']??''),true)?:[];unset($row['payload_json']);}return$rows; }
    public function importItems(int $job,int $limit=100): array { return $this->importItemsBatch($job,0,0,$limit); }
    public function importItemsBatch(int $job,int $priority=0,int $lastId=0,int $limit=100): array { $sql="SELECT * FROM (SELECT i.*,CASE i.source_type WHEN 'workspace' THEN 10 WHEN 'organization' THEN 10 WHEN 'team' THEN 10 WHEN 'project' THEN 20 WHEN 'tag' THEN 30 WHEN 'user' THEN 35 WHEN 'contact' THEN 40 WHEN 'task' THEN 50 WHEN 'subtask' THEN 55 WHEN 'comment' THEN 60 WHEN 'file' THEN 70 ELSE 80 END AS import_priority FROM module_shtab_job_items i WHERE i.job_id=:j AND i.status='pending') x WHERE (x.import_priority>:p OR (x.import_priority=:p AND x.id>:i)) ORDER BY x.import_priority ASC,x.id ASC LIMIT ".max(1,min(500,$limit));$stmt=$this->pdo->prepare($sql);$stmt->execute(['j'=>$job,'p'=>$priority,'i'=>$lastId]);return$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }

    public function projectKeyPrefixExists(string $prefix): bool
    { $stmt=$this->pdo->prepare('SELECT id FROM projects WHERE task_key_prefix=:p LIMIT 1');$stmt->execute(['p'=>$prefix]);return$stmt->fetchColumn()!==false; }

    public function findMapping(int $connection,string $type,string $source): ?array { $stmt=$this->pdo->prepare('SELECT * FROM module_shtab_source_mappings WHERE connection_id=:c AND source_type=:t AND source_id=:s LIMIT 1');$stmt->execute(['c'=>$connection,'t'=>$type,'s'=>$source]);$row=$stmt->fetch(PDO::FETCH_ASSOC);return is_array($row)?$row:null; }
    public function upsertMapping(int $connection,string $type,string $source,array $data): array { $old=$this->findMapping($connection,$type,$source);$now=$this->now();if($old){$stmt=$this->pdo->prepare('UPDATE module_shtab_source_mappings SET source_parent_id=:p,target_type=:tt,target_public_id=:tp,source_checksum=:sc,state=:st,created_by_job_id=COALESCE(created_by_job_id,:j),last_seen_at=:ls,updated_at=:u WHERE id=:i');$stmt->execute(['p'=>$data['source_parent_id']??$old['source_parent_id'],'tt'=>$data['target_type']??$old['target_type'],'tp'=>$data['target_public_id']??$old['target_public_id'],'sc'=>$data['source_checksum']??$old['source_checksum'],'st'=>$data['state']??'active','j'=>$data['created_by_job_id']??$old['created_by_job_id'],'ls'=>$now,'u'=>$now,'i'=>$old['id']]);return$this->findMapping($connection,$type,$source)??$old;} $pid=$this->id('shm');$stmt=$this->pdo->prepare('INSERT INTO module_shtab_source_mappings (public_id,connection_id,source_type,source_id,source_parent_id,target_type,target_public_id,source_checksum,state,created_by_job_id,last_seen_at,created_at,updated_at) VALUES (:i,:c,:t,:s,:p,:tt,:tp,:sc,:st,:j,:ls,:cr,:u)');$stmt->execute(['i'=>$pid,'c'=>$connection,'t'=>$type,'s'=>$source,'p'=>$data['source_parent_id']??null,'tt'=>$data['target_type']??null,'tp'=>$data['target_public_id']??null,'sc'=>$data['source_checksum']??null,'st'=>$data['state']??'active','j'=>$data['created_by_job_id']??null,'ls'=>$now,'cr'=>$now,'u'=>$now]);return$this->findMapping($connection,$type,$source)??['public_id'=>$pid]; }
    public function markMappingRolledBack(int $connection,string $type,string $source): void
    {
        $stmt=$this->pdo->prepare("UPDATE module_shtab_source_mappings SET target_type=NULL,target_public_id=NULL,state='rolled_back',updated_at=UTC_TIMESTAMP() WHERE connection_id=:c AND source_type=:t AND source_id=:s");
        $stmt->execute(['c'=>$connection,'t'=>$type,'s'=>$source]);
    }

    public function unresolved(int $job,string $type,string $source,string $reason,string $text,array $payload=[]): void { $delete=$this->pdo->prepare('DELETE FROM module_shtab_unresolved_entities WHERE job_id=:j AND source_type=:t AND source_id=:s AND reason_code=:r');$delete->execute(['j'=>$job,'t'=>$type,'s'=>$source,'r'=>$reason]);$stmt=$this->pdo->prepare('INSERT INTO module_shtab_unresolved_entities (job_id,source_type,source_id,reason_code,reason_text,payload_json,created_at) VALUES (:j,:t,:s,:r,:x,:p,:n)');$stmt->execute(['j'=>$job,'t'=>$type,'s'=>$source,'r'=>$reason,'x'=>mb_substr($text,0,2000),'p'=>$payload===[]?null:$this->json($payload),'n'=>$this->now()]); }
    public function addLog(int $job,string $level,string $step,string $message,array $context=[]): void { $stmt=$this->pdo->prepare('INSERT INTO module_shtab_job_logs (job_id,level,step,message,context_json,created_at) VALUES (:j,:l,:s,:m,:c,:n)');$stmt->execute(['j'=>$job,'l'=>$level,'s'=>$step,'m'=>mb_substr($message,0,2000),'c'=>$context===[]?null:$this->json($context),'n'=>$this->now()]); }
    public function logs(int $job,int $limit=100): array { $stmt=$this->pdo->prepare('SELECT * FROM module_shtab_job_logs WHERE job_id=:j ORDER BY id DESC LIMIT '.max(1,min(1000,$limit)));$stmt->execute(['j'=>$job]);return$stmt->fetchAll(PDO::FETCH_ASSOC)?:[]; }
    public function report(string $id): array { $job=$this->getJob($id);if(!$job)return[];$job['items']=$this->itemCounts((int)$job['id']);$job['logs']=$this->logs((int)$job['id']);$job['unresolved']=$this->unresolvedItems((int)$job['id']);return$job; }
    public function retryJob(string $id): ?int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare("SELECT id,status FROM module_shtab_jobs WHERE public_id=:p AND status IN ('completed_with_warnings','failed','cancelled') LIMIT 1 FOR UPDATE");
            $stmt->execute(['p'=>$id]);
            $job=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($job)) {
                $this->pdo->commit();
                return null;
            }
            $u=$this->pdo->prepare("UPDATE module_shtab_job_items SET status='pending',error_code=NULL,error_message=NULL WHERE job_id=:j AND status='failed' AND (error_code IS NULL OR error_code<>'SOURCE_ID_COLLISION')");
            $u->execute(['j'=>$job['id']]);
            $resetItems=$u->rowCount();
            $shouldQueue=(string)$job['status']==='failed' || (string)$job['status']==='cancelled' || $resetItems>0;
            if ($shouldQueue) {
                $this->pdo->prepare("UPDATE module_shtab_jobs SET status='queued',last_source_cursor=:cursor,current_step='import_retry',progress_percent=0,progress_json=NULL,finished_at=NULL,lease_token=NULL,lease_until=NULL,updated_at=UTC_TIMESTAMP() WHERE id=:j")->execute([
                    'cursor'=>$this->json(['phase'=>'import','priority'=>0,'id'=>0]),
                    'j'=>$job['id'],
                ]);
            }
            $this->pdo->commit();
            return $resetItems;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }
}
