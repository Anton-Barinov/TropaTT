<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabClient;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabCrawler;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabImportService;
use Module\Crm\ActiveCollabMigration\Service\ActiveCollabTargetWriter;
use Module\Crm\ActiveCollabMigration\Service\EncryptionService;

final class ActiveCollabMigrationController
{
    private ActiveCollabMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->repo = new ActiveCollabMigrationRepository($container->get('db.pdo'));
    }

    private function body(): array
    {
        $decoded = json_decode((string)($this->container->get('request')->rawBody ?? ''), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function actor(): array
    {
        $auth = $this->container->has('auth_user') ? $this->container->get('auth_user') : [];
        return is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : [];
    }

    private function actorId(): int { return (int)($this->actor()['id'] ?? 0); }

    private function can(string $permission): bool
    {
        $actor = $this->actor();
        return !empty($actor['is_root']) || in_array('*', (array)($actor['permission_codes'] ?? []), true) || in_array($permission, (array)($actor['permission_codes'] ?? []), true);
    }

    private function connection(string $id): array|JsonResponse
    {
        $connection = $this->repo->getConnection($id);
        if (!$connection) return JsonResponse::error('NOT_FOUND', 'ActiveCollab connection not found', 404);
        if (!$this->can('module.activecollab-migration.manage') && (int)$connection['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Connection access denied', 403);
        return $connection;
    }

    private function job(string $id): array|JsonResponse
    {
        $job = $this->repo->getJob($id);
        if (!$job) return JsonResponse::error('NOT_FOUND', 'ActiveCollab migration job not found', 404);
        if (!$this->can('module.activecollab-migration.manage') && (int)$job['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Migration job access denied', 403);
        return $job;
    }

    public function listConnections(): JsonResponse
    {
        return JsonResponse::success('ACTIVECOLLAB_CONNECTIONS_LIST', 'OK', ['connections'=>array_map([$this,'publicConnection'],$this->repo->listConnections($this->actorId(),$this->can('module.activecollab-migration.manage')))]);
    }

    public function createConnection(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createConnectionInternal());
    }

    private function createConnectionInternal(): JsonResponse
    {
        $input=$this->body();$name=trim((string)($input['name']??''));$token=trim((string)($input['access_token']??$input['token']??''));$baseUrl=trim((string)($input['base_url']??''));$accountId=trim((string)($input['account_id']??$input['workspace_gid']??''));
        if($name===''||$token===''||$baseUrl==='')return JsonResponse::error('VALIDATION_ERROR','name, base_url and access_token are required',422);
        if($accountId==='')return JsonResponse::error('ACTIVECOLLAB_ACCOUNT_ID_REQUIRED','account_id is required for an ActiveCollab API root',422);
        $connection=null;
        try{$client=new ActiveCollabClient($this->repo,$baseUrl);$connection=$this->repo->createConnection(['name'=>mb_substr($name,0,255),'base_url'=>$baseUrl,'workspace_gid'=>mb_substr($accountId,0,191),'auth_type'=>'pat','access_token_encrypted'=>EncryptionService::encrypt($token),'created_by_user_id'=>$this->actorId()]);$me=$client->me($token);$this->repo->updateConnectionCheck((string)$connection['public_id'],true,$accountId);return JsonResponse::success('ACTIVECOLLAB_CONNECTION_CREATED','Connection created and verified',['connection'=>$this->publicConnection($this->repo->getConnection((string)$connection['public_id'])??$connection),'source'=>$me],201);}catch(\Throwable $e){if(is_array($connection)&&!empty($connection['public_id']))$this->repo->updateConnectionCheck((string)$connection['public_id'],false,'ActiveCollab connection test failed');$code=$e->getMessage()==='ACTIVECOLLAB_AUTH_FAILED'?'ACTIVECOLLAB_AUTH_FAILED':($e->getMessage()==='ACTIVECOLLAB_BASE_URL_INVALID'?'ACTIVECOLLAB_BASE_URL_INVALID':'ACTIVECOLLAB_CONNECTION_TEST_FAILED');return JsonResponse::error($code,'ActiveCollab credentials or API root could not be verified',422);}
    }

    public function getConnection(array $params): JsonResponse { $r=$this->connection((string)($params['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('ACTIVECOLLAB_CONNECTION','OK',['connection'=>$this->publicConnection($r)]); }

    public function updateConnection(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$input=$this->body();$update=[];
        if(array_key_exists('name',$input))$update['name']=mb_substr(trim((string)$input['name']),0,255);
        if(array_key_exists('base_url',$input)){$base=trim((string)$input['base_url']);try{new ActiveCollabClient($this->repo,$base);}catch(\Throwable){return JsonResponse::error('ACTIVECOLLAB_BASE_URL_INVALID','base_url must be an HTTPS ActiveCollab API root',422);}$update['base_url']=$base;}
        if(array_key_exists('account_id',$input)||array_key_exists('workspace_gid',$input))$update['workspace_gid']=mb_substr(trim((string)($input['account_id']??$input['workspace_gid'])),0,191);
        $newToken=trim((string)($input['access_token']??$input['token']??''));if($newToken!=='')$update['access_token_encrypted']=EncryptionService::encrypt($newToken);
        $this->repo->updateConnection((string)$params['public_id'],$update);if($newToken!==''||isset($update['base_url']))$this->repo->updateConnectionCheck((string)$params['public_id'],false,'Credentials or API root changed; test required');return JsonResponse::success('ACTIVECOLLAB_CONNECTION_UPDATED','Connection updated',['connection'=>$this->publicConnection($this->repo->getConnection((string)$params['public_id'])??[]) ]);
    }

    public function deleteConnection(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;if($this->repo->hasRunningJobs((int)$r['id']))return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS','Cancel running jobs before deleting the connection',409);try{$this->repo->deleteConnection((int)$r['id']);return JsonResponse::success('ACTIVECOLLAB_CONNECTION_DELETED','Connection deleted');}catch(\Throwable){return JsonResponse::error('ACTIVECOLLAB_CONNECTION_DELETE_FAILED','Connection could not be deleted',409);}
    }

    public function testConnection(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$token=EncryptionService::decrypt((string)($r['access_token_encrypted']??''));if($token===null)return JsonResponse::error('ACTIVECOLLAB_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{$source=$this->client($r)->me($token);$this->repo->updateConnectionCheck((string)$r['public_id'],true);return JsonResponse::success('ACTIVECOLLAB_CONNECTION_TEST_OK','Connection successful',['source'=>$source]);}catch(\Throwable){$this->repo->updateConnectionCheck((string)$r['public_id'],false,'ActiveCollab connection test failed');return JsonResponse::error('ACTIVECOLLAB_CONNECTION_TEST_FAILED','ActiveCollab connection test failed',400);}
    }

    public function listWorkspaces(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$token=EncryptionService::decrypt((string)($r['access_token_encrypted']??''));if($token===null)return JsonResponse::error('ACTIVECOLLAB_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{return JsonResponse::success('ACTIVECOLLAB_ACCOUNTS_LIST','OK',['workspaces'=>$this->client($r)->workspaces($token,(string)($r['workspace_gid']??''))]);}catch(\Throwable){return JsonResponse::error('ACTIVECOLLAB_ACCOUNTS_FAILED','Could not validate the ActiveCollab API root',400);}
    }

    public function discover(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$input=$this->body();$account=trim((string)($input['account_id']??$input['workspace_gid']??$r['workspace_gid']??''));if($account==='')return JsonResponse::error('ACTIVECOLLAB_ACCOUNT_ID_REQUIRED','account_id is required',422);$token=EncryptionService::decrypt((string)$r['access_token_encrypted']);if($token===null)return JsonResponse::error('ACTIVECOLLAB_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{$client=$this->client($r);$includeArchived=!empty($input['include_archived']);$projects=$client->projects($token,$includeArchived);$companies=$client->companies($token,$includeArchived);foreach($client->users($token)as$user){$this->repo->upsertUserMapping((int)$r['id'],$user);}return JsonResponse::success('ACTIVECOLLAB_DISCOVERY_COMPLETE','ActiveCollab source discovered',['account_id'=>$account,'projects'=>$projects,'companies'=>$companies,'user_mappings'=>$this->repo->listUserMappings((int)$r['id'])]);}catch(\Throwable){return JsonResponse::error('ACTIVECOLLAB_DISCOVERY_FAILED','ActiveCollab discovery failed',400);}
    }

    public function listUserMappings(array $params): JsonResponse {$r=$this->connection((string)($params['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('ACTIVECOLLAB_USER_MAPPINGS','OK',['items'=>$this->repo->listUserMappings((int)$r['id'])]);}
    public function updateUserMapping(array $params): JsonResponse {$r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$input=$this->body();$crm=!empty($input['crm_user_public_id'])?(string)$input['crm_user_public_id']:null;if($crm!==null&&$this->repo->activeUserPublicId($crm)===null)return JsonResponse::error('USER_NOT_FOUND','Active CRM user not found',404);if(!$this->repo->updateUserMapping((int)$r['id'],(int)($params['mapping_id']??0),$crm))return JsonResponse::error('MAPPING_NOT_FOUND','User mapping not found',404);return JsonResponse::success('ACTIVECOLLAB_USER_MAPPING_UPDATED','Mapping updated');}
    public function listJobs(): JsonResponse {return JsonResponse::success('ACTIVECOLLAB_JOBS_LIST','OK',['items'=>$this->repo->listJobs($this->actorId(),$this->can('module.activecollab-migration.manage'))]);}

    public function createJob(): JsonResponse {return$this->withIdempotency(fn():JsonResponse=>$this->createJobInternal());}
    private function createJobInternal(): JsonResponse
    {
        $input=$this->body();$connection=$this->connection((string)($input['connection_public_id']??''));if($connection instanceof JsonResponse)return$connection;$account=trim((string)($input['account_id']??$input['workspace_gid']??$connection['workspace_gid']??''));if($account==='')return JsonResponse::error('ACTIVECOLLAB_ACCOUNT_ID_REQUIRED','account_id is required',422);$token=EncryptionService::decrypt((string)$connection['access_token_encrypted']);if($token===null)return JsonResponse::error('ACTIVECOLLAB_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{$this->client($connection)->me($token);}catch(\Throwable){return JsonResponse::error('ACTIVECOLLAB_SOURCE_UNAVAILABLE','ActiveCollab API is unavailable',422);}$mode=(string)($input['mode']??'import');if(!in_array($mode,['import','sync','dry_run'],true))return JsonResponse::error('VALIDATION_ERROR','mode must be import, sync or dry_run',422);$options=(array)($input['target_options']??$input['options']??[]);$includeTime=array_key_exists('include_time_records',$options)?(bool)$options['include_time_records']:true;$options['include_time_records']=$includeTime;if($mode!=='dry_run'&&$includeTime&&empty($this->actor()['is_root']))return JsonResponse::error('ACTIVECOLLAB_ROOT_REQUIRED','Imports of historical time records require a root user',403);$scope=(array)($input['source_scope']??[]);$scope['project_ids']=array_values(array_filter(array_map('strval',(array)($input['project_ids']??$scope['project_ids']??[]))));$job=$this->repo->createJob(['connection_id'=>(int)$connection['id'],'workspace_gid'=>$account,'mode'=>$mode,'source_scope'=>$scope,'target_options'=>$options,'created_by_user_id'=>$this->actorId()]);return JsonResponse::success('ACTIVECOLLAB_JOB_CREATED','Job created',['job'=>$job],201);
    }
    public function getJob(array $params): JsonResponse {$r=$this->job((string)($params['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('ACTIVECOLLAB_JOB','OK',['job'=>$r]);}
    public function startJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'queued','ACTIVECOLLAB_JOB_QUEUED');}
    public function pauseJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'pausing','ACTIVECOLLAB_JOB_PAUSING');}
    public function resumeJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'queued','ACTIVECOLLAB_JOB_RESUMED');}
    public function cancelJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'cancelling','ACTIVECOLLAB_JOB_CANCELLING');}
    public function retryFailed(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);if($r instanceof JsonResponse)return$r;if(!in_array((string)($r['status']??''),['completed_with_warnings','failed','cancelled'],true))return JsonResponse::error('INVALID_JOB_STATUS','Only a finished job can be retried',409);$count=$this->repo->retryJob((string)$params['public_id']);return$count===null?JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409):JsonResponse::success('ACTIVECOLLAB_JOB_RETRY_QUEUED','Failed items queued',['reset_items'=>$count]);}
    public function rollbackJob(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);if($r instanceof JsonResponse)return$r;try{$this->buildImportService((string)$params['public_id'])->rollback((string)$params['public_id'],$this->actor());return JsonResponse::success('ACTIVECOLLAB_JOB_ROLLED_BACK','Job targets rolled back');}catch(\Throwable){return JsonResponse::error('ACTIVECOLLAB_ROLLBACK_FAILED','Rollback failed; inspect the migration log',409);}}
    public function listJobItems(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);if($r instanceof JsonResponse)return$r;$input=$this->container->get('request')->allInput();return JsonResponse::success('ACTIVECOLLAB_JOB_ITEMS','OK',['items'=>$this->repo->items((int)$r['id'],!empty($input['status'])?(string)$input['status']:null,max(1,min(1000,(int)($input['limit']??200))))]);}
    public function listJobLogs(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('ACTIVECOLLAB_JOB_LOGS','OK',['items'=>$this->repo->logs((int)$r['id'])]);}
    public function getReport(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('ACTIVECOLLAB_JOB_REPORT','OK',['report'=>$this->repo->report((string)$params['public_id'])]);}

    private function changeJob(string $id,string $status,string $code): JsonResponse {$r=$this->job($id);if($r instanceof JsonResponse)return$r;$current=(string)($r['status']??'');$allowed=match($status){'queued'=>in_array($current,['draft','paused','failed','cancelled'],true),'pausing'=>in_array($current,['queued','running'],true),'cancelling'=>in_array($current,['draft','queued','running','paused','pausing'],true),default=>false};if(!$allowed)return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be changed from status: '.$current,409);$requested=$status;if($current==='queued'&&$status==='pausing')$requested='paused';if(in_array($current,['draft','queued','paused','pausing'],true)&&$status==='cancelling')$requested='cancelled';if(!$this->repo->requestStatus($id,$requested))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success($code,'Job state updated');}
    private function client(array $connection): ActiveCollabClient {$client=new ActiveCollabClient($this->repo,(string)($connection['base_url']??''));$client->setConnectionId((int)$connection['id']);return$client;}
    private function buildImportService(string $jobPublicId): ActiveCollabImportService {$job=$this->repo->getJob($jobPublicId);$connection=is_array($job)?$this->repo->getConnectionById((int)$job['connection_id']):null;if(!is_array($connection))throw new \RuntimeException('ACTIVECOLLAB_CONNECTION_NOT_FOUND');$client=$this->client($connection);$crawler=new ActiveCollabCrawler($client,$this->repo);$writer=new ActiveCollabTargetWriter($this->container,$this->repo,$client);return new ActiveCollabImportService($this->repo,$client,$crawler,$writer);}
    private function withIdempotency(callable $producer): JsonResponse {if(!$this->container->has('service.idempotency'))return$producer();$service=$this->container->get('service.idempotency');$request=$this->container->get('request');$auth=$this->container->has('auth_user')?$this->container->get('auth_user'):null;$actor=is_array($auth)&&is_array($auth['user']??null)?$auth['user']:null;$replayed=$service->replay($request,$actor);if($replayed instanceof JsonResponse)return$replayed;$response=$producer();$service->remember($request,$actor,$response);return$response;}
    private function publicConnection(array $connection): array {unset($connection['access_token_encrypted'],$connection['refresh_token_encrypted'],$connection['client_id_encrypted'],$connection['client_secret_encrypted']);return$connection;}
}
