<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\WorksectionMigration\Repository\WorksectionMigrationRepository;
use Module\Crm\WorksectionMigration\Service\WorksectionClient;
use Module\Crm\WorksectionMigration\Service\WorksectionCrawler;
use Module\Crm\WorksectionMigration\Service\WorksectionImportService;
use Module\Crm\WorksectionMigration\Service\WorksectionTargetWriter;
use Module\Crm\WorksectionMigration\Service\EncryptionService;

final class WorksectionMigrationController
{
    private WorksectionMigrationRepository $repo;

    public function __construct(private readonly Container $container)
    {
        $this->repo = new WorksectionMigrationRepository($container->get('db.pdo'));
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
        if (!$connection) return JsonResponse::error('NOT_FOUND', 'Worksection connection not found', 404);
        if (!$this->can('module.worksection-migration.manage') && (int)$connection['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Connection access denied', 403);
        return $connection;
    }

    private function job(string $id): array|JsonResponse
    {
        $job = $this->repo->getJob($id);
        if (!$job) return JsonResponse::error('NOT_FOUND', 'Worksection migration job not found', 404);
        if (!$this->can('module.worksection-migration.manage') && (int)$job['created_by_user_id'] !== $this->actorId()) return JsonResponse::error('FORBIDDEN', 'Migration job access denied', 403);
        return $job;
    }

    public function listConnections(): JsonResponse
    {
        return JsonResponse::success('WORKSECTION_CONNECTIONS_LIST', 'OK', ['connections'=>array_map([$this,'publicConnection'],$this->repo->listConnections($this->actorId(),$this->can('module.worksection-migration.manage')))]);
    }

    public function createConnection(): JsonResponse
    {
        return $this->withIdempotency(fn(): JsonResponse => $this->createConnectionInternal());
    }

    private function createConnectionInternal(): JsonResponse
    {
        $input=$this->body();$name=trim((string)($input['name']??''));$accountUrl=trim((string)($input['account_url']??$input['base_url']??''));$authType=(string)($input['auth_type']??'api_key');if(!in_array($authType,['api_key','oauth2'],true))return JsonResponse::error('VALIDATION_ERROR','auth_type must be api_key or oauth2',422);
        if($name===''||$accountUrl==='')return JsonResponse::error('VALIDATION_ERROR','name and account_url are required',422);
        try{new WorksectionClient($this->repo,$accountUrl);}catch(\Throwable){return JsonResponse::error('WORKSECTION_ACCOUNT_URL_INVALID','account_url must be an HTTPS Worksection account URL',422);}
        if($authType==='api_key'){$key=trim((string)($input['api_key']??$input['access_token']??''));if($key==='')return JsonResponse::error('VALIDATION_ERROR','api_key is required for api_key auth',422);}
        else{$token=trim((string)($input['access_token']??''));if($token==='')return JsonResponse::error('VALIDATION_ERROR','access_token is required for oauth2 auth',422);}
        $connection=null;
        try{$client=new WorksectionClient($this->repo,$accountUrl);$client->setAuthType($authType);$credential=$authType==='oauth2'?trim((string)($input['access_token']??'')):trim((string)($input['api_key']??$input['access_token']??''));$connection=$this->repo->createConnection(['name'=>mb_substr($name,0,255),'account_url'=>$accountUrl,'auth_type'=>$authType,'api_key_encrypted'=>$authType==='api_key'?EncryptionService::encrypt($credential):null,'access_token_encrypted'=>$authType==='oauth2'?EncryptionService::encrypt($credential):null,'created_by_user_id'=>$this->actorId()]);$me=$client->me($credential);$workspaceGid=$client->workspaceGid();$this->repo->updateConnectionCheck((string)$connection['public_id'],true,'',$workspaceGid);return JsonResponse::success('WORKSECTION_CONNECTION_CREATED','Connection created and verified',['connection'=>$this->publicConnection($this->repo->getConnection((string)$connection['public_id'])??$connection),'source'=>$me],201);}catch(\Throwable $e){if(is_array($connection)&&!empty($connection['public_id']))$this->repo->updateConnectionCheck((string)$connection['public_id'],false,'Worksection connection test failed');$code=$e->getMessage()==='WORKSECTION_AUTH_FAILED'?'WORKSECTION_AUTH_FAILED':($e->getMessage()==='WORKSECTION_ACCOUNT_URL_INVALID'?'WORKSECTION_ACCOUNT_URL_INVALID':'WORKSECTION_CONNECTION_TEST_FAILED');return JsonResponse::error($code,'Worksection credentials or account URL could not be verified',422);}
    }

    public function getConnection(array $params): JsonResponse { $r=$this->connection((string)($params['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('WORKSECTION_CONNECTION','OK',['connection'=>$this->publicConnection($r)]); }

    public function updateConnection(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$input=$this->body();$update=[];
        if(array_key_exists('name',$input))$update['name']=mb_substr(trim((string)$input['name']),0,255);
        if(array_key_exists('account_url',$input)){$url=trim((string)$input['account_url']);try{new WorksectionClient($this->repo,$url);}catch(\Throwable){return JsonResponse::error('WORKSECTION_ACCOUNT_URL_INVALID','account_url must be an HTTPS Worksection account URL',422);}$update['account_url']=$url;}
        $authType=(string)($r['auth_type']??'api_key');
        $newKey=trim((string)($input['api_key']??''));if($newKey!==''&&$authType==='api_key')$update['api_key_encrypted']=EncryptionService::encrypt($newKey);
        $newToken=trim((string)($input['access_token']??''));if($newToken!==''&&$authType==='oauth2')$update['access_token_encrypted']=EncryptionService::encrypt($newToken);
        $this->repo->updateConnection((string)$params['public_id'],$update);if($newKey!==''||$newToken!==''||isset($update['account_url']))$this->repo->updateConnectionCheck((string)$params['public_id'],false,'Credentials or account URL changed; test required');return JsonResponse::success('WORKSECTION_CONNECTION_UPDATED','Connection updated',['connection'=>$this->publicConnection($this->repo->getConnection((string)$params['public_id'])??[]) ]);
    }

    public function deleteConnection(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;if($this->repo->hasRunningJobs((int)$r['id']))return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS','Cancel running jobs before deleting the connection',409);try{$this->repo->deleteConnection((int)$r['id']);return JsonResponse::success('WORKSECTION_CONNECTION_DELETED','Connection deleted');}catch(\Throwable){return JsonResponse::error('WORKSECTION_CONNECTION_DELETE_FAILED','Connection could not be deleted',409);}
    }

    public function testConnection(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;try{$client=$this->client($r);$token=$this->credential($r);$source=$client->me($token);$this->repo->updateConnectionCheck((string)$r['public_id'],true);return JsonResponse::success('WORKSECTION_CONNECTION_TEST_OK','Connection successful',['source'=>$source]);}catch(\Throwable){$this->repo->updateConnectionCheck((string)$r['public_id'],false,'Worksection connection test failed');return JsonResponse::error('WORKSECTION_CONNECTION_TEST_FAILED','Worksection connection test failed',400);}
    }

    public function listWorkspaces(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;try{$client=$this->client($r);return JsonResponse::success('WORKSECTION_ACCOUNTS_LIST','OK',['workspaces'=>$client->workspaces($this->credential($r),(string)($r['workspace_gid']??''))]);}catch(\Throwable){return JsonResponse::error('WORKSECTION_ACCOUNTS_FAILED','Could not validate the Worksection account URL',400);}
    }

    public function discover(array $params): JsonResponse
    {
        $r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$token=$this->credential($r);try{$client=$this->client($r);$includeArchived=!empty($this->body()['include_archived']);$projects=$client->projects($token,$includeArchived);$groups=$client->projectGroups($token);foreach($client->users($token)as$user){$this->repo->upsertUserMapping((int)$r['id'],$user);}return JsonResponse::success('WORKSECTION_DISCOVERY_COMPLETE','Worksection source discovered',['account_id'=>(string)($r['workspace_gid']??$client->workspaceGid()),'projects'=>$projects,'project_groups'=>$groups,'user_mappings'=>$this->repo->listUserMappings((int)$r['id'])]);}catch(\Throwable){return JsonResponse::error('WORKSECTION_DISCOVERY_FAILED','Worksection discovery failed',400);}
    }

    public function listUserMappings(array $params): JsonResponse {$r=$this->connection((string)($params['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('WORKSECTION_USER_MAPPINGS','OK',['items'=>$this->repo->listUserMappings((int)$r['id'])]);}
    public function updateUserMapping(array $params): JsonResponse {$r=$this->connection((string)($params['public_id']??''));if($r instanceof JsonResponse)return$r;$input=$this->body();$crm=!empty($input['crm_user_public_id'])?(string)$input['crm_user_public_id']:null;if($crm!==null&&$this->repo->activeUserPublicId($crm)===null)return JsonResponse::error('USER_NOT_FOUND','Active CRM user not found',404);if(!$this->repo->updateUserMapping((int)$r['id'],(int)($params['mapping_id']??0),$crm))return JsonResponse::error('MAPPING_NOT_FOUND','User mapping not found',404);return JsonResponse::success('WORKSECTION_USER_MAPPING_UPDATED','Mapping updated');}
    public function listJobs(): JsonResponse {return JsonResponse::success('WORKSECTION_JOBS_LIST','OK',['items'=>$this->repo->listJobs($this->actorId(),$this->can('module.worksection-migration.manage'))]);}

    public function createJob(): JsonResponse {return$this->withIdempotency(fn():JsonResponse=>$this->createJobInternal());}
    private function createJobInternal(): JsonResponse
    {
        $input=$this->body();$connection=$this->connection((string)($input['connection_public_id']??''));if($connection instanceof JsonResponse)return$connection;$mode=(string)($input['mode']??'import');if(!in_array($mode,['import','sync','dry_run'],true))return JsonResponse::error('VALIDATION_ERROR','mode must be import, sync or dry_run',422);$options=(array)($input['target_options']??$input['options']??[]);$includeTime=array_key_exists('include_time_records',$options)?(bool)$options['include_time_records']:true;$options['include_time_records']=$includeTime;if($mode!=='dry_run'&&$includeTime&&empty($this->actor()['is_root']))return JsonResponse::error('WORKSECTION_ROOT_REQUIRED','Imports of historical time records require a root user',403);try{$token=$this->credential($connection);$this->client($connection)->me($token);}catch(\Throwable){return JsonResponse::error('WORKSECTION_SOURCE_UNAVAILABLE','Worksection API is unavailable',422);}$scope=(array)($input['source_scope']??[]);$scope['project_ids']=array_values(array_filter(array_map('strval',(array)($input['project_ids']??$scope['project_ids']??[]))));$scope['include_archived']=array_key_exists('include_archived',$scope)?(bool)$scope['include_archived']:(bool)($options['include_archived']??false);$scope['include_completed']=array_key_exists('include_completed',$scope)?(bool)$scope['include_completed']:(bool)($options['include_completed']??true);$scope['max_tasks']=max(0,(int)($input['max_tasks']??$scope['max_tasks']??0));$job=$this->repo->createJob(['connection_id'=>(int)$connection['id'],'workspace_gid'=>(string)($connection['workspace_gid']??$this->client($connection)->workspaceGid()),'mode'=>$mode,'source_scope'=>$scope,'target_options'=>$options,'created_by_user_id'=>$this->actorId()]);return JsonResponse::success('WORKSECTION_JOB_CREATED','Job created',['job'=>$job],201);
    }
    public function getJob(array $params): JsonResponse {$r=$this->job((string)($params['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('WORKSECTION_JOB','OK',['job'=>$r]);}
    public function startJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'queued','WORKSECTION_JOB_QUEUED');}
    public function pauseJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'pausing','WORKSECTION_JOB_PAUSING');}
    public function resumeJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'queued','WORKSECTION_JOB_RESUMED');}
    public function cancelJob(array $params): JsonResponse{return$this->changeJob((string)$params['public_id'],'cancelling','WORKSECTION_JOB_CANCELLING');}
    public function retryFailed(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);if($r instanceof JsonResponse)return$r;if(!in_array((string)($r['status']??''),['completed_with_warnings','failed','cancelled'],true))return JsonResponse::error('INVALID_JOB_STATUS','Only a finished job can be retried',409);$count=$this->repo->retryJob((string)$params['public_id']);return$count===null?JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409):JsonResponse::success('WORKSECTION_JOB_RETRY_QUEUED','Failed items queued',['reset_items'=>$count]);}
    public function rollbackJob(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);if($r instanceof JsonResponse)return$r;if(empty($this->actor()['is_root']))return JsonResponse::error('ROOT_REQUIRED','Only a root user may roll back imported CRM data',403);try{$this->buildImportService((string)$params['public_id'])->rollback((string)$params['public_id'],$this->actor());return JsonResponse::success('WORKSECTION_JOB_ROLLED_BACK','Job targets rolled back');}catch(\Throwable){return JsonResponse::error('WORKSECTION_ROLLBACK_FAILED','Rollback failed; inspect the migration log',409);}}
    public function listJobItems(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);if($r instanceof JsonResponse)return$r;$input=$this->container->get('request')->allInput();return JsonResponse::success('WORKSECTION_JOB_ITEMS','OK',['items'=>$this->repo->items((int)$r['id'],!empty($input['status'])?(string)$input['status']:null,max(1,min(1000,(int)($input['limit']??200))))]);}
    public function listJobLogs(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('WORKSECTION_JOB_LOGS','OK',['items'=>$this->repo->logs((int)$r['id'])]);}
    public function getReport(array $params): JsonResponse {$r=$this->job((string)$params['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('WORKSECTION_JOB_REPORT','OK',['report'=>$this->repo->report((string)$params['public_id'])]);}

    private function changeJob(string $id,string $status,string $code): JsonResponse {$r=$this->job($id);if($r instanceof JsonResponse)return$r;$current=(string)($r['status']??'');$allowed=match($status){'queued'=>in_array($current,['draft','paused','failed','cancelled'],true),'pausing'=>in_array($current,['queued','running'],true),'cancelling'=>in_array($current,['draft','queued','running','paused','pausing'],true),default=>false};if(!$allowed)return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be changed from status: '.$current,409);$requested=$status;if($current==='queued'&&$status==='pausing')$requested='paused';if(in_array($current,['draft','queued','paused','pausing'],true)&&$status==='cancelling')$requested='cancelled';if(!$this->repo->requestStatus($id,$requested))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success($code,'Job state updated');}
    private function credential(array $connection): string
    {
        $authType=(string)($connection['auth_type']??'api_key');
        $token=$authType==='oauth2'?EncryptionService::decrypt((string)($connection['access_token_encrypted']??'')):EncryptionService::decrypt((string)($connection['api_key_encrypted']??''));
        if($token===null||$token==='')throw new \RuntimeException('WORKSECTION_CREDENTIAL_DECRYPT_FAILED');return$token;
    }
    private function client(array $connection): WorksectionClient {$client=new WorksectionClient($this->repo,(string)($connection['account_url']??''));$client->setAuthType((string)($connection['auth_type']??'api_key'));$client->setConnectionId((int)$connection['id']);return$client;}
    private function buildImportService(string $jobPublicId): WorksectionImportService {$job=$this->repo->getJob($jobPublicId);$connection=is_array($job)?$this->repo->getConnectionById((int)$job['connection_id']):null;if(!is_array($connection))throw new \RuntimeException('WORKSECTION_CONNECTION_NOT_FOUND');$client=$this->client($connection);$crawler=new WorksectionCrawler($client,$this->repo);$writer=new WorksectionTargetWriter($this->container,$this->repo,$client);return new WorksectionImportService($this->repo,$client,$crawler,$writer);}
    private function withIdempotency(callable $producer): JsonResponse {if(!$this->container->has('service.idempotency'))return$producer();$service=$this->container->get('service.idempotency');$request=$this->container->get('request');$auth=$this->container->has('auth_user')?$this->container->get('auth_user'):null;$actor=is_array($auth)&&is_array($auth['user']??null)?$auth['user']:null;$replayed=$service->replay($request,$actor);if($replayed instanceof JsonResponse)return$replayed;$response=$producer();$service->remember($request,$actor,$response);return$response;}
    private function publicConnection(array $connection): array {unset($connection['api_key_encrypted'],$connection['access_token_encrypted'],$connection['refresh_token_encrypted'],$connection['client_id_encrypted'],$connection['client_secret_encrypted']);return$connection;}
}
