<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;
use Module\Crm\TodoistMigration\Service\EncryptionService;
use Module\Crm\TodoistMigration\Service\TodoistClient;
use Module\Crm\TodoistMigration\Service\TodoistCrawler;
use Module\Crm\TodoistMigration\Service\TodoistImportService;
use Module\Crm\TodoistMigration\Service\TodoistTargetWriter;

final class TodoistMigrationController
{
    private TodoistMigrationRepository $repo;
    public function __construct(private readonly Container $container){$this->repo=new TodoistMigrationRepository($container->get('db.pdo'));}
    private function body():array{$r=$this->container->get('request');$v=json_decode((string)($r->rawBody??''),true);return is_array($v)?$v:($r->allInput()??[]);}
    private function actor():array{$a=$this->container->has('auth_user')?$this->container->get('auth_user'):[];return is_array($a)&&is_array($a['user']??null)?$a['user']:[];}
    private function actorId():int{return(int)($this->actor()['id']??0);}
    private function can(string $p):bool{$a=$this->actor();return!empty($a['is_root'])||in_array('*',(array)($a['permission_codes']??[]),true)||in_array($p,(array)($a['permission_codes']??[]),true);}
    private function connection(string $id):array|JsonResponse{$r=$this->repo->getConnection($id);if(!$r)return JsonResponse::error('NOT_FOUND','Todoist connection not found',404);if(!$this->can('module.todoist-migration.manage')&&(int)$r['created_by_user_id']!==$this->actorId())return JsonResponse::error('FORBIDDEN','Connection access denied',403);return$r;}
    private function job(string $id):array|JsonResponse{$r=$this->repo->getJob($id);if(!$r)return JsonResponse::error('NOT_FOUND','Todoist job not found',404);if(!$this->can('module.todoist-migration.manage')&&(int)$r['created_by_user_id']!==$this->actorId())return JsonResponse::error('FORBIDDEN','Job access denied',403);return$r;}
    public function listConnections():JsonResponse{return JsonResponse::success('TODOIST_CONNECTIONS_LIST','OK',['connections'=>array_map([$this,'publicConnection'],$this->repo->listConnections($this->actorId(),$this->can('module.todoist-migration.manage')))]);}
    public function oauthAuthorizeUrl(): JsonResponse
    {
        $in = $this->body();
        $clientId = trim((string)($in['client_id'] ?? ''));
        $redirectUri = trim((string)($in['redirect_uri'] ?? ''));
        if ($clientId === '' || $redirectUri === '' || !str_starts_with($redirectUri, 'https://')) return JsonResponse::error('VALIDATION_ERROR', 'client_id and an HTTPS redirect_uri are required', 422);
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $state = bin2hex(random_bytes(24));
        $_SESSION['todoist_oauth_state'] = hash_hmac('sha256', $state, (string)($this->actorId()) . ':' . $clientId . ':' . $redirectUri);
        $_SESSION['todoist_oauth_state_expires'] = time() + 600;
        $_SESSION['todoist_oauth_client_id'] = $clientId;
        $_SESSION['todoist_oauth_redirect_uri'] = $redirectUri;
        $client = new TodoistClient($this->repo);
        return JsonResponse::success('TODOIST_OAUTH_AUTHORIZE_URL', 'Open the authorization URL and return the code and state', ['authorization_url' => $client->oauthAuthorizeUrl($clientId, $state, 'data:read_write', $redirectUri), 'state_expires_at' => $_SESSION['todoist_oauth_state_expires']]);
    }

