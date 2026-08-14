<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Controller;

use Api\System\Library\Container;
use Api\System\Library\Http\JsonResponse;
use Module\Crm\GoogleCalendar\Repository\GoogleCalendarRepository;
use Module\Crm\GoogleCalendar\Service\GoogleCalendarClient;
use Module\Crm\GoogleCalendar\Service\GoogleCalendarSyncService;
use RuntimeException;

final class GoogleCalendarController
{
    private GoogleCalendarRepository $repository;
    private GoogleCalendarClient $client;
    private GoogleCalendarSyncService $sync;

    public function __construct(private readonly Container $container)
    {
        $this->repository = $container->get('module.google_calendar.repository');
        $this->client = $container->get('module.google_calendar.client');
        $this->sync = $container->get('module.google_calendar.sync');
    }

    public function oauthStart(): JsonResponse
    {
        if (!$this->client->configured()) return JsonResponse::error('GOOGLE_OAUTH_NOT_CONFIGURED', 'Google OAuth credentials are not configured on this CRM instance', 503);
        if (session_status() === PHP_SESSION_NONE) @session_start();
        $state = bin2hex(random_bytes(32));
        $_SESSION['google_calendar_oauth_state'] = hash_hmac('sha256', $state, (string)$this->actorId());
        $_SESSION['google_calendar_oauth_expires'] = time() + 600;
        $_SESSION['google_calendar_oauth_user_id'] = $this->actorId();
        return JsonResponse::success('GOOGLE_OAUTH_URL', 'Open the Google authorization URL', ['authorization_url' => $this->client->authorizeUrl($state)]);
    }

    public function oauthCallback(): JsonResponse
    {
        $input = $this->container->get('request')->allInput(); $code=trim((string)($input['code']??''));$state=trim((string)($input['state']??''));
        if($code===''||$state===''||!$this->validState($state))return JsonResponse::error('GOOGLE_OAUTH_STATE_INVALID','OAuth state is invalid or expired',422);
        $connection = null;
        try{$tokens=$this->client->exchangeCode($code);$connection=$this->sync->connectUser($this->actorId(),$tokens);$calendars=$this->client->calendars((string)$tokens['access_token']);$email=null;foreach($calendars as $calendar){$this->repository->upsertSource((int)$connection['id'],$calendar);if(!empty($calendar['primary']))$email=(string)($calendar['id']??'');}if($email!==null)$this->repository->updateConnection((int)$connection['id'],['google_account_email'=>$email]);return JsonResponse::success('GOOGLE_CONNECTED','Google Calendar connected',['connection'=>$this->publicConnection($this->repository->connectionForUser($this->actorId())??$connection)]);}catch(\Throwable){if(is_array($connection)&&!empty($connection['id']))$this->repository->updateConnection((int)$connection['id'],['status'=>'sync_warning','last_error'=>'Google calendars could not be loaded after authorization']);return JsonResponse::error('GOOGLE_OAUTH_EXCHANGE_FAILED','Google authorization could not be completed',422);}
    }

    public function connections(): JsonResponse
    {
        $items=[];foreach($this->repository->listConnectionsForUser($this->actorId()) as $connection){$connection['calendars']=array_map([$this,'publicSource'],$this->repository->allSources((int)$connection['id']));$items[]=$this->publicConnection($connection);}return JsonResponse::success('GOOGLE_CONNECTIONS','OK',['connections'=>$items]);
    }

    public function test(array $params): JsonResponse
    {
        $connection=$this->ownedConnection((string)($params['public_id']??''));if($connection===null)return$this->notFound();try{$result=$this->sync->test((int)$connection['id'],$this->actorId());return JsonResponse::success('GOOGLE_CONNECTION_TEST_OK','Google Calendar connection is working',['result'=>$result]);}catch(RuntimeException $e){return JsonResponse::error($e->getMessage()==='GOOGLE_REFRESH_REVOKED'?'GOOGLE_REAUTH_REQUIRED':'GOOGLE_CONNECTION_TEST_FAILED','Google Calendar connection test failed',422);}
    }

