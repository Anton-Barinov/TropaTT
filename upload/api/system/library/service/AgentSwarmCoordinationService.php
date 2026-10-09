<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use PDO;
use RuntimeException;
use Throwable;

/** Coordinates one source run without granting ordinary task-write authority. */
final class AgentSwarmCoordinationService
{
    private const EVENT_KINDS = ['intent','result','checkpoint','blocker','message','decision','question','conflict','qa_result'];
    public function __construct(private readonly PDO $pdo) {}

    /** @param array{agent_id:string,run_id:string,token:string,generation:int} $lease */
    public function createRun(int $organizationId, int $actorId, array $input, array $lease): array
    {
        $this->assertInputKeys($input, ['run_id','parent_task_public_id','project_public_id','base_sha','participants']);
        foreach (['run_id','parent_task_public_id','base_sha'] as $key) { if (!isset($input[$key]) || !is_string($input[$key])) throw new RuntimeException('SWARM_INVALID_ARGUMENT'); }
        if (array_key_exists('project_public_id',$input) && $input['project_public_id'] !== null && !is_string($input['project_public_id'])) throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        if (!isset($input['participants']) || !is_array($input['participants']) || !array_is_list($input['participants'])) throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        $runId = (string)($input['run_id'] ?? '');
        $parentTask = (string)($input['parent_task_public_id'] ?? '');
        $project = isset($input['project_public_id']) && $input['project_public_id'] !== '' ? (string)$input['project_public_id'] : null;
        $sha = strtolower((string)($input['base_sha'] ?? ''));
        $participants = $input['participants'] ?? null;
        $this->validateRunInput($organizationId, $actorId, $runId, $parentTask, $sha, $participants);
        $this->validateLeaseShape($lease);
        $normalizedParticipants = [];
        $participantAgents = [];
        foreach ($participants as $participant) {
            if (!is_array($participant) || array_diff(array_keys($participant), ['task_public_id', 'agent_id']) !== []) {
                throw new RuntimeException('SWARM_INVALID_ARGUMENT');
            }
            $task = (string)($participant['task_public_id'] ?? '');
            $agent = (string)($participant['agent_id'] ?? '');
            if (!preg_match('/\Atsk_[A-Za-z0-9]{1,60}\z/D', $task) || !$this->validAgentId($agent) || $task === $parentTask) {
                throw new RuntimeException('SWARM_INVALID_PARTICIPANT');
            }
            if (isset($participantAgents[$agent])) { throw new RuntimeException('SWARM_DUPLICATE_PARTICIPANT'); }
            $participantAgents[$agent] = true;
            $normalizedParticipants[$task] = $agent;
        }
        if (count($normalizedParticipants) !== count($participants)) { throw new RuntimeException('SWARM_DUPLICATE_PARTICIPANT'); }
        ksort($normalizedParticipants, SORT_STRING);
        $inputHash = hash('sha256', json_encode([$parentTask, $project, $sha, $normalizedParticipants], JSON_THROW_ON_ERROR));
        $this->requireMysql();
        $this->pdo->beginTransaction();
        try {
            // The parent task lease serializes run creation; lock task rows in stable order next.
            $this->assertLease($organizationId, $actorId, $parentTask, $lease);
            $taskIds = array_keys($normalizedParticipants);
            $taskIds[] = $parentTask;
            sort($taskIds, SORT_STRING);
            $taskRows = [];
            foreach ($taskIds as $task) { $taskRows[$task] = $this->lockAssignedTask($organizationId, $actorId, $task); }
            $derivedProject = $this->projectPublicIdForTask($organizationId, $taskRows[$parentTask]);
            if ($project !== null && $project !== $derivedProject) { throw new RuntimeException('SWARM_PROJECT_SCOPE_MISMATCH'); }
            $project = $derivedProject;
            foreach (array_keys($normalizedParticipants) as $task) {
                if ($this->projectPublicIdForTask($organizationId, $taskRows[$task]) !== $project) { throw new RuntimeException('SWARM_PARTICIPANT_SCOPE_MISMATCH'); }
            }
            $existing = $this->one('SELECT payload_hash, created_by_user_id FROM agent_swarm_runs WHERE organization_id = :org AND swarm_run_id = :run FOR UPDATE', ['org'=>$organizationId,'run'=>$runId]);
            if ($existing) {
                if ((int)$existing['created_by_user_id'] !== $actorId || !hash_equals((string)$existing['payload_hash'], $inputHash)) {
                    throw new RuntimeException('SWARM_RUN_CONFLICT');
                }
                $this->pdo->commit();
                $result = $this->getRun($organizationId, $actorId, $runId);
                $result['replayed'] = true;
                return $result;
            }
            $this->exec('INSERT INTO agent_swarm_runs (organization_id, swarm_run_id, parent_task_public_id, project_public_id, base_sha, created_by_user_id, parent_agent_id, parent_lease_run_id, parent_generation, payload_hash, status, created_at) VALUES (:org,:run,:parent,:project,:sha,:actor,:agent,:lease_run,:generation,:hash,:status,UTC_TIMESTAMP())', [
                'org'=>$organizationId,'run'=>$runId,'parent'=>$parentTask,'project'=>$project,'sha'=>$sha,'actor'=>$actorId,
                'agent'=>$lease['agent_id'],'lease_run'=>$lease['run_id'],'generation'=>$lease['generation'],'hash'=>$inputHash,'status'=>'active']);
            $stmt = $this->pdo->prepare($this->dialectSql('INSERT INTO agent_swarm_participants (organization_id, swarm_run_id, task_public_id, agent_id, registered_by_user_id, created_at) VALUES (:org,:run,:task,:agent,:actor,UTC_TIMESTAMP())'));
            foreach ($normalizedParticipants as $task => $agent) {
                $stmt->execute(['org'=>$organizationId,'run'=>$runId,'task'=>$task,'agent'=>$agent,'actor'=>$actorId]);
            }
            $this->pdo->commit();
            $result = $this->getRun($organizationId, $actorId, $runId);
            $result['replayed'] = false;
            return $result;
        } catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    public function getRun(int $organizationId, int $actorId, string $runId): array
    {
        $run = $this->authorizedRun($organizationId, $actorId, $runId);
        $stmt = $this->pdo->prepare('SELECT task_public_id, agent_id FROM agent_swarm_participants WHERE organization_id = :org AND swarm_run_id = :run ORDER BY task_public_id');
        $stmt->execute(['org'=>$organizationId,'run'=>$runId]);
        $run['participants'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['run'=>$run];
    }

    /** Atomic, all-or-nothing path acquisition. */
    public function claimPaths(int $organizationId, int $actorId, array $input, array $lease): array
    {
        $this->assertInputKeys($input, ['swarm_run_id','task_public_id','agent_id','base_sha','paths']);
        foreach (['swarm_run_id','task_public_id','agent_id','base_sha'] as $key) { if (!isset($input[$key]) || !is_string($input[$key])) throw new RuntimeException('SWARM_INVALID_ARGUMENT'); }
        if (!isset($input['paths']) || !is_array($input['paths']) || !array_is_list($input['paths'])) throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        $runId = (string)($input['swarm_run_id'] ?? '');
        $task = (string)($input['task_public_id'] ?? '');
        $agent = (string)($input['agent_id'] ?? '');
        $sha = strtolower((string)($input['base_sha'] ?? ''));
        $paths = $input['paths'] ?? null;
        if ($organizationId < 1 || $actorId < 1 || !preg_match('/\A[a-f0-9]{32}\z/D', $runId)
            || !preg_match('/\Atsk_[A-Za-z0-9]{1,60}\z/D', $task) || !$this->validAgentId($agent)
            || !preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/D', $sha) || !is_array($paths) || !$paths || count($paths) > 200) {
            throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        }
        $this->validateLeaseShape($lease);
        $normalized = [];
        foreach ($paths as $path) { if (!is_string($path)) { throw new RuntimeException('SWARM_INVALID_PATH'); } $normalized[] = $this->normalizePath($path); }
        $normalized = array_values(array_unique($normalized));
        if (count($normalized) !== count($paths)) { throw new RuntimeException('SWARM_DUPLICATE_PATH'); }
        sort($normalized, SORT_STRING);
        $this->assertNoInternalOverlap($normalized);
        $this->requireMysql();
        $this->pdo->beginTransaction();
        try {
            $run = $this->lockRun($organizationId, $actorId, $runId);
            if ($run['status'] !== 'active' || !hash_equals((string)$run['base_sha'], $sha)) { throw new RuntimeException('SWARM_BASE_SHA_MISMATCH'); }
            // A single organization-scoped InnoDB row serializes prefix checks across SHAs/runs.
            $scopeSql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO agent_source_claim_scopes (organization_id, created_at) VALUES (:org, CURRENT_TIMESTAMP) ON CONFLICT(organization_id) DO NOTHING'
                : 'INSERT INTO agent_source_claim_scopes (organization_id, created_at) VALUES (:org, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE organization_id = VALUES(organization_id)';
            $this->exec($scopeSql, ['org'=>$organizationId]);
            $scope = $this->one('SELECT organization_id FROM agent_source_claim_scopes WHERE organization_id = :org FOR UPDATE', ['org'=>$organizationId]);
            if (!$scope) { throw new RuntimeException('SWARM_CLAIM_SCOPE_MISSING'); }
            $this->assertLease($organizationId, $actorId, $task, $lease);
            $this->assertParticipant($organizationId, $runId, $task, $agent, $actorId);
            $this->pruneStaleClaims($organizationId);
            $claims = $this->all('SELECT path_hash, normalized_path, swarm_run_id, base_sha, task_public_id, agent_id, lease_run_id, lease_generation, owner_user_id FROM agent_source_path_claims WHERE organization_id = :org ORDER BY normalized_path', ['org'=>$organizationId]);
            $replay = [];
            foreach ($normalized as $path) {
                foreach ($claims as $claimed) {
                    if (!$this->pathsOverlap($path, (string)$claimed['normalized_path'])) { continue; }
                    $same = $claimed['swarm_run_id'] === $runId && $claimed['task_public_id'] === $task && $claimed['agent_id'] === $agent
                        && $claimed['lease_run_id'] === $lease['run_id'] && (int)$claimed['lease_generation'] === (int)$lease['generation']
                        && (int)$claimed['owner_user_id'] === $actorId && hash_equals((string)$claimed['base_sha'], $sha) && $claimed['normalized_path'] === $path;
                    if ($same) { $replay[$path] = true; continue; }
                    throw new RuntimeException('SWARM_PATH_OVERLAP');
                }
            }
            $insert = $this->pdo->prepare($this->dialectSql('INSERT INTO agent_source_path_claims (organization_id, path_hash, normalized_path, swarm_run_id, base_sha, task_public_id, agent_id, lease_run_id, lease_generation, owner_user_id, created_at) VALUES (:org,:hash,:path,:run,:sha,:task,:agent,:lease_run,:generation,:actor,UTC_TIMESTAMP())'));
            foreach ($normalized as $path) {
                if (isset($replay[$path])) { continue; }
                $insert->execute(['org'=>$organizationId,'hash'=>$this->pathHash($path),'path'=>$path,'run'=>$runId,'sha'=>$sha,'task'=>$task,'agent'=>$agent,'lease_run'=>$lease['run_id'],'generation'=>$lease['generation'],'actor'=>$actorId]);
            }
            $this->pdo->commit();
            return ['claimed_paths'=>$normalized,'replayed'=>count($replay)===count($normalized)];
        } catch (Throwable $e) { $this->rollback(); throw $e; }
    }

    public function releasePaths(int $organizationId, int $actorId, array $input, array $lease): array
    {
        $this->assertInputKeys($input, ['swarm_run_id','task_public_id','agent_id','paths']);
        foreach (['swarm_run_id','task_public_id','agent_id'] as $key) { if (!isset($input[$key]) || !is_string($input[$key])) throw new RuntimeException('SWARM_INVALID_ARGUMENT'); }
        if (!isset($input['paths']) || !is_array($input['paths']) || !array_is_list($input['paths'])) throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        $runId=(string)($input['swarm_run_id']??''); $task=(string)($input['task_public_id']??''); $agent=(string)($input['agent_id']??'');
        $paths=$input['paths']??null;
        if (!preg_match('/\A[a-f0-9]{32}\z/D',$runId)||!preg_match('/\Atsk_[A-Za-z0-9]{1,60}\z/D',$task)||!$this->validAgentId($agent)||!is_array($paths)||!$paths||count($paths)>200) throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        $this->validateLeaseShape($lease); $normalized=[]; foreach($paths as $path){if(!is_string($path)) throw new RuntimeException('SWARM_INVALID_PATH');$normalized[]=$this->normalizePath($path);} $unique=array_values(array_unique($normalized)); if(count($unique)!==count($normalized))throw new RuntimeException('SWARM_DUPLICATE_PATH');$normalized=$unique;sort($normalized,SORT_STRING);
        $this->requireMysql(); $this->pdo->beginTransaction();
        try {
            $run=$this->lockRun($organizationId,$actorId,$runId);
            $scopeSql = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
                ? 'INSERT INTO agent_source_claim_scopes (organization_id, created_at) VALUES (:org, CURRENT_TIMESTAMP) ON CONFLICT(organization_id) DO NOTHING'
                : 'INSERT INTO agent_source_claim_scopes (organization_id, created_at) VALUES (:org, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE organization_id = VALUES(organization_id)';
            $this->exec($scopeSql,['org'=>$organizationId]);
            $this->one('SELECT organization_id FROM agent_source_claim_scopes WHERE organization_id = :org FOR UPDATE',['org'=>$organizationId]);
            $this->assertLease($organizationId,$actorId,$task,$lease);
            $this->assertParticipant($organizationId,$runId,$task,$agent,$actorId);
            $stmt=$this->pdo->prepare($this->dialectSql('DELETE FROM agent_source_path_claims WHERE organization_id=:org AND swarm_run_id=:run AND task_public_id=:task AND agent_id=:agent AND lease_run_id=:lease_run AND lease_generation=:generation AND owner_user_id=:actor AND path_hash=:hash'));
            $deleted=0; foreach($normalized as $path){$stmt->execute(['org'=>$organizationId,'run'=>$runId,'task'=>$task,'agent'=>$agent,'lease_run'=>$lease['run_id'],'generation'=>$lease['generation'],'actor'=>$actorId,'hash'=>$this->pathHash($path)]);$deleted+=$stmt->rowCount();}
            $this->pdo->commit(); return ['released_paths'=>$deleted,'base_sha'=>$run['base_sha']];
        } catch(Throwable $e){$this->rollback();throw $e;}
    }

    /** Append run-visible coordination event, fenced by the participant's task lease. */
    public function appendEvent(int $organizationId, int $actorId, array $input, array $lease): array
    {
        $this->assertInputKeys($input, ['swarm_run_id','event_id','task_public_id','agent_id','operation_id','event_kind','stage','body']);
        foreach (['swarm_run_id','event_id','task_public_id','agent_id','operation_id','event_kind','stage','body'] as $key) { if (!isset($input[$key]) || !is_string($input[$key])) throw new RuntimeException('SWARM_INVALID_EVENT'); }
        $runId=(string)($input['swarm_run_id']??'');$eventId=(string)($input['event_id']??'');$task=(string)($input['task_public_id']??'');$agent=(string)($input['agent_id']??'');
        $operation=(string)($input['operation_id']??'');$kind=(string)($input['event_kind']??'');$stage=(string)($input['stage']??'');$body=(string)($input['body']??'');
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$runId)||!preg_match('/\A[a-f0-9]{32}\z/D',$eventId)||!preg_match('/\Atsk_[A-Za-z0-9]{1,60}\z/D',$task)||!$this->validAgentId($agent)
            ||!preg_match('/\A[A-Za-z0-9._-]{1,96}\z/D',$operation)||!in_array($kind,self::EVENT_KINDS,true)||!preg_match('/\A[a-z][a-z0-9_]{0,31}\z/D',$stage)
            ||trim($body)===''||strlen($body)>16000||mb_strlen($body,'UTF-8')>4000||!mb_check_encoding($body,'UTF-8')||str_contains($body,(string)($lease['token']??''))||preg_match('/(?:\bapk_[A-Za-z0-9]+|\bBearer\s+\S+|(?:api[_-]?key|access[_-]?token|refresh[_-]?token|password|secret|private[_-]?key|client[_-]?secret)\s*[:=]\s*\S+|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\beyJ[A-Za-z0-9_-]{20,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,})/i',$body)) throw new RuntimeException('SWARM_INVALID_EVENT');
        $this->validateLeaseShape($lease);$hash=hash('sha256',json_encode([$operation,$kind,$stage,$body],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $this->requireMysql();$this->pdo->beginTransaction();
        try{
            $this->lockRun($organizationId,$actorId,$runId);$this->assertLease($organizationId,$actorId,$task,$lease);$this->assertParticipant($organizationId,$runId,$task,$agent,$actorId);
            $old=$this->one('SELECT id,event_id,operation_id,event_kind,stage,owner_user_id,agent_id,task_public_id,lease_run_id,lease_generation,body,payload_hash,created_at FROM agent_swarm_events WHERE organization_id=:org AND swarm_run_id=:run AND event_id=:event FOR UPDATE',['org'=>$organizationId,'run'=>$runId,'event'=>$eventId]);
            if($old){if(!hash_equals((string)$old['payload_hash'],$hash)||(int)$old['owner_user_id']!==$actorId||$old['agent_id']!==$agent||$old['task_public_id']!==$task||$old['lease_run_id']!==$lease['run_id']||(int)$old['lease_generation']!==(int)$lease['generation'])throw new RuntimeException('SWARM_EVENT_CONFLICT');$this->pdo->commit();return ['event'=>$this->publicEvent($old),'replayed'=>true];}
            $this->exec('INSERT INTO agent_swarm_events (organization_id,swarm_run_id,event_id,operation_id,event_kind,stage,owner_user_id,agent_id,task_public_id,lease_run_id,lease_generation,body,payload_hash,created_at) VALUES (:org,:run,:event,:operation,:kind,:stage,:actor,:agent,:task,:lease_run,:generation,:body,:hash,UTC_TIMESTAMP())',['org'=>$organizationId,'run'=>$runId,'event'=>$eventId,'operation'=>$operation,'kind'=>$kind,'stage'=>$stage,'actor'=>$actorId,'agent'=>$agent,'task'=>$task,'lease_run'=>$lease['run_id'],'generation'=>$lease['generation'],'body'=>$body,'hash'=>$hash]);
            $id=(int)$this->pdo->lastInsertId();$row=$this->one('SELECT * FROM agent_swarm_events WHERE id=:id',['id'=>$id]);$this->pdo->commit();return ['event'=>$this->publicEvent($row??[]),'replayed'=>false];
        }catch(Throwable $e){$this->rollback();throw $e;}
    }

    public function listEvents(int $organizationId,int $actorId,string $runId,int $afterId=0,int $limit=50):array
    {
        if($afterId<0||$limit<1||$limit>100)throw new RuntimeException('SWARM_INVALID_CURSOR');
        $this->authorizedRun($organizationId,$actorId,$runId);
        $rows=$this->all('SELECT id,event_id,operation_id,event_kind,stage,owner_user_id,agent_id,task_public_id,lease_run_id,lease_generation,body,payload_hash,created_at FROM agent_swarm_events WHERE organization_id=:org AND swarm_run_id=:run AND id>:cursor ORDER BY id LIMIT '.(int)$limit,['org'=>$organizationId,'run'=>$runId,'cursor'=>$afterId]);
        return ['events'=>array_map(fn(array $r):array=>$this->publicEvent($r),$rows),'next_cursor'=>$rows?(int)end($rows)['id']:$afterId];
    }

    private function authorizedRun(int $org,int $actor,string $runId):array
    {
        if(!preg_match('/\A[a-f0-9]{32}\z/D',$runId))throw new RuntimeException('SWARM_INVALID_ARGUMENT');
        $row=$this->one('SELECT r.swarm_run_id,r.parent_task_public_id,r.project_public_id,r.base_sha,r.created_by_user_id,r.status,r.created_at FROM agent_swarm_runs r WHERE r.organization_id=:org AND r.swarm_run_id=:run AND (r.created_by_user_id=:actor OR EXISTS (SELECT 1 FROM agent_swarm_participants p JOIN tasks t ON t.public_id=p.task_public_id AND t.organization_id=p.organization_id WHERE p.organization_id=r.organization_id AND p.swarm_run_id=r.swarm_run_id AND t.assignee_user_id=:participant_actor AND t.deleted_at IS NULL))',['org'=>$org,'run'=>$runId,'actor'=>$actor,'participant_actor'=>$actor]);
        if(!$row)throw new RuntimeException('SWARM_RUN_NOT_FOUND');return $row;
    }
    private function lockRun(int $org,int $actor,string $runId):array
    {
        $this->authorizedRun($org,$actor,$runId);
        $row=$this->one('SELECT swarm_run_id,parent_task_public_id,project_public_id,base_sha,created_by_user_id,status,created_at FROM agent_swarm_runs WHERE organization_id=:org AND swarm_run_id=:run FOR UPDATE',['org'=>$org,'run'=>$runId]);
        if(!$row)throw new RuntimeException('SWARM_RUN_NOT_FOUND');return $row;
    }
    private function assertParticipant(int $org,string $run,string $task,string $agent,int $actor):void
    {
        $row=$this->one('SELECT p.agent_id FROM agent_swarm_participants p JOIN tasks t ON t.public_id=p.task_public_id AND t.organization_id=p.organization_id WHERE p.organization_id=:org AND p.swarm_run_id=:run AND p.task_public_id=:task AND p.agent_id=:agent AND t.assignee_user_id=:actor AND t.deleted_at IS NULL FOR UPDATE',['org'=>$org,'run'=>$run,'task'=>$task,'agent'=>$agent,'actor'=>$actor]);
        if(!$row)throw new RuntimeException('SWARM_PARTICIPANT_DENIED');
    }
    private function lockAssignedTask(int $org,int $actor,string $task):array
    {
        $row=$this->one('SELECT id,public_id,project_id FROM tasks WHERE organization_id=:org AND public_id=:task AND assignee_user_id=:actor AND deleted_at IS NULL FOR UPDATE',['org'=>$org,'task'=>$task,'actor'=>$actor]);
        if(!$row)throw new RuntimeException('SWARM_TASK_ACCESS_DENIED');return $row;
    }
    private function projectPublicIdForTask(int $org,array $task):?string
    {
        if(empty($task['project_id']))return null;$row=$this->one('SELECT public_id FROM projects WHERE id=:id AND organization_id=:org AND deleted_at IS NULL',['id'=>$task['project_id'],'org'=>$org]);
        if(!$row)throw new RuntimeException('SWARM_PROJECT_SCOPE_MISMATCH');return (string)$row['public_id'];
    }
    private function assertLease(int $org,int $actor,string $task,array $lease):void
    {
        $row=$this->one('SELECT owner_user_id,agent_id,run_id,token_hash,generation,expires_at,UNIX_TIMESTAMP() server_now FROM agent_leases WHERE organization_id=:org AND resource_key=:resource FOR UPDATE',['org'=>$org,'resource'=>'task:'.$task]);
        if(!$row||(int)$row['owner_user_id']!==$actor||$row['agent_id']!==$lease['agent_id']||$row['run_id']!==$lease['run_id']||(int)$row['generation']!==(int)$lease['generation']||(int)$row['expires_at']<=(int)$row['server_now']||!hash_equals((string)$row['token_hash'],hash('sha256',(string)$lease['token'])))throw new RuntimeException('LEASE_OWNERSHIP_LOST');
    }
    private function pruneStaleClaims(int $org):void
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->exec("DELETE FROM agent_source_path_claims WHERE organization_id=:org AND NOT EXISTS (SELECT 1 FROM agent_leases l WHERE l.organization_id=agent_source_path_claims.organization_id AND l.resource_key='task:'||agent_source_path_claims.task_public_id AND l.expires_at>CAST(strftime('%s','now') AS INTEGER) AND l.generation=agent_source_path_claims.lease_generation AND l.run_id=agent_source_path_claims.lease_run_id AND l.agent_id=agent_source_path_claims.agent_id AND l.owner_user_id=agent_source_path_claims.owner_user_id)",['org'=>$org]);
            return;
        }
        $this->exec("DELETE c FROM agent_source_path_claims c LEFT JOIN agent_leases l ON l.organization_id=c.organization_id AND l.resource_key=CONCAT('task:',c.task_public_id) WHERE c.organization_id=:org AND (l.id IS NULL OR l.expires_at<=UNIX_TIMESTAMP() OR l.generation<>c.lease_generation OR l.run_id<>c.lease_run_id OR l.agent_id<>c.agent_id OR l.owner_user_id<>c.owner_user_id)",['org'=>$org]);
    }
    private function normalizePath(string $path):string
    {
        if($path===''||strlen($path)>512||!mb_check_encoding($path,'UTF-8')||str_contains($path,"\0")||str_contains($path,chr(92))||str_starts_with($path,'/')||preg_match('/[\x00-\x1f\x7f]/',$path))throw new RuntimeException('SWARM_INVALID_PATH');
        $parts=explode('/',$path);foreach($parts as $part)if($part===''||$part==='.'||$part==='..')throw new RuntimeException('SWARM_INVALID_PATH');return $path;
    }
    private function assertNoInternalOverlap(array $paths):void
    {
        for($i=0;$i<count($paths);$i++)for($j=$i+1;$j<count($paths);$j++)if($this->pathsOverlap($paths[$i],$paths[$j]))throw new RuntimeException('SWARM_PATH_OVERLAP');
    }
    private function pathsOverlap(string $a,string $b):bool{$a=strtolower($a);$b=strtolower($b);return $a===$b||str_starts_with($a,$b.'/')||str_starts_with($b,$a.'/');}
    private function pathHash(string $path):string{return hash('sha256',strtolower($path));}
    private function assertInputKeys(array $input,array $allowed):void{if(array_diff(array_keys($input),$allowed)!==[])throw new RuntimeException('SWARM_INVALID_ARGUMENT');}
    private function validateRunInput(int $org,int $actor,string $run,string $parent,string $sha,mixed $participants):void
    {
        if($org<1||$actor<1||!preg_match('/\A[a-f0-9]{32}\z/D',$run)||!preg_match('/\Atsk_[A-Za-z0-9]{1,60}\z/D',$parent)||!preg_match('/\A(?:[a-f0-9]{40}|[a-f0-9]{64})\z/D',$sha)||!is_array($participants)||count($participants)<1||count($participants)>16)throw new RuntimeException('SWARM_INVALID_ARGUMENT');
    }
    private function validateLeaseShape(array $lease):void
    {
        if(array_diff(array_keys($lease),['agent_id','run_id','token','generation'])!==[]||!is_string($lease['agent_id']??null)||!is_string($lease['run_id']??null)||!is_string($lease['token']??null)||!is_int($lease['generation']??null)||!$this->validAgentId($lease['agent_id'])||!$this->validAgentId($lease['run_id'])||!preg_match('/\A[a-f0-9]{64}\z/D',$lease['token'])||$lease['generation']<1)throw new RuntimeException('LEASE_INVALID_ARGUMENT');
    }
    private function validAgentId(string $id):bool{return (bool)preg_match('/\A[A-Za-z0-9._-]{1,96}\z/D',$id)&&!preg_match('/\bapk_/i',$id);}
    private function requireMysql():void{if(!in_array($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME),['mysql','sqlite'],true)||$this->pdo->inTransaction())throw new RuntimeException('SWARM_MYSQL_TRANSACTION_REQUIRED');}
    private function one(string $sql,array $params):?array{$s=$this->pdo->prepare($this->dialectSql($sql));$s->execute($params);$r=$s->fetch(PDO::FETCH_ASSOC);return $r?:null;}
    private function all(string $sql,array $params):array{$s=$this->pdo->prepare($this->dialectSql($sql));$s->execute($params);return $s->fetchAll(PDO::FETCH_ASSOC)?:[];}
    private function exec(string $sql,array $params):void{$s=$this->pdo->prepare($this->dialectSql($sql));$s->execute($params);}
    private function dialectSql(string $sql):string
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') return $sql;
        $sql = str_replace(' FOR UPDATE', '', $sql);
        $sql = str_replace('UNIX_TIMESTAMP() server_now', "CAST(strftime('%s','now') AS INTEGER) server_now", $sql);
        return str_replace('UTC_TIMESTAMP()', 'CURRENT_TIMESTAMP', $sql);
    }
    private function rollback():void{if($this->pdo->inTransaction())$this->pdo->rollBack();}
    private function publicEvent(array $r):array{return ['cursor'=>(int)$r['id'],'event_id'=>(string)$r['event_id'],'operation_id'=>(string)$r['operation_id'],'event_kind'=>(string)$r['event_kind'],'stage'=>(string)$r['stage'],'owner_user_id'=>(int)$r['owner_user_id'],'agent_id'=>(string)$r['agent_id'],'task_public_id'=>(string)$r['task_public_id'],'lease_run_id'=>(string)$r['lease_run_id'],'lease_generation'=>(int)$r['lease_generation'],'body'=>(string)$r['body'],'created_at'=>(string)$r['created_at']];}
}
