<?php
declare(strict_types=1);

namespace Module\Crm\Bitrix24Migration\Service;

use Module\Crm\Bitrix24Migration\Repository\Bitrix24MigrationRepository;
use RuntimeException;

final class Bitrix24Client
{
    private const MAX_COLLECTION_ITEMS = 10000;
    private ?array $connection = null;
    private ?int $connectionId = null;
    private string $authType = '';
    private string $portalUrl = '';
    private string $webhookUrl = '';
    private string $accessToken = '';
    private string $refreshToken = '';
    private string $clientId = '';
    private string $clientSecret = '';

    public function __construct(
        private readonly Bitrix24MigrationRepository $repo,
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
    }

    public function setConnection(array $connection): void
    {
        $this->connection = $connection;
        $this->connectionId = (int)($connection['id'] ?? 0) ?: null;
        $this->authType = (string)($connection['auth_type'] ?? 'webhook');
        $this->portalUrl = rtrim((string)($connection['portal_url'] ?? ''), '/');
        $this->webhookUrl = (string)(EncryptionService::decrypt((string)($connection['webhook_url_encrypted'] ?? '')) ?? '');
        $this->accessToken = (string)(EncryptionService::decrypt((string)($connection['access_token_encrypted'] ?? '')) ?? '');
        $this->refreshToken = (string)(EncryptionService::decrypt((string)($connection['refresh_token_encrypted'] ?? '')) ?? '');
        $this->clientId = (string)(EncryptionService::decrypt((string)($connection['client_id_encrypted'] ?? '')) ?? '');
        $this->clientSecret = (string)(EncryptionService::decrypt((string)($connection['client_secret_encrypted'] ?? '')) ?? '');
        if ($this->authType === 'webhook' && $this->webhookUrl === '') throw new RuntimeException('BITRIX24_WEBHOOK_MISSING');
        if ($this->authType === 'oauth' && ($this->accessToken === '' || $this->portalUrl === '')) throw new RuntimeException('BITRIX24_OAUTH_CREDENTIALS_MISSING');
    }

    /** @return array<string,mixed> */
    public function test(): array
    {
        return $this->call('user.current', []);
    }

