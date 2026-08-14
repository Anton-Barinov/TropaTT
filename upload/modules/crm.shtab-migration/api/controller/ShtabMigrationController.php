<?php
declare(strict_types=1);

namespace Module\Crm\ShtabMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\ShtabMigration\Repository\ShtabMigrationRepository;
use Module\Crm\ShtabMigration\Service\ShtabExportCrawler;
use Module\Crm\ShtabMigration\Service\ShtabExportParser;
use Module\Crm\ShtabMigration\Service\ShtabImportService;
use Module\Crm\ShtabMigration\Service\ShtabTargetWriter;
use RuntimeException;

final class ShtabMigrationController
{
    private ShtabMigrationRepository $repo;
    public function __construct(private readonly Container $container){$this->repo=new ShtabMigrationRepository($container->get('db.pdo'));}
    private function body(): array {$request=$this->container->get('request');$value=json_decode((string)($request->rawBody??''),true);return is_array($value)?$value:($request->allInput()??[]);}
    private function actor(): array {$auth=$this->container->has('auth_user')?$this->container->get('auth_user'):[];return is_array($auth)&&is_array($auth['user']??null)?$auth['user']:[];}
    private function actorId(): int {return(int)($this->actor()['id']??0);}
    private function can(string $permission): bool {$actor=$this->actor();return!empty($actor['is_root'])||in_array('*',(array)($actor['permission_codes']??[]),true)||in_array($permission,(array)($actor['permission_codes']??[]),true);}
    private function connection(string $id): array|JsonResponse {$row=$this->repo->getConnection($id);if(!$row)return JsonResponse::error('NOT_FOUND','Shtab connection not found',404);if(!$this->can('module.shtab-migration.manage')&&(int)$row['created_by_user_id']!==$this->actorId())return JsonResponse::error('FORBIDDEN','Connection access denied',403);return$row;}
    private function job(string $id): array|JsonResponse {$row=$this->repo->getJob($id);if(!$row)return JsonResponse::error('NOT_FOUND','Shtab job not found',404);if(!$this->can('module.shtab-migration.manage')&&(int)$row['created_by_user_id']!==$this->actorId())return JsonResponse::error('FORBIDDEN','Job access denied',403);return$row;}

