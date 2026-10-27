<?php
declare(strict_types=1);

namespace Module\Crm\ClickUpMigration\Service;

use Module\Crm\ClickUpMigration\Repository\ClickUpMigrationRepository;
use RuntimeException;

final class ClickUpClient
{
    private const API = 'https://api.clickup.com/api/v2';
    private const OAUTH_TOKEN = self::API . '/oauth/token';
    private ?int $connectionId = null;
    private string $authType = 'pat';

    public function __construct(
        private readonly ClickUpMigrationRepository $repo,
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
    }

    public function setConnectionId(?int $id): void { $this->connectionId = $id; }
    public function setAuthType(string $authType): void { $this->authType = $authType === 'oauth2' ? 'oauth2' : 'pat'; }

    /** @return array<string,mixed> */
    public function test(string $token): array
    {
        $teams = $this->request($token, 'GET', '/team');
        $items = $this->items($teams, ['teams']);
        return ['ok' => true, 'teams_count' => count($items), 'teams' => array_map(static fn(array $team): array => [
            'id' => (string)($team['id'] ?? ''),
            'name' => (string)($team['name'] ?? ''),
        ], array_slice($items, 0, 20))];
    }

    /** @return array<string,mixed> */
    public function oauthExchange(string $clientId, string $clientSecret, string $code): array
    {
        $ch = curl_init(self::OAUTH_TOKEN);
        if ($ch === false) throw new RuntimeException('CLICKUP_OAUTH_EXCHANGE_FAILED');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['client_id' => trim($clientId), 'client_secret' => $clientSecret, 'code' => trim($code)], JSON_UNESCAPED_SLASHES),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => max(5, $this->timeout), CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($data) || trim((string)($data['access_token'] ?? '')) === '') throw new RuntimeException('CLICKUP_OAUTH_EXCHANGE_FAILED', $status);
        return $data;
    }

    public function oauthAuthorizeUrl(string $clientId, string $state, ?string $redirectUri = null): string
    {
        $query = ['client_id' => trim($clientId), 'state' => $state];
        if ($redirectUri !== null && trim($redirectUri) !== '') $query['redirect_uri'] = trim($redirectUri);
        return 'https://app.clickup.com/api?' . http_build_query($query);
    }

    /** @return array<int,array<string,mixed>> */
    public function teams(string $token): array { return $this->items($this->request($token, 'GET', '/team'), ['teams']); }
    /** @return array<int,array<string,mixed>> */
    public function spaces(string $token, string $teamId, bool $archived): array { return $this->items($this->request($token, 'GET', '/team/' . rawurlencode($teamId) . '/space', ['archived' => $archived ? 'true' : 'false']), ['spaces']); }
    /** @return array<int,array<string,mixed>> */
    public function folders(string $token, string $spaceId, bool $archived): array { return $this->items($this->request($token, 'GET', '/space/' . rawurlencode($spaceId) . '/folder', ['archived' => $archived ? 'true' : 'false']), ['folders']); }
    /** @return array<int,array<string,mixed>> */
    public function folderlessLists(string $token, string $spaceId, bool $archived): array { return $this->items($this->request($token, 'GET', '/space/' . rawurlencode($spaceId) . '/list', ['archived' => $archived ? 'true' : 'false']), ['lists']); }
    /** @return array<int,array<string,mixed>> */
    public function listsInFolder(string $token, string $folderId, bool $archived): array { return $this->items($this->request($token, 'GET', '/folder/' . rawurlencode($folderId) . '/list', ['archived' => $archived ? 'true' : 'false']), ['lists']); }

    /** @return array<int,array<string,mixed>> */
    public function tasks(string $token, string $listId, bool $archived, bool $includeClosed, int $page = 0, ?string $updatedSince = null, ?string $completedSince = null, ?string $completedUntil = null): array
    {
        $query = ['archived' => $archived ? 'true' : 'false', 'include_closed' => $includeClosed ? 'true' : 'false', 'subtasks' => 'true', 'include_markdown_description' => 'true', 'page' => max(0, $page)];
        if ($updatedSince !== null && trim($updatedSince) !== '') { $ts = strtotime($updatedSince); if ($ts !== false) $query['date_updated_gt'] = $ts * 1000; }
        if ($completedSince !== null && trim($completedSince) !== '') { $ts = strtotime($completedSince . ' 00:00:00 UTC'); if ($ts !== false) $query['date_done_gt'] = $ts * 1000; }
        if ($completedUntil !== null && trim($completedUntil) !== '') { $ts = strtotime($completedUntil . ' 23:59:59 UTC'); if ($ts !== false) $query['date_done_lt'] = $ts * 1000; }
        return $this->items($this->request($token, 'GET', '/list/' . rawurlencode($listId) . '/task', $query), ['tasks']);
    }

    /** @return array<string,mixed> */
    public function task(string $token, string $taskId): array
    { return $this->request($token, 'GET', '/task/' . rawurlencode($taskId), ['include_subtasks' => 'true', 'include_markdown_description' => 'true']); }

    /** @return array<int,array<string,mixed>> */
    public function comments(string $token, string $taskId, callable $consumer): int
    {
        $count = 0; $start = null; $startId = null; $seen = [];
        for ($page = 0; $page < 10000; $page++) {
            $query = [];
            if ($start !== null) { $query['start'] = $start; $query['start_id'] = $startId; }
            $batch = $this->items($this->request($token, 'GET', '/task/' . rawurlencode($taskId) . '/comment', $query), ['comments']);
            if ($batch === []) break;
            foreach ($batch as $comment) {
                $id = (string)($comment['id'] ?? '');
                if ($id !== '' && isset($seen[$id])) continue;
                if ($id !== '') $seen[$id] = true;
                ++$count;
                if ($consumer($comment) === false) return $count;
            }
            if (count($batch) < 25) break;
            $last = end($batch);
            $start = (string)($last['date'] ?? $last['created_at'] ?? '');
            $startId = (string)($last['id'] ?? '');
            if ($start === '' || $startId === '') break;
        }
        return $count;
    }

    /** @return array<int,array<string,mixed>> */
    public function fields(string $token, string $listId): array { return $this->items($this->request($token, 'GET', '/list/' . rawurlencode($listId) . '/field'), ['fields']); }
    /** @return array<int,array<string,mixed>> */
    public function timeEntries(string $token, string $teamId, int $startDate, int $endDate, ?string $taskId = null, ?string $assigneeId = null): array
    {
        $query = ['start_date' => $startDate, 'end_date' => $endDate];
        if ($taskId !== null && trim($taskId) !== '') $query['task_id'] = trim($taskId);
        if ($assigneeId !== null && trim($assigneeId) !== '') $query['assignee'] = trim($assigneeId);
        return $this->items($this->request($token, 'GET', '/team/' . rawurlencode($teamId) . '/time_entries', $query), ['data', 'time_entries']);
    }
    /** @return array<int,array<string,mixed>> */    public function taskTimeEntries(string $token, string $teamId, string $taskId, int $startDate, int $endDate, array $assigneeIds = []): array
    {
        if ($endDate < $startDate) return [];
        $entries = [];
        $seen = [];
        $window = 90 * 86400 * 1000;
        $assignees = array_values(array_unique(array_filter(array_map('strval', $assigneeIds), static fn(string $id): bool => $id !== '')));
        // ClickUp returns the current user's entries when assignee is omitted.
        // Query each workspace member explicitly so an admin migration does not
        // silently omit everyone else's time.
        if ($assignees === []) $assignees = [null];
        foreach ($assignees as $assigneeId) {
            for ($from = $startDate; $from <= $endDate; $from = $to + 1) {
                $to = min($endDate, $from + $window - 1);
                foreach ($this->timeEntries($token, $teamId, $from, $to, $taskId, $assigneeId) as $entry) {
                    $id = (string)($entry['id'] ?? '');
                    if ($id !== '' && isset($seen[$id])) continue;
                    if ($id !== '') $seen[$id] = true;
                    $entries[] = $entry;
                }
                if ($to >= $endDate) break;
            }
        }
        return $entries;
    }

    /** @return array<int,array<string,mixed>> */
    public function goals(string $token, string $teamId): array { return $this->items($this->request($token, 'GET', '/team/' . rawurlencode($teamId) . '/goal'), ['goals']); }

    /** Download only official ClickUp/CloudFront HTTPS attachments with SSRF and size guards. */
    public function downloadAttachment(string $token, string $url, int $maxBytes, int $redirects = 0): array
    {
        if ($redirects > 3) throw new RuntimeException('CLICKUP_ATTACHMENT_REDIRECT_LIMIT');
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = $parts['port'] ?? null;
        if ($scheme !== 'https' || $host === '' || ($port !== null && (int)$port !== 443) || !$this->allowedAttachmentHost($host)) throw new RuntimeException('CLICKUP_ATTACHMENT_URL_NOT_ALLOWED');
        $address = $this->publicAddress($host);
        if ($address === null) throw new RuntimeException('CLICKUP_ATTACHMENT_SSRF_BLOCKED');
        $tmp = tempnam(sys_get_temp_dir(), 'clickup-');
        if ($tmp === false) throw new RuntimeException('CLICKUP_ATTACHMENT_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('CLICKUP_ATTACHMENT_TEMP_FAILED'); }
        $written = 0; $headers = [];
        $ch = curl_init($url);
        if ($ch === false) { fclose($fp); @unlink($tmp); throw new RuntimeException('CLICKUP_ATTACHMENT_DOWNLOAD_FAILED'); }
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($fp, &$written, $maxBytes): int { $length = strlen($chunk); if ($written + $length > $maxBytes) return 0; $written += $length; return fwrite($fp, $chunk) ?: 0; },
            CURLOPT_TIMEOUT => 120, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RESOLVE => [$host . ':443:' . $address], CURLOPT_HTTPHEADER => ['Accept: */*', 'User-Agent: TropaTT-ClickUp-Migration/1.0', $this->authorizationHeader($token)],
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int { $length=strlen($line); if(str_contains($line, ':')){[$key,$value]=array_pad(explode(':',$line,2),2,'');$headers[strtolower(trim($key))]=trim($value);} return $length; },
        ]);
        $ok = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
        if ($ok !== false && in_array($status, [301,302,303,307,308], true) && !empty($headers['location'])) { @unlink($tmp); $next=trim((string)$headers['location']); if(str_starts_with($next,'//'))$next='https:'.$next; elseif(str_starts_with($next,'/'))$next='https://'.$host.$next; return $this->downloadAttachment($token,$next,$maxBytes,$redirects+1); }
        if ($ok === false || $status < 200 || $status >= 300 || $written > $maxBytes) { @unlink($tmp); throw new RuntimeException($written > $maxBytes ? 'CLICKUP_ATTACHMENT_TOO_LARGE' : 'CLICKUP_ATTACHMENT_DOWNLOAD_FAILED'); }
        return ['path'=>$tmp,'size'=>$written,'mime_type'=>(string)($headers['content-type']??'application/octet-stream')];
    }

    private function allowedAttachmentHost(string $host): bool
    {
        return in_array($host, ['attachments.clickup.com', 'files.clickup.com', 'app.clickup.com'], true);
    }

    private function publicAddress(string $host): ?string
    {
        $addresses=[];
        if(filter_var($host,FILTER_VALIDATE_IP)!==false)$addresses[]=$host;
        if($addresses===[] && function_exists('dns_get_record')) foreach((array)@dns_get_record($host,DNS_A) as $record){$ip=trim((string)($record['ip']??''));if($ip!=='')$addresses[]=$ip;}
        if($addresses===[])$addresses=gethostbynamel($host)?:[];
        foreach($addresses as $ip)if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)!==false)return $ip;
        return null;
    }

    private function authorizationHeader(string $token): string
    {
        return 'Authorization: ' . ($this->authType === 'oauth2' ? 'Bearer ' : '') . trim($token);
    }

    /** @return array<string,mixed> */
    private function request(string $token, string $method, string $path, array $query = []): array
    {
        $attempts = max(1, $this->maxRetries);
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $headers = [];
            $url = self::API . $path . ($query !== [] ? '?' . http_build_query($query) : '');
            $ch = curl_init($url);
            if ($ch === false) throw new RuntimeException('CLICKUP_REQUEST_FAILED');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $this->timeout), CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => [$this->authorizationHeader($token), 'Accept: application/json', 'User-Agent: TropaTT-ClickUp-Migration/1.0'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) { [$key, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($key))] = trim($value); }
                    return $length;
                },
            ]);
            $body = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
            $retryAfter = isset($headers['retry-after']) ? max(1, (int)$headers['retry-after']) : null;
            if ($this->connectionId !== null) $this->repo->recordRequest($this->connectionId, $status, $status === 429 ? $retryAfter : null);
            $data = is_string($body) ? json_decode($body, true) : null;
            if ($status === 429) { if ($attempt < $attempts) { sleep(min(60, $retryAfter ?? (2 ** $attempt))); continue; } throw new RuntimeException('CLICKUP_RATE_LIMITED', 429); }
            if ($status === 401) throw new RuntimeException('CLICKUP_AUTH_FAILED', 401);
            if ($status === 403) throw new RuntimeException('CLICKUP_FORBIDDEN', 403);
            if ($status === 404) throw new RuntimeException('CLICKUP_NOT_FOUND', 404);
            if ($body === false || $status < 200 || $status >= 300) {
                if ($attempt < $attempts && ($status === 0 || $status >= 500)) { sleep(min(30, 2 ** $attempt)); continue; }
                throw new RuntimeException('CLICKUP_HTTP_' . $status . ($error !== '' ? ': ' . $error : ''), $status);
            }
            if (!is_array($data)) throw new RuntimeException('CLICKUP_INVALID_RESPONSE');
            return $data;
        }
        throw new RuntimeException('CLICKUP_REQUEST_FAILED');
    }

    /** @param array<int,string> $keys @return array<int,array<string,mixed>> */
    private function items(array $response, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($response[$key]) && is_array($response[$key])) return array_values(array_filter($response[$key], 'is_array'));
        }
        return array_is_list($response) ? array_values(array_filter($response, 'is_array')) : [];
    }
}