    public function createConnection(): JsonResponse
    {
        $in = $this->body();
        $name = trim((string)($in['name'] ?? ''));
        $token = trim((string)($in['access_token'] ?? $in['token'] ?? ''));
        if ($name === '' || $token === '') return JsonResponse::error('VALIDATION_ERROR', 'name and access_token are required', 422);
        $connection = null;
        try {
            $connection = $this->repo->createConnection([
                'name' => mb_substr($name, 0, 255),
                'auth_type' => 'pat',
                'access_token_encrypted' => EncryptionService::encrypt($token),
                'created_by_user_id' => $this->actorId(),
            ]);
            $client = new TodoistClient($this->repo);
            $client->setConnectionId((int)$connection['id']);
            $info = $client->test($token);
            $this->repo->updateConnectionCheck((string)$connection['public_id'], true, '', ['id' => null, 'name' => 'Todoist account']);
            return JsonResponse::success('TODOIST_CONNECTION_CREATED', 'Connection created and verified', ['connection' => $this->publicConnection($this->repo->getConnection((string)$connection['public_id']) ?? $connection), 'account' => $info], 201);
        } catch (\Throwable $e) {
            if (is_array($connection) && !empty($connection['public_id'])) $this->repo->updateConnectionCheck((string)$connection['public_id'], false, 'Todoist connection test failed');
            return JsonResponse::error($e->getMessage() === 'TODOIST_AUTH_FAILED' ? 'TODOIST_AUTH_FAILED' : 'TODOIST_CONNECTION_TEST_FAILED', 'Todoist credentials could not be verified', 422);
        }
    }

    public function exchangeOAuth(): JsonResponse
    {
        $in = $this->body();
        $name = trim((string)($in['name'] ?? ''));
        $clientId = trim((string)($in['client_id'] ?? ''));
        $clientSecret = (string)($in['client_secret'] ?? '');
        $code = trim((string)($in['code'] ?? ''));
        $state = trim((string)($in['state'] ?? ''));
        if ($name === '' || $clientId === '' || $clientSecret === '' || $code === '' || $state === '') return JsonResponse::error('VALIDATION_ERROR', 'name, client_id, client_secret, code and state are required', 422);
        if (!$this->validOAuthState($state, $clientId, trim((string)($in['redirect_uri'] ?? '')))) return JsonResponse::error('TODOIST_OAUTH_STATE_INVALID', 'OAuth state is invalid or expired', 422);
        $connection = null;
        try {
            $client = new TodoistClient($this->repo);
            $tokens = $client->oauthExchange($clientId, $clientSecret, $code, !empty($in['redirect_uri']) ? (string)$in['redirect_uri'] : null);
            $connection = $this->repo->createConnection([
                'name' => mb_substr($name, 0, 255),
                'auth_type' => 'oauth2',
                'access_token_encrypted' => EncryptionService::encrypt((string)$tokens['access_token']),
                'refresh_token_encrypted' => !empty($tokens['refresh_token']) ? EncryptionService::encrypt((string)$tokens['refresh_token']) : null,
                'client_id_encrypted' => EncryptionService::encrypt($clientId),
                'client_secret_encrypted' => EncryptionService::encrypt($clientSecret),
                'created_by_user_id' => $this->actorId(),
            ]);
            $client->setConnectionId((int)$connection['id']);
            $info = $client->test((string)$tokens['access_token']);
            $this->repo->updateConnectionCheck((string)$connection['public_id'], true, '', ['id' => null, 'name' => 'Todoist OAuth account']);
            return JsonResponse::success('TODOIST_OAUTH_CONNECTED', 'OAuth connection created and verified', ['connection' => $this->publicConnection($this->repo->getConnection((string)$connection['public_id']) ?? $connection), 'account' => $info], 201);
        } catch (\Throwable) {
            if (is_array($connection) && !empty($connection['id'])) { try { $this->repo->deleteConnection((int)$connection['id']); } catch (\Throwable) {} }
            return JsonResponse::error('TODOIST_OAUTH_EXCHANGE_FAILED', 'Todoist OAuth authorization could not be completed', 422);
        }
    }

