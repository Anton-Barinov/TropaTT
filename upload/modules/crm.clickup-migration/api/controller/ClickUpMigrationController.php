<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\ClickUpMigration\Repository\ClickUpMigrationRepository;
use Module\Crm\ClickUpMigration\Service\EncryptionService;
use Module\Crm\ClickUpMigration\Service\ClickUpClient;
use Module\Crm\ClickUpMigration\Service\ClickUpCrawler;
use Module\Crm\ClickUpMigration\Service\ClickUpImportService;
use Module\Crm\ClickUpMigration\Service\ClickUpTargetWriter;

final class ClickUpMigrationController
{
    private ClickUpMigrationRepository $repo;
    public function __construct(private readonly Container $container){$this->repo=new ClickUpMigrationRepository($container->get('db.pdo'));}
    private function body():array{$r=$this->container->get('request');$v=json_decode((string)($r->rawBody??''),true);return is_array($v)?$v:($r->allInput()??[]);}
    private function actor():array{$a=$this->container->has('auth_user')?$this->container->get('auth_user'):[];return is_array($a)&&is_array($a['user']??null)?$a['user']:[];}
    private function actorId():int{return(int)($this->actor()['id']??0);}
    private function can(string $p):bool{$a=$this->actor();return!empty($a['is_root'])||in_array('*',(array)($a['permission_codes']??[]),true)||in_array($p,(array)($a['permission_codes']??[]),true);}
    private function connection(string $id):array|JsonResponse{$r=$this->repo->getConnection($id);if(!$r)return JsonResponse::error('NOT_FOUND','ClickUp connection not found',404);if(!$this->can('module.clickup-migration.manage')&&(int)$r['created_by_user_id']!==$this->actorId())return JsonResponse::error('FORBIDDEN','Connection access denied',403);return$r;}
    private function job(string $id):array|JsonResponse{$r=$this->repo->getJob($id);if(!$r)return JsonResponse::error('NOT_FOUND','ClickUp job not found',404);if(!$this->can('module.clickup-migration.manage')&&(int)$r['created_by_user_id']!==$this->actorId())return JsonResponse::error('FORBIDDEN','Job access denied',403);return$r;}
    public function listConnections():JsonResponse{return JsonResponse::success('CLICKUP_CONNECTIONS_LIST','OK',['connections'=>array_map([$this,'publicConnection'],$this->repo->listConnections($this->actorId(),$this->can('module.clickup-migration.manage')))]);}
    public function oauthAuthorizeUrl(): JsonResponse
    {
        $in = $this->body();
        $clientId = trim((string)($in['client_id'] ?? ''));
        $redirectUri = trim((string)($in['redirect_uri'] ?? ''));
        if ($clientId === '' || $redirectUri === '' || !str_starts_with($redirectUri, 'https://')) return JsonResponse::error('VALIDATION_ERROR', 'client_id and an HTTPS redirect_uri are required', 422);
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $state = bin2hex(random_bytes(24));
        $_SESSION['clickup_oauth_state'] = hash_hmac('sha256', $state, (string)($this->actorId()) . ':' . $clientId . ':' . $redirectUri);
        $_SESSION['clickup_oauth_state_expires'] = time() + 600;
        $_SESSION['clickup_oauth_client_id'] = $clientId;
        $_SESSION['clickup_oauth_redirect_uri'] = $redirectUri;
        $client = new ClickUpClient($this->repo);
        return JsonResponse::success('CLICKUP_OAUTH_AUTHORIZE_URL', 'Open the authorization URL and return the code and state', ['authorization_url' => $client->oauthAuthorizeUrl($clientId, $state, $redirectUri), 'state_expires_at' => $_SESSION['clickup_oauth_state_expires']]);
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
            $client = new ClickUpClient($this->repo);
            $client->setConnectionId((int)$connection['id']);
            $client->setAuthType('pat');
            $info = $client->test($token);
            $this->repo->updateConnectionCheck((string)$connection['public_id'], true, '', ['id' => null, 'name' => 'ClickUp account']);
            return JsonResponse::success('CLICKUP_CONNECTION_CREATED', 'Connection created and verified', ['connection' => $this->publicConnection($this->repo->getConnection((string)$connection['public_id']) ?? $connection), 'account' => $info], 201);
        } catch (\Throwable $e) {
            if (is_array($connection) && !empty($connection['public_id'])) $this->repo->updateConnectionCheck((string)$connection['public_id'], false, 'ClickUp connection test failed');
            return JsonResponse::error($e->getMessage() === 'CLICKUP_AUTH_FAILED' ? 'CLICKUP_AUTH_FAILED' : 'CLICKUP_CONNECTION_TEST_FAILED', 'ClickUp credentials could not be verified', 422);
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
        if (!$this->validOAuthState($state, $clientId, trim((string)($in['redirect_uri'] ?? '')))) return JsonResponse::error('CLICKUP_OAUTH_STATE_INVALID', 'OAuth state is invalid or expired', 422);
        $connection = null;
        try {
            $client = new ClickUpClient($this->repo);
            $tokens = $client->oauthExchange($clientId, $clientSecret, $code);
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
            $client->setAuthType('oauth2');
            $info = $client->test((string)$tokens['access_token']);
            $this->repo->updateConnectionCheck((string)$connection['public_id'], true, '', ['id' => null, 'name' => 'ClickUp OAuth account']);
            return JsonResponse::success('CLICKUP_OAUTH_CONNECTED', 'OAuth connection created and verified', ['connection' => $this->publicConnection($this->repo->getConnection((string)$connection['public_id']) ?? $connection), 'account' => $info], 201);
        } catch (\Throwable) {
            if (is_array($connection) && !empty($connection['id'])) { try { $this->repo->deleteConnection((int)$connection['id']); } catch (\Throwable) {} }
            return JsonResponse::error('CLICKUP_OAUTH_EXCHANGE_FAILED', 'ClickUp OAuth authorization could not be completed', 422);
        }
    }