    public function sync(array $params): JsonResponse
    {
        $connection=$this->ownedConnection((string)($params['public_id']??''));if($connection===null)return$this->notFound();try{$result=$this->sync->sync((int)$connection['id'],$this->actorId());$partial=($result['warnings']??[])!==[];return JsonResponse::success($partial?'GOOGLE_SYNC_WARNING':'GOOGLE_SYNC_COMPLETE',$partial?'Google Calendar synchronized with warnings':'Google Calendar synchronized',['result'=>$result],$partial?207:200);}catch(RuntimeException $e){$code=$e->getMessage()==='GOOGLE_REFRESH_REVOKED'?'GOOGLE_REAUTH_REQUIRED':'GOOGLE_SYNC_FAILED';return JsonResponse::error($code,'Google Calendar synchronization failed',422);}
    }

    public function updateCalendar(array $params): JsonResponse
    {
        $input=$this->body();$direction=(string)($input['direction']??'google_to_crm');$source=$this->repository->sourceForUser((string)($params['public_id']??''),$this->actorId());$enabled=array_key_exists('is_enabled',$input)?(bool)$input['is_enabled']:(bool)($source['is_enabled']??true);if(!$this->updateDirectionByPublicId((string)($params['public_id']??''),$this->actorId(),$direction,$enabled))return JsonResponse::error('GOOGLE_CALENDAR_NOT_FOUND','Calendar not found or direction is invalid',404);return JsonResponse::success('GOOGLE_CALENDAR_UPDATED','Calendar settings updated');
    }

    public function disconnect(array $params): JsonResponse
    {
        $connection=$this->ownedConnection((string)($params['public_id']??''));if($connection===null)return$this->notFound();try{$this->sync->disconnect((int)$connection['id'],$this->actorId());return JsonResponse::success('GOOGLE_DISCONNECTED','Google Calendar disconnected');}catch(\Throwable){return JsonResponse::error('GOOGLE_DISCONNECT_FAILED','Google Calendar could not be disconnected',409);}
    }

    private function updateDirectionByPublicId(string $publicId,int $userId,string $direction,bool $enabled):bool
    {
        $source=$this->repository->sourceForUser($publicId,$userId);return$source?$this->sync->updateDirection((int)$source['id'],$userId,$direction,$enabled):false;
    }

    private function ownedConnection(string $publicId):?array
    {
        foreach($this->repository->listConnectionsForUser($this->actorId()) as $connection)if((string)$connection['public_id']===$publicId)return$this->repository->connectionById((int)$connection['id']);return null;
    }
    private function actor():array{$auth=$this->container->has('auth_user')?$this->container->get('auth_user'):[];return is_array($auth['user']??null)?$auth['user']:[];}
    private function actorId():int{return(int)($this->actor()['id']??0);}
    private function body():array{$request=$this->container->get('request');$decoded=json_decode((string)($request->rawBody??''),true);return is_array($decoded)?$decoded:((array)$request->allInput());}
    private function validState(string $state):bool{if(session_status()===PHP_SESSION_NONE)@session_start();$expires=(int)($_SESSION['google_calendar_oauth_expires']??0);$expected=(string)($_SESSION['google_calendar_oauth_state']??'');$user=(int)($_SESSION['google_calendar_oauth_user_id']??0);$ok=$user===$this->actorId()&&$expires>=time()&&$expected!==''&&hash_equals($expected,hash_hmac('sha256',$state,(string)$this->actorId()));if($ok)unset($_SESSION['google_calendar_oauth_state'],$_SESSION['google_calendar_oauth_expires'],$_SESSION['google_calendar_oauth_user_id']);return$ok;}
    private function publicSource(array $source): array
    {
        return [
            'public_id' => (string)($source['public_id'] ?? ''),
            'calendar_id' => (string)($source['calendar_id'] ?? ''),
            'summary' => $source['summary'] ?? null,
            'timezone' => $source['timezone'] ?? null,
            'direction' => (string)($source['direction'] ?? 'google_to_crm'),
            'is_enabled' => (bool)($source['is_enabled'] ?? false),
            'is_primary' => (bool)($source['is_primary'] ?? false),
            'last_sync_at' => $source['last_sync_at'] ?? null,
            'last_error' => $source['last_error'] ?? null,
        ];
    }
    private function publicConnection(array $connection):array{unset($connection['refresh_token_encrypted'],$connection['access_token_encrypted'],$connection['access_token_expires_at']);return$connection;}
    private function notFound():JsonResponse{return JsonResponse::error('GOOGLE_CONNECTION_NOT_FOUND','Google Calendar connection not found',404);}
}
