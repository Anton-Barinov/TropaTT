<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Service;

use Module\Crm\GoogleCalendar\Repository\GoogleCalendarRepository;
use RuntimeException;

final class GoogleCalendarClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_ROOT = 'https://www.googleapis.com/calendar/v3';

    public function __construct(private readonly GoogleCalendarRepository $repository) {}

    public function configured(): bool
    {
        return trim((string)getenv('GOOGLE_CLIENT_ID')) !== ''
            && trim((string)getenv('GOOGLE_CLIENT_SECRET')) !== ''
            && str_starts_with(trim((string)getenv('GOOGLE_REDIRECT_URI')), 'https://');
    }

    public function authorizeUrl(string $state): string
    {
        if (!$this->configured()) throw new RuntimeException('GOOGLE_OAUTH_NOT_CONFIGURED');
        $query = http_build_query([
            'client_id' => trim((string)getenv('GOOGLE_CLIENT_ID')),
            'redirect_uri' => trim((string)getenv('GOOGLE_REDIRECT_URI')),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'scope' => implode(' ', [
                'https://www.googleapis.com/auth/calendar.readonly',
                'https://www.googleapis.com/auth/calendar.events',
            ]),
            'state' => $state,
        ]);
        return self::AUTH_URL . '?' . $query;
    }

    /** @return array{access_token:string,refresh_token?:string,expires_in:int,scope?:string} */
    public function exchangeCode(string $code): array
    {
        return $this->tokenRequest(['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>trim((string)getenv('GOOGLE_REDIRECT_URI'))]);
    }

    /** @return array{access_token:string,expires_in:int,scope?:string} */
    public function refresh(string $refreshToken): array
    {
        return $this->tokenRequest(['grant_type'=>'refresh_token','refresh_token'=>$refreshToken]);
    }

    public function revoke(string $token): void
    {
        $this->requestRaw(self::TOKEN_URL . '/revoke?token=' . rawurlencode($token), 'POST', [], '', false);
    }

    /** @return array<string,mixed> */
    public function currentUser(string $accessToken): array
    {
        return $this->api($accessToken, 'GET', '/users/me/calendarList', ['maxResults'=>1]);
    }

    /** @return array<int,array<string,mixed>> */
    public function calendars(string $accessToken): array
    {
        $items=[];$pageToken=null;
        do {
            $query=['maxResults'=>250]; if($pageToken!==null)$query['pageToken']=$pageToken;
            $page=$this->api($accessToken,'GET','/users/me/calendarList',$query);
            foreach((array)($page['items']??[]) as $item){if(is_array($item)&&!empty($item['id']))$items[]=$item;}
            $pageToken=isset($page['nextPageToken'])?(string)$page['nextPageToken']:null;
        } while($pageToken!==null&&$pageToken!=='');
        return $items;
    }

    /** @return array<string,mixed> */
    public function eventsPage(string $accessToken,string $calendarId,array $query):array
    {
        return $this->api($accessToken,'GET','/calendars/'.rawurlencode($calendarId).'/events',$query);
    }

    /** @return array<string,mixed> */
    public function createEvent(string $accessToken,string $calendarId,array $event):array
    {
        return $this->api($accessToken,'POST','/calendars/'.rawurlencode($calendarId).'/events',[], $event);
    }

    /** @return array<string,mixed> */
    public function updateEvent(string $accessToken,string $calendarId,string $eventId,array $event):array
    {
        return $this->api($accessToken,'PATCH','/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId),[], $event);
    }

    public function deleteEvent(string $accessToken,string $calendarId,string $eventId):void
    {
        $this->api($accessToken,'DELETE','/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId));
    }

    /** @return array<string,mixed> */
    private function tokenRequest(array $fields): array
    {
        $fields['client_id']=trim((string)getenv('GOOGLE_CLIENT_ID'));$fields['client_secret']=(string)getenv('GOOGLE_CLIENT_SECRET');
        [$status,$payload]=$this->requestRaw(self::TOKEN_URL,'POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query($fields),true);
        if($status<200||$status>=300||!is_array($payload)||empty($payload['access_token'])){if(is_array($payload)&&($payload['error']??'')==='invalid_grant')throw new RuntimeException('GOOGLE_REFRESH_REVOKED');throw new RuntimeException('GOOGLE_TOKEN_EXCHANGE_FAILED');}
        return $payload;
    }

    /** @return array<string,mixed> */
    private function api(string $accessToken,string $method,string $path,array $query=[],?array $body=null):array
    {
        $url=self::API_ROOT.$path.($query!==[]?'?'.http_build_query($query):'');$headers=['Authorization: Bearer '.$accessToken,'Accept: application/json'];
        if($body!==null)$headers[]='Content-Type: application/json';
        [$status,$payload]=$this->requestRaw($url,$method,$headers,$body===null?'':(json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?:''),true);
        if($status===410)throw new RuntimeException('GOOGLE_SYNC_TOKEN_EXPIRED',410);
        if($status===401)throw new RuntimeException('GOOGLE_ACCESS_TOKEN_EXPIRED',401);
        if($status<200||$status>=300)throw new RuntimeException('GOOGLE_API_ERROR_'.(string)$status,$status);
        return is_array($payload)?$payload:[];
    }

    /** @return array{0:int,1:array<string,mixed>} */
    private function requestRaw(string $url,string $method,array $headers,string $body,bool $retry):array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('CURL_REQUIRED');
        $max=max(1,min(5,(int)(getenv('GOOGLE_MAX_RETRIES')?:5)));$attempt=0;
        do {
            $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_TIMEOUT=>max(5,min(60,(int)(getenv('GOOGLE_TIMEOUT_SECONDS')?:30))),CURLOPT_CONNECTTIMEOUT=>10]);
            if($body!=='')curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
            $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
            $payload=is_string($raw)?(json_decode($raw,true)?:[]):[];
            $retryable=$error!==''||in_array($status,[429,500,502,503,504],true)||($status===403&&in_array((string)($payload['error']['errors'][0]['reason']??''),['rateLimitExceeded','userRateLimitExceeded','quotaExceeded'],true));
            if(!$retry||!$retryable||$attempt>=$max-1){if($error!==''&&$status===0)throw new RuntimeException('GOOGLE_NETWORK_ERROR');return[$status,$payload];}
            $retryAfter=(int)($payload['error']['retryAfter']??0);$sleep=$retryAfter>0?$retryAfter:min(32,2**$attempt)+(random_int(0,1000)/1000);usleep((int)($sleep*1000000));$attempt++;
        } while(true);
    }
}
