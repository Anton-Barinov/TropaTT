<?php
declare(strict_types=1);

namespace Module\Crm\GoogleCalendar\Service;

use Api\System\Library\Http\Request;
use Module\Crm\GoogleCalendar\Repository\GoogleCalendarRepository;
use RuntimeException;

final class GoogleCalendarClient
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const API_ROOT = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private readonly GoogleCalendarRepository $repository,
        private readonly ?Request $request = null,
    ) {}

    /**
     * @param array{client_id:string,client_secret:string}|null $credentials
     *        Decrypted per-user OAuth credentials; when null (or incomplete)
     *        the global GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET env vars are
     *        used as a fallback so existing installations keep working.
     */
    public function configured(?array $credentials = null): bool
    {
        $credentials = $this->credentials($credentials);
        if ($credentials['client_id'] === '' || $credentials['client_secret'] === '') {
            return false;
        }
        try {
            $this->redirectUri();
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function authorizeUrl(string $state, ?string $redirectUri = null, ?array $credentials = null): string
    {
        $credentials = $this->credentials($credentials);
        if ($credentials['client_id'] === '' || $credentials['client_secret'] === '') {
            throw new RuntimeException('GOOGLE_OAUTH_NOT_CONFIGURED');
        }
        $query = http_build_query([
            'client_id' => $credentials['client_id'],
            'redirect_uri' => $this->redirectUri($redirectUri),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'scope' => implode(' ', [
                'openid',
                'email',
                'https://www.googleapis.com/auth/calendar.readonly',
                'https://www.googleapis.com/auth/calendar.events',
            ]),
            'state' => $state,
        ]);
        return self::AUTH_URL . '?' . $query;
    }

    /** @return array{access_token:string,refresh_token?:string,expires_in:int,scope?:string} */
    public function exchangeCode(string $code, ?string $redirectUri = null, ?array $credentials = null): array
    {
        return $this->tokenRequest(['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$this->redirectUri($redirectUri)], $credentials);
    }

    /** @return array{access_token:string,expires_in:int,scope?:string} */
    public function refresh(string $refreshToken, ?array $credentials = null): array
    {
        return $this->tokenRequest(['grant_type'=>'refresh_token','refresh_token'=>$refreshToken], $credentials);
    }

    /**
     * @param array{client_id:string,client_secret:string}|null $credentials
     * @return array{client_id:string,client_secret:string}
     */
    private function credentials(?array $credentials = null): array
    {
        if (is_array($credentials)) {
            $id = trim((string)($credentials['client_id'] ?? ''));
            $secret = trim((string)($credentials['client_secret'] ?? ''));
            if ($id !== '' && $secret !== '') {
                return ['client_id' => $id, 'client_secret' => $secret];
            }
        }
        return [
            'client_id' => trim((string)getenv('GOOGLE_CLIENT_ID')),
            'client_secret' => trim((string)getenv('GOOGLE_CLIENT_SECRET')),
        ];
    }

    public function revoke(string $token): void
    {
        $this->requestRaw(self::TOKEN_URL . '/revoke?token=' . rawurlencode($token), 'POST', [], '', false);
    }

    public function accountEmail(string $accessToken): ?string
    {
        [$status, $payload] = $this->requestRaw(
            'https://openidconnect.googleapis.com/v1/userinfo',
            'GET',
            ['Authorization: Bearer ' . $accessToken, 'Accept: application/json'],
            '',
            true
        );
        if ($status === 401) {
            throw new RuntimeException('GOOGLE_ACCESS_TOKEN_EXPIRED', 401);
        }
        if ($status < 200 || $status >= 300 || !is_array($payload)) {
            throw new RuntimeException('GOOGLE_ACCOUNT_IDENTITY_UNAVAILABLE', $status);
        }
        $email = trim((string)($payload['email'] ?? ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
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
    public function updateEvent(string $accessToken,string $calendarId,string $eventId,array $event,?string $etag=null):array
    {
        $headers = $etag !== null && $etag !== '' ? ['If-Match: '.$etag] : [];
        return $this->api($accessToken,'PATCH','/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId),[], $event, $headers);
    }

    /** @return array<string,mixed>|null */
    public function findEventByCrmId(string $accessToken,string $calendarId,string $crmEventPublicId):?array
    {
        $page = $this->api($accessToken, 'GET', '/calendars/'.rawurlencode($calendarId).'/events', [
            'privateExtendedProperty' => 'tropatt_event_public_id='.$crmEventPublicId,
            'showDeleted' => 'false',
            'singleEvents' => 'false',
            'maxResults' => 10,
        ]);
        foreach ((array)($page['items'] ?? []) as $event) {
            if (is_array($event) && (string)($event['extendedProperties']['private']['tropatt_event_public_id'] ?? '') === $crmEventPublicId) {
                return $event;
            }
        }
        return null;
    }

    public function deleteEvent(string $accessToken,string $calendarId,string $eventId):void
    {
        $this->api($accessToken,'DELETE','/calendars/'.rawurlencode($calendarId).'/events/'.rawurlencode($eventId));
    }

    public function redirectUri(?string $explicit = null): string
    {
        $override = trim($explicit !== null ? $explicit : (string)getenv('GOOGLE_REDIRECT_URI'));
        if ($override !== '') {
            $parts = parse_url($override);
            if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https' || empty($parts['host'])) {
                throw new RuntimeException('GOOGLE_REDIRECT_URI_INVALID');
            }
            return $override;
        }
        return $this->installApiBase() . '?route=/_module/crm.google-calendar/oauth/callback';
    }

    /**
     * Public HTTPS webhook address that Google Calendar push notifications
     * are delivered to. Returns '' when the installation URL cannot be
     * resolved from configuration or the current request (e.g. inside the
     * cron worker without CRM_PUBLIC_URL set) — in that case the periodic
     * sync remains the fallback.
     */
    public function watchAddress(): string
    {
        try {
            return $this->installApiBase() . '?route=/_module/crm.google-calendar/webhook';
        } catch (RuntimeException) {
            return '';
        }
    }

    /**
     * Resolve the installation API entry point (https://host + SCRIPT_NAME)
     * from CRM_PUBLIC_URL, or from the current request when available.
     */
    private function installApiBase(): string
    {
        $publicBase = trim((string)getenv('CRM_PUBLIC_URL'));
        if ($publicBase !== '') {
            $baseParts = parse_url($publicBase);
            if (!is_array($baseParts) || strtolower((string)($baseParts['scheme'] ?? '')) !== 'https' || empty($baseParts['host']) || !empty($baseParts['query']) || !empty($baseParts['fragment'])) {
                throw new RuntimeException('CRM_PUBLIC_URL_INVALID');
            }
            return rtrim($publicBase, '/') . '/api/index.php';
        }

        if ($this->request === null) {
            throw new RuntimeException('GOOGLE_INSTALL_URL_UNRESOLVED');
        }
        $server = $this->request->server;
        $https = strtolower((string)($server['HTTPS'] ?? ''));
        $forwardedProto = strtolower((string)($server['HTTP_X_FORWARDED_PROTO'] ?? ''));
        $trustedProxyConfig = trim((string)getenv('CRM_TRUSTED_PROXIES'));
        $scheme = ($https !== '' && $https !== 'off') || ($forwardedProto === 'https' && $trustedProxyConfig !== '') ? 'https' : 'http';
        if ($scheme !== 'https') {
            throw new RuntimeException('GOOGLE_INSTALL_URL_HTTPS_REQUIRED');
        }
        $host = trim((string)($server['HTTP_HOST'] ?? $server['SERVER_NAME'] ?? ''));
        if ($host === '' || preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) !== 1) {
            throw new RuntimeException('GOOGLE_INSTALL_URL_HOST_INVALID');
        }
        $script = (string)($server['SCRIPT_NAME'] ?? '/api/index.php');
        if ($script === '' || !str_ends_with($script, '.php')) {
            $script = '/api/index.php';
        }
        $script = '/' . ltrim(strtok($script, '?') ?: '/api/index.php', '/');
        return 'https://' . $host . $script;
    }

    /** @return array<string,mixed> */
    public function watch(string $accessToken, string $calendarId, string $channelId, string $address, string $token, int $expiration): array
    {
        return $this->api($accessToken, 'POST', '/calendars/'.rawurlencode($calendarId).'/watch', [], [
            'id' => $channelId,
            'type' => 'web_hook',
            'address' => $address,
            'token' => $token,
            'expiration' => $expiration,
        ]);
    }

    public function stopWatch(string $accessToken, string $channelId, string $resourceId): void
    {
        $this->api($accessToken, 'POST', '/channels/stop', [], ['id' => $channelId, 'resourceId' => $resourceId]);
    }

    /** @return array<string,mixed> */
    private function tokenRequest(array $fields, ?array $credentials = null): array
    {
        $credentials = $this->credentials($credentials);
        $fields['client_id'] = $credentials['client_id'];
        $fields['client_secret'] = $credentials['client_secret'];
        [$status,$payload]=$this->requestRaw(self::TOKEN_URL,'POST',['Content-Type: application/x-www-form-urlencoded'],http_build_query($fields),true);
        if($status<200||$status>=300||!is_array($payload)||empty($payload['access_token'])){if(is_array($payload)&&($payload['error']??'')==='invalid_grant')throw new RuntimeException('GOOGLE_REFRESH_REVOKED');throw new RuntimeException('GOOGLE_TOKEN_EXCHANGE_FAILED');}
        return $payload;
    }

    /** @return array<string,mixed> */
    private function api(string $accessToken,string $method,string $path,array $query=[],?array $body=null,array $extraHeaders=[]):array
    {
        $url=self::API_ROOT.$path.($query!==[]?'?'.http_build_query($query):'');$headers=['Authorization: Bearer '.$accessToken,'Accept: application/json'];
        if($body!==null)$headers[]='Content-Type: application/json';
        foreach($extraHeaders as $extraHeader)$headers[]=(string)$extraHeader;
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
            $responseHeaders = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => max(5, min(60, (int)(getenv('GOOGLE_TIMEOUT_SECONDS') ?: 30))),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $header) use (&$responseHeaders): int {
                    $parts = explode(':', $header, 2);
                    if (count($parts) === 2) {
                        $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }
                    return strlen($header);
                },
            ]);
            if ($body !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            $raw = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            $payload = is_string($raw) ? (json_decode($raw, true) ?: []) : [];
            $retryable = $error !== '' || in_array($status, [429, 500, 502, 503, 504], true)
                || ($status === 403 && in_array((string)($payload['error']['errors'][0]['reason'] ?? ''), ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'], true));
            if (!$retry || !$retryable || $attempt >= $max - 1) {
                if ($error !== '' && $status === 0) throw new RuntimeException('GOOGLE_NETWORK_ERROR');
                return [$status, $payload];
            }
            $retryAfter = $this->retryAfterSeconds($responseHeaders['retry-after'] ?? null);
            $sleep = $retryAfter > 0 ? $retryAfter : min(32, 2 ** $attempt) + (random_int(0, 1000) / 1000);
            usleep((int)($sleep * 1000000));
            $attempt++;
        } while (true);
    }

    private function retryAfterSeconds(?string $value): int
    {
        if ($value === null || trim($value) === '') return 0;
        if (ctype_digit(trim($value))) return max(0, min(120, (int)trim($value)));
        $timestamp = strtotime($value);
        return $timestamp === false ? 0 : max(0, min(120, $timestamp - time()));
    }
}