    public function listConnections(): JsonResponse {return JsonResponse::success('SHTAB_CONNECTIONS_LIST','OK',['connections'=>$this->repo->listConnections($this->actorId(),$this->can('module.shtab-migration.manage'))]);}
    public function createConnection(): JsonResponse { return $this->withIdempotency(fn(): JsonResponse => $this->createConnectionInternal()); }
    private function createConnectionInternal(): JsonResponse {$in=$this->body();$name=trim((string)($in['name']??''));if($name==='')return JsonResponse::error('VALIDATION_ERROR','name is required',422);$row=$this->repo->createConnection(mb_substr($name,0,255),$this->actorId());return JsonResponse::success('SHTAB_CONNECTION_CREATED','Export profile created',['connection'=>$row],201);}
    public function getConnection(array $p): JsonResponse {$row=$this->connection((string)($p['public_id']??''));return$row instanceof JsonResponse?$row:JsonResponse::success('SHTAB_CONNECTION','OK',['connection'=>$row]);}
    public function deleteConnection(array $p): JsonResponse {$row=$this->connection((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;if($this->repo->hasRunningJobs((int)$row['id']))return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS','Cancel running jobs first',409);$this->repo->deleteConnection((int)$row['id']);return JsonResponse::success('SHTAB_CONNECTION_DELETED','Connection deleted');}
    public function testConnection(array $p): JsonResponse {$row=$this->connection((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;return JsonResponse::success('SHTAB_EXPORT_MODE','No remote API is contacted; upload an official CSV/XLSX export to create a job',['source'=>'official_export_only','supported_extensions'=>['csv','txt','xlsx']]);}
    public function listUserMappings(array $p): JsonResponse {$row=$this->connection((string)($p['public_id']??''));return$row instanceof JsonResponse?$row:JsonResponse::success('SHTAB_USER_MAPPINGS','OK',['items'=>$this->repo->listUserMappings((int)$row['id'])]);}
    public function listCrmUsers(array $p): JsonResponse {$row=$this->connection((string)($p['public_id']??''));return$row instanceof JsonResponse?$row:JsonResponse::success('SHTAB_CRM_USERS','OK',['items'=>$this->repo->activeCrmUsers()]);}
    public function updateUserMapping(array $p): JsonResponse {$row=$this->connection((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;$in=$this->body();$crm=!empty($in['crm_user_public_id'])?(string)$in['crm_user_public_id']:null;if($crm!==null&&$this->repo->activeUserPublicId($crm)===null)return JsonResponse::error('USER_NOT_FOUND','Active CRM user not found',404);if(!$this->repo->updateUserMapping((int)$row['id'],(int)($p['mapping_id']??0),$crm))return JsonResponse::error('MAPPING_NOT_FOUND','Mapping not found',404);return JsonResponse::success('SHTAB_USER_MAPPING_UPDATED','Mapping updated');}

    public function createJob(): JsonResponse { return $this->withIdempotency(fn(): JsonResponse => $this->createJobInternal()); }
    private function createJobInternal(): JsonResponse
    {
        $in=$this->body();$connection=$this->connection((string)($in['connection_public_id']??''));if($connection instanceof JsonResponse)return$connection;$mode=(string)($in['mode']??'import');if(!in_array($mode,['import','sync','dry_run'],true))return JsonResponse::error('VALIDATION_ERROR','mode must be import, sync or dry_run',422);if($mode!=='dry_run'&&empty($this->actor()['is_root']))return JsonResponse::error('ROOT_REQUIRED','Only a root user may run an import or sync',403);
        $name=trim((string)($in['file_name']??$in['name']??'shtab-export.csv'));$ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));if(!in_array($ext,['csv','txt','xlsx'],true))return JsonResponse::error('SHTAB_UNSUPPORTED_FILE_FORMAT','Only CSV, TXT and XLSX exports are accepted',422);$raw=(string)($in['content_base64']??'');if(str_contains($raw,','))$raw=substr($raw,strpos($raw,',')+1);$bytes=base64_decode($raw,true);if(!is_string($bytes)||$bytes==='')return JsonResponse::error('SHTAB_FILE_REQUIRED','content_base64 is required',422);if(strlen($bytes)>20*1024*1024)return JsonResponse::error('SHTAB_FILE_TOO_LARGE','The export is limited to 20 MB',422);
        $base=getenv('CRM_STORAGE_BASE')?:dirname(__DIR__,4).'/storage_api';$dir=rtrim((string)$base,'/').'/temp/shtab-migration';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))return JsonResponse::error('SHTAB_STORAGE_FAILED','Could not create private import storage',500);$path=$dir.'/'.bin2hex(random_bytes(16)).'.'.$ext;if(file_put_contents($path,$bytes,LOCK_EX)!==strlen($bytes)){@unlink($path);return JsonResponse::error('SHTAB_STORAGE_FAILED','Could not store the export',500);}
        $scope=(array)($in['source_scope']??[]);$scope['entity_type']=(string)($in['entity_type']??$scope['entity_type']??'auto');
        try {
            $job=$this->repo->createJob(['connection_id'=>(int)$connection['id'],'mode'=>$mode,'source_scope'=>$scope,'source_file_path'=>$path,'source_file_name'=>mb_substr(basename($name),0,255),'created_by_user_id'=>$this->actorId()]);
        } catch (\Throwable) {
            @unlink($path);
            return JsonResponse::error('SHTAB_JOB_CREATE_FAILED','Could not create the migration job',500);
        }
        return JsonResponse::success('SHTAB_JOB_CREATED','Shtab export job created',['job'=>$this->publicJob($job)],201);
    }
    public function listJobs(): JsonResponse {return JsonResponse::success('SHTAB_JOBS_LIST','OK',['items'=>array_map([$this,'publicJob'],$this->repo->listJobs($this->actorId(),$this->can('module.shtab-migration.manage')))]);}
    public function getJob(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));return$row instanceof JsonResponse?$row:JsonResponse::success('SHTAB_JOB','OK',['job'=>$this->publicJob($row)]);}
    public function startJob(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;if(($row['mode']??'import')!=='dry_run'&&empty($this->actor()['is_root']))return JsonResponse::error('ROOT_REQUIRED','Only a root user may run an import or sync',403);return$this->changeJob((string)($p['public_id']??''),'queued','SHTAB_JOB_QUEUED');}
    public function pauseJob(array $p): JsonResponse
    {
        $id=(string)($p['public_id']??'');
        $row=$this->job($id);
        if($row instanceof JsonResponse)return$row;
        $current=(string)($row['status']??'');
        if($current==='queued'){
            if(!$this->repo->requestStatus($id,'paused'))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);
            return JsonResponse::success('SHTAB_JOB_PAUSED','Job paused');
        }
        if($current==='running')return$this->changeJob($id,'pausing','SHTAB_JOB_PAUSING');
        return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be paused from '.$current,409);
    }
    public function cancelJob(array $p): JsonResponse
    {
        $id=(string)($p['public_id']??'');
        $row=$this->job($id);
        if($row instanceof JsonResponse)return$row;
        $current=(string)($row['status']??'');
        if(in_array($current,['draft','queued','paused'],true)){
            if(!$this->repo->requestStatus($id,'cancelled'))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);
            // Keep an untouched upload available for retry when crawl has not
            // started yet; after items exist, the durable job payload is enough.
            if($this->repo->itemCount((int)$row['id'])>0)$this->repo->cleanupJobFile((int)$row['id']);
            return JsonResponse::success('SHTAB_JOB_CANCELLED','Job cancelled');
        }
        if(in_array($current,['running','pausing'],true))return$this->changeJob($id,'cancelling','SHTAB_JOB_CANCELLING');
        return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be cancelled from '.$current,409);
    }
    public function retryFailed(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;if(($row['mode']??'import')!=='dry_run'&&empty($this->actor()['is_root']))return JsonResponse::error('ROOT_REQUIRED','Only a root user may retry an import or sync',403);$n=$this->repo->retryJob((string)$p['public_id']);if($n===null)return JsonResponse::error('INVALID_JOB_STATUS','Only finished jobs can be retried',409);if($n===0&&($row['status']??'')==='completed_with_warnings')return JsonResponse::error('NO_RETRYABLE_ITEMS','There are no retryable failed items; fix the source collision or create a new job',409);return JsonResponse::success('SHTAB_JOB_RETRY_QUEUED','Failed items queued for retry',['reset_items'=>$n]);}
    public function rollbackJob(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;if(empty($this->actor()['is_root']))return JsonResponse::error('ROOT_REQUIRED','Only a root user may roll back imported CRM data',403);if(!in_array((string)($row['status']??''),['completed','completed_with_warnings','failed','cancelled','rolled_back','rolled_back_with_warnings'],true))return JsonResponse::error('INVALID_JOB_STATUS','Only finished jobs can be rolled back',409);try{$repo=$this->repo;$service=new ShtabImportService($repo,new ShtabExportCrawler(new ShtabExportParser(),$repo),new ShtabTargetWriter($this->container,$repo));$service->rollback((string)$p['public_id'],$this->actor());return JsonResponse::success('SHTAB_JOB_ROLLED_BACK','Job targets rolled back');}catch(\Throwable){return JsonResponse::error('SHTAB_ROLLBACK_FAILED','Rollback failed; inspect migration log',409);}}
    public function listJobItems(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));if($row instanceof JsonResponse)return$row;$request=$this->container->get('request');$input=$request->allInput();return JsonResponse::success('SHTAB_JOB_ITEMS','OK',['items'=>$this->repo->items((int)$row['id'],!empty($input['status'])?(string)$input['status']:null,max(1,min(1000,(int)($input['limit']??200))))]);}
    public function listJobLogs(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));return$row instanceof JsonResponse?$row:JsonResponse::success('SHTAB_JOB_LOGS','OK',['items'=>$this->repo->logs((int)$row['id'])]);}
    public function getReport(array $p): JsonResponse {$row=$this->job((string)($p['public_id']??''));return$row instanceof JsonResponse?$row:JsonResponse::success('SHTAB_JOB_REPORT','OK',['report'=>$this->publicJob($this->repo->report((string)$p['public_id']))]);}
    private function changeJob(string $id,string $status,string $code): JsonResponse
    {
        $row=$this->job($id);
        if($row instanceof JsonResponse)return$row;
        $current=(string)($row['status']??'');
        $allowed=$status==='queued'
            ? in_array($current,['draft','paused'],true)
            : ($status==='pausing'
                ? $current==='running'
                : ($status==='cancelling'&&in_array($current,['running','pausing'],true)));
        if(!$allowed)return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be changed from '.$current,409);
        if(!$this->repo->requestStatus($id,$status))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);
        return JsonResponse::success($code,'Job state updated');
    }
    /** @param callable():JsonResponse $producer */
    private function withIdempotency(callable $producer): JsonResponse
    {
        if (!$this->container->has('service.idempotency')) return $producer();
        $service=$this->container->get('service.idempotency');$request=$this->container->get('request');$auth=$this->container->has('auth_user')?$this->container->get('auth_user'):null;$actor=is_array($auth)&&is_array($auth['user']??null)?$auth['user']:null;
        $replayed=$service->replay($request,$actor);if($replayed instanceof JsonResponse)return$replayed;$response=$producer();$service->remember($request,$actor,$response);return$response;
    }
    private function publicJob(array $job): array {unset($job['source_file_path'],$job['lease_token'],$job['lease_until']);return$job;}
}