    private function validOAuthState(string $state, string $clientId, string $redirectUri): bool
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $expires = (int)($_SESSION['todoist_oauth_state_expires'] ?? 0);
        $expected = (string)($_SESSION['todoist_oauth_state'] ?? '');
        $expectedClient = (string)($_SESSION['todoist_oauth_client_id'] ?? '');
        $expectedRedirect = (string)($_SESSION['todoist_oauth_redirect_uri'] ?? '');
        $valid = $expires >= time() && $expected !== '' && $expectedClient === $clientId && $expectedRedirect === $redirectUri && hash_equals($expected, hash_hmac('sha256', $state, (string)($this->actorId()) . ':' . $clientId . ':' . $redirectUri));
        if ($valid) { unset($_SESSION['todoist_oauth_state'], $_SESSION['todoist_oauth_state_expires'], $_SESSION['todoist_oauth_client_id'], $_SESSION['todoist_oauth_redirect_uri']); }
        return $valid;
    }

    public function getConnection(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('TODOIST_CONNECTION','OK',['connection'=>$this->publicConnection($r)]);}
    public function updateConnection(array $p): JsonResponse
    {
        $r = $this->connection((string)($p['public_id'] ?? ''));
        if ($r instanceof JsonResponse) return $r;
        $in = $this->body();
        $d = [];
        if (array_key_exists('name', $in)) $d['name'] = mb_substr(trim((string)$in['name']), 0, 255);
        $token = trim((string)($in['access_token'] ?? $in['token'] ?? ''));
        if ($token !== '') $d['access_token_encrypted'] = EncryptionService::encrypt($token);
        $this->repo->updateConnection((string)$p['public_id'], $d);
        return JsonResponse::success('TODOIST_CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->publicConnection($this->repo->getConnection((string)$p['public_id']) ?? [])]);
    }
    public function deleteConnection(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;if($this->repo->hasRunningJobs((int)$r['id']))return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS','Cancel running jobs before deletion',409);$this->repo->deleteConnection((int)$r['id']);return JsonResponse::success('TODOIST_CONNECTION_DELETED','Connection deleted');}
    public function testConnection(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;$t=EncryptionService::decrypt((string)$r['access_token_encrypted']);if($t===null)return JsonResponse::error('TODOIST_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{$c=new TodoistClient($this->repo);$c->setConnectionId((int)$r['id']);$info=$c->test($t);$this->repo->updateConnectionCheck((string)$r['public_id'],true);return JsonResponse::success('TODOIST_CONNECTION_TEST_OK','Connection successful',['account'=>$info]);}catch(\Throwable){$this->repo->updateConnectionCheck((string)$r['public_id'],false,'Todoist connection test failed');return JsonResponse::error('TODOIST_CONNECTION_TEST_FAILED','Todoist connection test failed',400);}}
    public function discover(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;$t=EncryptionService::decrypt((string)$r['access_token_encrypted']);if($t===null)return JsonResponse::error('TODOIST_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{$c=new TodoistClient($this->repo);$c->setConnectionId((int)$r['id']);$projects=$c->projects($t,true);$warnings=[];foreach($projects as $project){$projectId=(string)($project['id']??'');if($projectId==='')continue;try{$c->eachCollaborators($t,$projectId,function(array $user)use($r):void{if(!empty($user['id']))$this->repo->upsertUserMapping((int)$r['id'],$user);});}catch(\Throwable){$warnings[]=$projectId;}}return JsonResponse::success('TODOIST_DISCOVERY_COMPLETE','Todoist projects discovered',['projects'=>$projects,'user_mappings'=>$this->repo->listUserMappings((int)$r['id']),'warnings'=>$warnings]);}catch(\Throwable){return JsonResponse::error('TODOIST_DISCOVERY_FAILED','Could not load Todoist projects',400);}}
    public function listUserMappings(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('TODOIST_USER_MAPPINGS','OK',['items'=>$this->repo->listUserMappings((int)$r['id'])]);}
    public function updateUserMapping(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;$in=$this->body();$crm=!empty($in['crm_user_public_id'])?(string)$in['crm_user_public_id']:null;if($crm!==null&&$this->repo->activeUserPublicId($crm)===null)return JsonResponse::error('USER_NOT_FOUND','Active CRM user not found',404);if(!$this->repo->updateUserMapping((int)$r['id'],(int)($p['mapping_id']??0),$crm))return JsonResponse::error('MAPPING_NOT_FOUND','Mapping not found',404);return JsonResponse::success('TODOIST_USER_MAPPING_UPDATED','Mapping updated');}
    public function listJobs():JsonResponse{return JsonResponse::success('TODOIST_JOBS_LIST','OK',['items'=>$this->repo->listJobs($this->actorId(),$this->can('module.todoist-migration.manage'))]);}
    public function createJob():JsonResponse{$in=$this->body();$r=$this->connection((string)($in['connection_public_id']??''));if($r instanceof JsonResponse)return$r;$mode=(string)($in['mode']??'import');if(!in_array($mode,['import','sync','dry_run'],true))return JsonResponse::error('VALIDATION_ERROR','mode must be import, sync or dry_run',422);$scope=(array)($in['source_scope']??[]);$scope['project_ids']=array_values(array_filter(array_map('strval',(array)($in['project_ids']??$scope['project_ids']??[]))));$scope['max_tasks']=max(0,(int)($in['max_tasks']??$scope['max_tasks']??0));$job=$this->repo->createJob(['connection_id'=>(int)$r['id'],'mode'=>$mode,'source_scope'=>$scope,'target_options'=>(array)($in['target_options']??$in['options']??[]),'created_by_user_id'=>$this->actorId()]);return JsonResponse::success('TODOIST_JOB_CREATED','Job created',['job'=>$job],201);}
    public function getJob(array $p):JsonResponse{$r=$this->job((string)($p['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('TODOIST_JOB','OK',['job'=>$r]);}
    public function startJob(array $p):JsonResponse{return$this->changeJob((string)$p['public_id'],'queued','TODOIST_JOB_QUEUED');}public function pauseJob(array $p):JsonResponse{return$this->changeJob((string)$p['public_id'],'pausing','TODOIST_JOB_PAUSING');}public function resumeJob(array $p):JsonResponse{return$this->changeJob((string)$p['public_id'],'queued','TODOIST_JOB_RESUMED');}public function cancelJob(array $p):JsonResponse{$r=$this->job((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;if((string)($r['status']??'')==='draft'){if(!$this->repo->requestStatus((string)$p['public_id'],'cancelled'))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success('TODOIST_JOB_CANCELLED','Job cancelled');}return$this->changeJob((string)$p['public_id'],'cancelling','TODOIST_JOB_CANCELLING');}
    public function retryFailed(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);if($r instanceof JsonResponse)return$r;if(!in_array((string)($r['status']??''),['completed_with_warnings','failed','cancelled'],true))return JsonResponse::error('INVALID_JOB_STATUS','Only a finished job can be retried',409);$n=$this->repo->retryJob((string)$p['public_id']);if($n===null)return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success('TODOIST_JOB_RETRY_QUEUED','Failed items queued for retry',['reset_items'=>$n]);}
    public function rollbackJob(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);if($r instanceof JsonResponse)return$r;try{$this->buildImportService()->rollback((string)$p['public_id'],$this->actor());return JsonResponse::success('TODOIST_JOB_ROLLED_BACK','Job targets rolled back');}catch(\Throwable){return JsonResponse::error('TODOIST_ROLLBACK_FAILED','Rollback failed; inspect migration log',409);}}
    public function listJobItems(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);if($r instanceof JsonResponse)return$r;$in=$this->container->get('request')->allInput();return JsonResponse::success('TODOIST_JOB_ITEMS','OK',['items'=>$this->repo->items((int)$r['id'],!empty($in['status'])?(string)$in['status']:null,max(1,min(1000,(int)($in['limit']??200))))]);}
    public function listJobLogs(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('TODOIST_JOB_LOGS','OK',['items'=>$this->repo->logs((int)$r['id'])]);}
    public function getReport(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('TODOIST_JOB_REPORT','OK',['report'=>$this->repo->report((string)$p['public_id'])]);}
    private function changeJob(string $id,string $status,string $code):JsonResponse{$r=$this->job($id);if($r instanceof JsonResponse)return$r;$current=(string)($r['status']??'');$ok=match($status){'queued'=>in_array($current,['draft','paused','failed','cancelled'],true),'pausing'=>in_array($current,['queued','running'],true),'cancelling'=>in_array($current,['draft','queued','running','paused','pausing'],true),default=>false};if(!$ok)return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be changed from status: '.$current,409);if(!$this->repo->requestStatus($id,$status))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success($code,'Job state updated');}
    private function buildImportService():TodoistImportService{$c=new TodoistClient($this->repo);return new TodoistImportService($this->repo,$c,new \Module\Crm\TodoistMigration\Service\TodoistCrawler($c,$this->repo),new TodoistTargetWriter($this->container,$this->repo,$c));}
    private function publicConnection(array $c):array{unset($c['access_token_encrypted'],$c['refresh_token_encrypted'],$c['client_id_encrypted'],$c['client_secret_encrypted']);return$c;}
}