    private function validOAuthState(string $state, string $clientId, string $redirectUri): bool
    {
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $expires = (int)($_SESSION['clickup_oauth_state_expires'] ?? 0);
        $expected = (string)($_SESSION['clickup_oauth_state'] ?? '');
        $expectedClient = (string)($_SESSION['clickup_oauth_client_id'] ?? '');
        $expectedRedirect = (string)($_SESSION['clickup_oauth_redirect_uri'] ?? '');
        $valid = $expires >= time() && $expected !== '' && $expectedClient === $clientId && $expectedRedirect === $redirectUri && hash_equals($expected, hash_hmac('sha256', $state, (string)($this->actorId()) . ':' . $clientId . ':' . $redirectUri));
        if ($valid) { unset($_SESSION['clickup_oauth_state'], $_SESSION['clickup_oauth_state_expires'], $_SESSION['clickup_oauth_client_id'], $_SESSION['clickup_oauth_redirect_uri']); }
        return $valid;
    }

    public function getConnection(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('CLICKUP_CONNECTION','OK',['connection'=>$this->publicConnection($r)]);}
    public function updateConnection(array $p): JsonResponse
    {
        $r = $this->connection((string)($p['public_id'] ?? ''));
        if ($r instanceof JsonResponse) return $r;
        $in = $this->body();
        $d = [];
        if (array_key_exists('name', $in)) $d['name'] = mb_substr(trim((string)$in['name']), 0, 255);
        $token = trim((string)($in['access_token'] ?? $in['token'] ?? ''));
        if ($token !== '') { $d['access_token_encrypted'] = EncryptionService::encrypt($token); $this->repo->markConnectionUnverified((string)$p['public_id']); }
        $this->repo->updateConnection((string)$p['public_id'], $d);
        return JsonResponse::success('CLICKUP_CONNECTION_UPDATED', 'Connection updated', ['connection' => $this->publicConnection($this->repo->getConnection((string)$p['public_id']) ?? [])]);
    }
    public function deleteConnection(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;if($this->repo->hasRunningJobs((int)$r['id']))return JsonResponse::error('CONNECTION_HAS_RUNNING_JOBS','Cancel running jobs before deletion',409);$this->repo->deleteConnection((int)$r['id']);return JsonResponse::success('CLICKUP_CONNECTION_DELETED','Connection deleted');}
    public function testConnection(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;$t=EncryptionService::decrypt((string)$r['access_token_encrypted']);if($t===null)return JsonResponse::error('CLICKUP_CREDENTIAL_DECRYPT_FAILED','Could not decrypt credentials',500);try{$c=new ClickUpClient($this->repo);$c->setConnectionId((int)$r['id']);$c->setAuthType((string)($r['auth_type']??'pat'));$info=$c->test($t);$this->repo->updateConnectionCheck((string)$r['public_id'],true);return JsonResponse::success('CLICKUP_CONNECTION_TEST_OK','Connection successful',['account'=>$info]);}catch(\Throwable){$this->repo->updateConnectionCheck((string)$r['public_id'],false,'ClickUp connection test failed');return JsonResponse::error('CLICKUP_CONNECTION_TEST_FAILED','ClickUp connection test failed',400);}}
    public function discover(array $p): JsonResponse
    {
        $r = $this->connection((string)($p['public_id'] ?? ''));
        if ($r instanceof JsonResponse) return $r;
        $token = EncryptionService::decrypt((string)$r['access_token_encrypted']);
        if ($token === null) return JsonResponse::error('CLICKUP_CREDENTIAL_DECRYPT_FAILED', 'Could not decrypt credentials', 500);
        try {
            $client = new ClickUpClient($this->repo);
            $client->setConnectionId((int)$r['id']);
            $client->setAuthType((string)($r['auth_type'] ?? 'pat'));
            $teams = $client->teams($token);
            $spaces = [];
            foreach ($teams as $team) {
                $teamId = (string)($team['id'] ?? '');
                foreach ((array)($team['members'] ?? []) as $member) {
                    $user = is_array($member['user'] ?? null) ? $member['user'] : $member;
                    if (!empty($user['id'])) $this->repo->upsertUserMapping((int)$r['id'], $user);
                }
                if ($teamId === '') continue;
                foreach ($client->spaces($token, $teamId, true) as $space) {
                    $space['_team_id'] = $teamId;
                    $spaces[] = $space;
                }
            }
            return JsonResponse::success('CLICKUP_DISCOVERY_COMPLETE', 'ClickUp hierarchy discovered', ['teams' => $teams, 'projects' => $spaces, 'spaces' => $spaces, 'user_mappings' => $this->repo->listUserMappings((int)$r['id']), 'warnings' => ['Folders, lists and tasks are loaded by the queued job to keep discovery safe on shared hosting.']]);
        } catch (\Throwable) {
            return JsonResponse::error('CLICKUP_DISCOVERY_FAILED', 'Could not load ClickUp hierarchy', 400);
        }
    }
    public function listUserMappings(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('CLICKUP_USER_MAPPINGS','OK',['items'=>$this->repo->listUserMappings((int)$r['id'])]);}
    public function updateUserMapping(array $p):JsonResponse{$r=$this->connection((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;$in=$this->body();$crm=!empty($in['crm_user_public_id'])?(string)$in['crm_user_public_id']:null;if($crm!==null&&$this->repo->activeUserPublicId($crm)===null)return JsonResponse::error('USER_NOT_FOUND','Active CRM user not found',404);if(!$this->repo->updateUserMapping((int)$r['id'],(int)($p['mapping_id']??0),$crm))return JsonResponse::error('MAPPING_NOT_FOUND','Mapping not found',404);return JsonResponse::success('CLICKUP_USER_MAPPING_UPDATED','Mapping updated');}
    public function listJobs():JsonResponse{return JsonResponse::success('CLICKUP_JOBS_LIST','OK',['items'=>$this->repo->listJobs($this->actorId(),$this->can('module.clickup-migration.manage'))]);}
    public function createJob(): JsonResponse
    {
        $in = $this->body();
        $r = $this->connection((string)($in['connection_public_id'] ?? ''));
        if ($r instanceof JsonResponse) return $r;
        $mode = (string)($in['mode'] ?? 'import');
        if (!in_array($mode, ['import', 'sync', 'dry_run'], true)) return JsonResponse::error('VALIDATION_ERROR', 'mode must be import, sync or dry_run', 422);
        if ($mode !== 'dry_run' && empty($this->actor()['is_root'])) return JsonResponse::error('ROOT_REQUIRED', 'Only a root user may run a ClickUp import or sync.', 403);
        $scope = (array)($in['source_scope'] ?? []);
        $scope['team_ids'] = array_values(array_filter(array_map('strval', (array)($in['team_ids'] ?? $scope['team_ids'] ?? []))));
        $scope['space_ids'] = array_values(array_filter(array_map('strval', (array)($in['space_ids'] ?? $in['project_ids'] ?? $scope['space_ids'] ?? []))));
        $scope['max_tasks'] = max(0, (int)($in['max_tasks'] ?? $scope['max_tasks'] ?? 0));
        $options = (array)($in['target_options'] ?? $in['options'] ?? []);
        $job = $this->repo->createJob(['connection_id' => (int)$r['id'], 'mode' => $mode, 'source_scope' => $scope, 'target_options' => $options, 'created_by_user_id' => $this->actorId()]);
        return JsonResponse::success('CLICKUP_JOB_CREATED', 'Job created', ['job' => $job], 201);
    }
    public function getJob(array $p):JsonResponse{$r=$this->job((string)($p['public_id']??''));return$r instanceof JsonResponse?$r:JsonResponse::success('CLICKUP_JOB','OK',['job'=>$r]);}
    public function startJob(array $p):JsonResponse{return$this->changeJob((string)$p['public_id'],'queued','CLICKUP_JOB_QUEUED');}public function pauseJob(array $p):JsonResponse{return$this->changeJob((string)$p['public_id'],'pausing','CLICKUP_JOB_PAUSING');}public function resumeJob(array $p):JsonResponse{return$this->changeJob((string)$p['public_id'],'queued','CLICKUP_JOB_RESUMED');}public function cancelJob(array $p):JsonResponse{$r=$this->job((string)($p['public_id']??''));if($r instanceof JsonResponse)return$r;if((string)($r['status']??'')==='draft'){if(!$this->repo->requestStatus((string)$p['public_id'],'cancelled'))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success('CLICKUP_JOB_CANCELLED','Job cancelled');}return$this->changeJob((string)$p['public_id'],'cancelling','CLICKUP_JOB_CANCELLING');}
    public function retryFailed(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);if($r instanceof JsonResponse)return$r;if(!in_array((string)($r['status']??''),['completed_with_warnings','failed','cancelled'],true))return JsonResponse::error('INVALID_JOB_STATUS','Only a finished job can be retried',409);$n=$this->repo->retryJob((string)$p['public_id']);if($n===null)return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success('CLICKUP_JOB_RETRY_QUEUED','Failed items queued for retry',['reset_items'=>$n]);}
    public function rollbackJob(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);if($r instanceof JsonResponse)return$r;if(empty($this->actor()['is_root']))return JsonResponse::error('ROOT_REQUIRED','Only a root user may roll back imported CRM data',403);try{$this->buildImportService()->rollback((string)$p['public_id'],$this->actor());return JsonResponse::success('CLICKUP_JOB_ROLLED_BACK','Job targets rolled back');}catch(\Throwable){return JsonResponse::error('CLICKUP_ROLLBACK_FAILED','Rollback failed; inspect migration log',409);}}
    public function listJobItems(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);if($r instanceof JsonResponse)return$r;$in=$this->container->get('request')->allInput();return JsonResponse::success('CLICKUP_JOB_ITEMS','OK',['items'=>$this->repo->items((int)$r['id'],!empty($in['status'])?(string)$in['status']:null,max(1,min(1000,(int)($in['limit']??200))))]);}
    public function listJobLogs(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('CLICKUP_JOB_LOGS','OK',['items'=>$this->repo->logs((int)$r['id'])]);}
    public function getReport(array $p):JsonResponse{$r=$this->job((string)$p['public_id']);return$r instanceof JsonResponse?$r:JsonResponse::success('CLICKUP_JOB_REPORT','OK',['report'=>$this->repo->report((string)$p['public_id'])]);}
    private function changeJob(string $id,string $status,string $code):JsonResponse{$r=$this->job($id);if($r instanceof JsonResponse)return$r;$current=(string)($r['status']??'');$ok=match($status){'queued'=>in_array($current,['draft','paused','failed','cancelled'],true),'pausing'=>in_array($current,['queued','running'],true),'cancelling'=>in_array($current,['draft','queued','running','paused','pausing'],true),default=>false};if(!$ok)return JsonResponse::error('INVALID_JOB_STATUS','Job cannot be changed from status: '.$current,409);if(!$this->repo->requestStatus($id,$status))return JsonResponse::error('INVALID_JOB_STATUS','Job changed concurrently',409);return JsonResponse::success($code,'Job state updated');}
    private function buildImportService():ClickUpImportService{$c=new ClickUpClient($this->repo);return new ClickUpImportService($this->repo,$c,new \Module\Crm\ClickUpMigration\Service\ClickUpCrawler($c,$this->repo),new ClickUpTargetWriter($this->container,$this->repo,$c));}
    private function publicConnection(array $c):array{unset($c['access_token_encrypted'],$c['refresh_token_encrypted'],$c['client_id_encrypted'],$c['client_secret_encrypted']);return$c;}
}