    /** @return array<int,array<string,mixed>> */
    public function users(): array { return $this->collection('user.get', ['sort' => 'ID', 'order' => 'ASC']); }
    /** @return array<int,array<string,mixed>> */
    public function departments(): array { return $this->collection('department.get', ['sort' => 'SORT', 'order' => 'ASC']); }
    /** @return array<int,array<string,mixed>> */
    public function companies(): array { return $this->collection('crm.company.list', ['select' => ['ID','TITLE','PHONE','EMAIL','WEB','ADDRESS','COMMENTS','DATE_CREATE','DATE_MODIFY','ASSIGNED_BY_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function contacts(): array { return $this->collection('crm.contact.list', ['select' => ['ID','NAME','SECOND_NAME','LAST_NAME','PHONE','EMAIL','POST','COMMENTS','COMPANY_ID','DATE_CREATE','DATE_MODIFY','ASSIGNED_BY_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function leads(): array { return $this->collection('crm.lead.list', ['select' => ['ID','TITLE','NAME','LAST_NAME','PHONE','EMAIL','STATUS_ID','STATUS_SEMANTIC_ID','COMMENTS','DATE_CREATE','DATE_MODIFY','ASSIGNED_BY_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function deals(): array { return $this->collection('crm.deal.list', ['select' => ['ID','TITLE','STAGE_ID','CATEGORY_ID','OPPORTUNITY','CURRENCY_ID','PROBABILITY','COMMENTS','DATE_CREATE','DATE_MODIFY','ASSIGNED_BY_ID','CONTACT_ID','COMPANY_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function invoices(): array { return $this->collection('crm.invoice.list', ['select' => ['ID','ORDER_TOPIC','STATUS_ID','PRICE','CURRENCY','DATE_INSERT','DATE_UPDATE','RESPONSIBLE_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function quotes(): array { return $this->collection('crm.quote.list', ['select' => ['ID','TITLE','STATUS_ID','OPPORTUNITY','CURRENCY_ID','DATE_CREATE','DATE_MODIFY','ASSIGNED_BY_ID','CONTACT_ID','COMPANY_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function products(): array { return $this->collection('crm.product.list', ['select' => ['ID','NAME','DESCRIPTION','PRICE','CURRENCY_ID','ACTIVE','DATE_CREATE','DATE_UPDATE','SECTION_ID','UF_*'], 'order' => ['ID' => 'ASC']]); }
    /** @return array<int,array<string,mixed>> */
    public function projects(bool $includeArchived = false): array { return $this->collection('sonet_group.get', ['ORDER' => ['ID' => 'ASC'], 'FILTER' => $includeArchived ? [] : ['ACTIVE' => 'Y']]); }
    /** @return array<int,array<string,mixed>> */
    public function tasks(): array { return $this->collection('tasks.task.list', ['order' => ['ID' => 'ASC'], 'select' => ['ID','TITLE','DESCRIPTION','STATUS','PRIORITY','DEADLINE','START_DATE_PLAN','END_DATE_PLAN','RESPONSIBLE_ID','CREATED_BY','GROUP_ID','PARENT_ID','TAGS','DATE_CREATE','CHANGED_DATE','CLOSED_DATE','UF_*']]); }
    /** @return array<int,array<string,mixed>> */
    public function activities(): array { return $this->collection('crm.activity.list', ['order' => ['ID' => 'ASC'], 'filter' => ['CHECK_PERMISSIONS' => 'N']]); }
    /** @param array<int,int> $ownerIds @return array<int,array<string,mixed>> */
    public function events(?string $from = null, ?string $to = null, array $ownerIds = [0]): array
    {
        $items=[];$seen=[];foreach(array_values(array_unique(array_map('intval',$ownerIds))) as $ownerId){$params=['type'=>$ownerId===0?'company_calendar':'user','ownerId'=>$ownerId];if($from!==null&&trim($from)!=='')$params['from']=$from;if($to!==null&&trim($to)!=='')$params['to']=$to;foreach($this->collection('calendar.event.get',$params,false) as $item){$id=(string)($item['ID']??$item['id']??hash('sha256',(string)json_encode($item)));if(isset($seen[$id]))continue;$seen[$id]=true;$items[]=$item;}}return$items;
    }
    /** @return array<int,array<string,mixed>> */
    public function files(): array { return $this->collection('disk.file.list', ['order' => ['ID' => 'ASC'], 'filter' => ['DELETED_TYPE' => 0]]); }

    /** @return array<string,mixed> */
    public function fileDetails(string $sourceId): array
    {
        $result = $this->call('disk.file.get', ['id' => $sourceId]);
        $value = $result['result'] ?? $result;
        return is_array($value) ? $value : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function comments(string $entityType, string $entityId): array
    {
        return $this->collection('crm.timeline.comment.list', ['filter' => ['ENTITY_ID' => $entityId, 'ENTITY_TYPE' => $entityType], 'select' => ['ID','ENTITY_ID','ENTITY_TYPE','AUTHOR_ID','COMMENT','CREATED','FILES']]);
    }

    /** @return array<int,array<string,mixed>> */
    public function taskComments(string $taskId): array
    {
        return $this->collection('task.commentitem.getlist', ['TASKID' => $taskId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function productRows(string $ownerType, string $ownerId): array
    {
        $legacy = match (strtoupper($ownerType)) {
            'D' => 'crm.deal.productrows.get',
            'I' => 'crm.invoice.productrows.get',
            'Q' => 'crm.quote.productrows.get',
            default => '',
        };
        if ($legacy !== '') {
            try {
                return $this->collection($legacy, ['id' => $ownerId]);
            } catch (RuntimeException) {
                // Universal product-row API is available on newer portals.
            }
        }
        return $this->collection('crm.item.productrow.list', ['filter' => ['=ownerType' => $ownerType, '=ownerId' => $ownerId], 'select' => ['*']]);
    }

    /** @return array<int,array<string,mixed>> */
    public function batch(array $calls): array
    {
        $calls = array_slice($calls, 0, 50);
        if ($calls === []) return [];
        return $this->call('batch', ['halt' => 0, 'cmd' => $calls]);
    }

    /** @return array<string,mixed> */
    public function downloadFile(string $url, int $maxBytes, int $redirects = 0): array
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $portalHost = strtolower((string)(parse_url($this->portalUrl, PHP_URL_HOST) ?? ''));
        $resolvedIps = $this->publicIps($host);
        if (($parts['scheme'] ?? '') !== 'https' || (($redirects === 0) && !$this->allowedDownloadHost($host, $portalHost)) || $resolvedIps === []) throw new RuntimeException('BITRIX24_FILE_URL_BLOCKED');
        $tmp = tempnam(sys_get_temp_dir(), 'b24-');
        if ($tmp === false) throw new RuntimeException('BITRIX24_FILE_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('BITRIX24_FILE_TEMP_FAILED'); }
        $written = 0; $overflow = false; $headers = [];
        $ch = curl_init($url);
        $requestHeaders = ['Accept: */*', 'User-Agent: TropaTT-Bitrix24-Migration/1.0'];
        if ($this->authType === 'oauth' && $this->accessToken !== '') $requestHeaders[] = 'Authorization: Bearer ' . $this->accessToken;
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($fp, &$written, &$overflow, $maxBytes): int { $length = strlen($chunk); if ($written + $length > $maxBytes) { $overflow = true; return 0; } $written += $length; return fwrite($fp, $chunk) ?: 0; },
            CURLOPT_TIMEOUT => max(10, $this->timeout * 2), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_RESOLVE => array_map(static fn(string $ip): string => $host . ':443:' . $ip, $resolvedIps), CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int { $length = strlen($line); if (str_contains($line, ':')) { [$name,$value]=array_pad(explode(':',$line,2),2,'');$headers[strtolower(trim($name))]=trim($value); } return $length; },
        ]);
        $ok = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
        if ($ok !== false && in_array($code, [301, 302, 303, 307, 308], true) && !empty($headers['location'])) {
            @unlink($tmp);
            if ($redirects >= 3) throw new RuntimeException('BITRIX24_FILE_REDIRECT_LIMIT');
            $location = trim((string)$headers['location']);
            if (str_starts_with($location, '//')) $location = 'https:' . $location;
            elseif (str_starts_with($location, '/')) $location = $this->portalUrl . $location;
            $next = parse_url($location);
            $nextHost = strtolower((string)($next['host'] ?? ''));
            if (($next['scheme'] ?? '') !== 'https' || !$this->allowedDownloadHost($nextHost, $portalHost) || $this->publicIps($nextHost) === []) throw new RuntimeException('BITRIX24_FILE_REDIRECT_BLOCKED');
            return $this->downloadFile($location, $maxBytes, $redirects + 1);
        }
        if ($ok === false || $code < 200 || $code >= 300 || $overflow) { @unlink($tmp); throw new RuntimeException($overflow ? 'BITRIX24_FILE_TOO_LARGE' : 'BITRIX24_FILE_DOWNLOAD_FAILED'); }
        return ['path' => $tmp, 'size' => $written, 'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream')];
    }

    /** @return array<int,array<string,mixed>> */
    private function collection(string $method, array $params, bool $paginate = true): array
    {
        if (!$paginate) {
            return $this->extractItems($this->call($method, $params));
        }

        $items = []; $start = 0; $seen = [];
        do {
            $page = $this->call($method, $params + ['start' => $start]);
            foreach ($this->extractItems($page) as $item) {
                $id = (string)($item['ID'] ?? $item['id'] ?? $item['ID'] ?? hash('sha256', json_encode($item)));
                if (isset($seen[$id])) continue;
                $seen[$id] = true; $items[] = $item;
                if (count($items) > self::MAX_COLLECTION_ITEMS) throw new RuntimeException('BITRIX24_COLLECTION_LIMIT_EXCEEDED');
            }
            $next = $page['next'] ?? null;
            if ($next === null && isset($page['total']) && count($items) < (int)$page['total']) $next = $start + 50;
            $start = $next === null ? 0 : (int)$next;
        } while ($next !== null && $start > 0);
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function extractItems(array $result): array
    {
        $value = $result['result'] ?? $result;
        if (isset($value['tasks']) && is_array($value['tasks'])) $value = $value['tasks'];
        elseif (isset($value['items']) && is_array($value['items'])) $value = $value['items'];
        elseif (isset($value['users']) && is_array($value['users'])) $value = $value['users'];
        elseif (isset($value['groups']) && is_array($value['groups'])) $value = $value['groups'];
        elseif (isset($value['events']) && is_array($value['events'])) $value = $value['events'];
        return is_array($value) && array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [];
    }

    /** @return array<string,mixed> */
    private function call(string $method, array $params, int $attempt = 0): array
    {
        if ($this->connection === null) throw new RuntimeException('BITRIX24_CONNECTION_NOT_SET');
        $this->waitRateLimit();
        $url = $this->endpoint($method, $params);
        $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $this->timeout), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: TropaTT-Bitrix24-Migration/1.0'], CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int { $length=strlen($line);if(str_contains($line,':')){[$name,$value]=array_pad(explode(':',$line,2),2,'');$headers[strtolower(trim($name))]=trim($value);}return$length;}]);
        $endpointParts = parse_url($url);
        $endpointHost = strtolower((string)($endpointParts['host'] ?? ''));
        $endpointIps = $this->publicIps($endpointHost);
        if (($endpointParts['scheme'] ?? '') !== 'https' || $endpointIps === []) {
            curl_close($ch);
            throw new RuntimeException('BITRIX24_ENDPOINT_BLOCKED');
        }
        curl_setopt($ch, CURLOPT_RESOLVE, array_map(static fn(string $ip): string => $endpointHost . ':443:' . $ip, $endpointIps));
        $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        $this->repo->recordRequest((int)$this->connectionId, $code, $headers);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        $apiError = is_array($decoded) ? (string)($decoded['error'] ?? '') : '';
        if (($code === 401 || in_array($apiError, ['expired_token','INVALID_TOKEN'], true)) && $this->authType === 'oauth' && $attempt === 0) {
            $this->refresh(); return $this->call($method, $params, $attempt + 1);
        }
        if ($code === 429 || $code === 503 || $apiError === 'QUERY_LIMIT_EXCEEDED' || $apiError === 'OPERATION_TIME_LIMIT') {
            $delay = min(60, 2 ** min(6, $attempt + 1));
            if (isset($headers['retry-after'])) $delay = max(1, (int)$headers['retry-after']);
            $this->repo->recordRetryAfter((int)$this->connectionId, $delay);
            if ($attempt < max(1, $this->maxRetries) - 1) { sleep($delay); return $this->call($method, $params, $attempt + 1); }
            throw new RuntimeException('BITRIX24_RATE_LIMITED', $code ?: 429);
        }
        if ($body === false || $code < 200 || $code >= 300) throw new RuntimeException('BITRIX24_HTTP_' . $code . ($error !== '' ? ': ' . $error : ''), $code);
        if (!is_array($decoded)) throw new RuntimeException('BITRIX24_INVALID_RESPONSE');
        if (!empty($decoded['error'])) throw new RuntimeException('BITRIX24_API_' . strtoupper((string)$decoded['error']), $code ?: 400);
        return $decoded;
    }

    private function endpoint(string $method, array $params): string
    {
        $encoded = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        if ($this->authType === 'webhook') return rtrim($this->webhookUrl, '/') . '/' . rawurlencode($method) . '.json' . ($encoded !== '' ? '?' . $encoded : '');
        $query = ['auth' => $this->accessToken] + $params;
        return $this->portalUrl . '/rest/' . rawurlencode($method) . '.json?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function refresh(): void
    {
        if ($this->refreshToken === '' || $this->clientId === '' || $this->clientSecret === '' || $this->connectionId === null) throw new RuntimeException('BITRIX24_OAUTH_REFRESH_UNAVAILABLE');
        $ch = curl_init('https://oauth.bitrix.info/oauth/token/');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['grant_type'=>'refresh_token','client_id'=>$this->clientId,'client_secret'=>$this->clientSecret,'refresh_token'=>$this->refreshToken]), CURLOPT_TIMEOUT => $this->timeout, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Accept: application/json']]);
        $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($code < 200 || $code >= 300 || !is_array($data) || empty($data['access_token'])) throw new RuntimeException('BITRIX24_OAUTH_REFRESH_FAILED');
        $this->accessToken = (string)$data['access_token']; $this->refreshToken = (string)($data['refresh_token'] ?? $this->refreshToken);
        $this->repo->updateOAuthTokens($this->connectionId, EncryptionService::encrypt($this->accessToken), EncryptionService::encrypt($this->refreshToken));
    }

    private function waitRateLimit(): void
    {
        if ($this->connectionId === null) return;
        $state = $this->repo->rateState($this->connectionId); $until = strtotime((string)($state['retry_after_until'] ?? ''));
        if ($until > time()) sleep(min(60, $until - time() + 1));
    }

    private function allowedDownloadHost(string $host, string $portalHost): bool
    {
        if ($host === '' || $host === $portalHost) return $host !== '';
        // Bitrix24 may redirect disk downloads to a Bitrix-controlled CDN.
        // Match only known Bitrix24 public domains; do not accept arbitrary
        // suffixes such as evil.bitrix24.attacker.example.
        foreach (['bitrix24.ru','bitrix24.com','bitrix24.eu','bitrix24.de','bitrix24.fr','bitrix24.es','bitrix24.it','bitrix24.pl','bitrix24.com.br','bitrix24.mx','bitrix24.co.uk','bitrix24.us','bitrix24.ca','bitrix24.kz','bitrix24.by','bitrix24.ua','bitrix24.site'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) return true;
        }
        return false;
    }

    /** @return array<int,string> */
    private function publicIps(string $host): array
    {
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) return [];
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        $public = [];
        foreach ($ips as $ip) if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) $public[] = $ip;
        return array_values(array_unique($public));
    }

    private function privateHost(string $host): bool
    {
        return $this->publicIps($host) === [];
    }
}
