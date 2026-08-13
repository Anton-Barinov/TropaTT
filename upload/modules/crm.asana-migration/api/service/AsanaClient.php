<?php
declare(strict_types=1);

namespace Module\Crm\AsanaMigration\Service;

use Module\Crm\AsanaMigration\Repository\AsanaMigrationRepository;
use RuntimeException;

final class AsanaClient
{
    private const BASE_URL = 'https://app.asana.com/api/1.0';
    private const API_VERSION = '1.0';
    private const MAX_COLLECTION_ITEMS = 10000;

    public function __construct(
        private readonly AsanaMigrationRepository $repo,
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
    }

    private ?int $connectionId = null;

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    /** @return array<string,mixed> */
    public function me(string $token): array
    {
        return $this->getData($token, '/users/me', ['opt_fields' => 'gid,name,email,photo']);
    }

    /** @return array<int,array<string,mixed>> */
    public function workspaces(string $token): array
    {
        return $this->collection($token, '/workspaces', ['opt_fields' => 'gid,name,is_organization'], 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function users(string $token, string $workspaceGid): array
    {
        return $this->collection($token, '/workspaces/' . rawurlencode($workspaceGid) . '/users', ['opt_fields' => 'gid,name,email'], 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function projects(string $token, string $workspaceGid, bool $includeArchived = false): array
    {
        $query = ['opt_fields' => 'gid,name,notes,archived,public,owner,team,created_at,modified_at,permalink_url', 'archived' => $includeArchived ? 'true' : 'false'];
        return $this->collection($token, '/workspaces/' . rawurlencode($workspaceGid) . '/projects', $query, 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function sections(string $token, string $projectGid): array
    {
        return $this->collection($token, '/projects/' . rawurlencode($projectGid) . '/sections', ['opt_fields' => 'gid,name,project,created_at,modified_at'], 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function tasks(string $token, string $projectGid, bool $includeArchived = false): array
    {
        return $this->collection($token, '/projects/' . rawurlencode($projectGid) . '/tasks', ['opt_fields' => 'gid,name,notes,html_notes,completed,completed_at,created_at,modified_at,due_on,due_at,start_on,start_at,assignee,followers,parent,projects,memberships,tags,custom_fields,dependencies,dependents,permalink_url,resource_subtype,archived',
            'archived' => $includeArchived ? 'true' : 'false',
            ], 100);
    }

    /** @return array<string,mixed> */
    public function task(string $token, string $taskGid): array
    {
        return $this->getData($token, '/tasks/' . rawurlencode($taskGid), ['opt_fields' => 'gid,name,notes,html_notes,completed,completed_at,created_at,modified_at,due_on,due_at,start_on,start_at,assignee,followers,parent,projects,memberships,tags,custom_fields,dependencies,dependents,permalink_url,resource_subtype,archived']);
    }

    /** @return array<int,array<string,mixed>> */
    public function subtasks(string $token, string $taskGid): array
    {
        return $this->collection($token, '/tasks/' . rawurlencode($taskGid) . '/subtasks', ['opt_fields' => 'gid,name,notes,completed,completed_at,created_at,modified_at,due_on,due_at,start_on,start_at,assignee,followers,parent,projects,memberships,tags,custom_fields,dependencies,dependents,permalink_url,resource_subtype,archived'], 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function stories(string $token, string $taskGid): array
    {
        return $this->collection($token, '/tasks/' . rawurlencode($taskGid) . '/stories', ['opt_fields' => 'gid,resource_subtype,text,html_text,created_at,created_by,created_at'], 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function attachments(string $token, string $taskGid): array
    {
        return $this->collection($token, '/tasks/' . rawurlencode($taskGid) . '/attachments', ['opt_fields' => 'gid,name,resource_subtype,download_url,view_url,permanent_url,created_at,size,mime_type'], 100);
    }

    /** @return array<int,array<string,mixed>> */
    public function tags(string $token, string $workspaceGid): array
    {
        return $this->collection($token, '/workspaces/' . rawurlencode($workspaceGid) . '/tags', ['opt_fields' => 'gid,name,color,created_at'], 100);
    }

    /** Stream a paginated collection so large workspaces are not accumulated in PHP memory. */
    public function eachUsers(string $token, string $workspaceGid, callable $consumer): int
    {
        return $this->streamCollection($token, '/workspaces/' . rawurlencode($workspaceGid) . '/users', ['opt_fields' => 'gid,name,email'], 100, $consumer);
    }

    public function eachTags(string $token, string $workspaceGid, callable $consumer): int
    {
        return $this->streamCollection($token, '/workspaces/' . rawurlencode($workspaceGid) . '/tags', ['opt_fields' => 'gid,name,color,created_at'], 100, $consumer);
    }

    public function eachProjects(string $token, string $workspaceGid, bool $includeArchived, callable $consumer): int
    {
        return $this->streamCollection($token, '/workspaces/' . rawurlencode($workspaceGid) . '/projects', ['opt_fields' => 'gid,name,notes,archived,public,owner,team,created_at,modified_at,permalink_url', 'archived' => $includeArchived ? 'true' : 'false'], 100, $consumer);
    }

    public function eachSections(string $token, string $projectGid, callable $consumer): int
    {
        return $this->streamCollection($token, '/projects/' . rawurlencode($projectGid) . '/sections', ['opt_fields' => 'gid,name,project,created_at,modified_at'], 100, $consumer);
    }

    public function eachTasks(string $token, string $projectGid, bool $includeArchived, callable $consumer): int
    {
        return $this->streamCollection($token, '/projects/' . rawurlencode($projectGid) . '/tasks', ['opt_fields' => 'gid,name,notes,html_notes,completed,completed_at,created_at,modified_at,due_on,due_at,start_on,start_at,assignee,followers,parent,projects,memberships,tags,custom_fields,dependencies,dependents,permalink_url,resource_subtype,archived', 'archived' => $includeArchived ? 'true' : 'false'], 100, $consumer);
    }

    public function eachStories(string $token, string $taskGid, callable $consumer): int
    {
        return $this->streamCollection($token, '/tasks/' . rawurlencode($taskGid) . '/stories', ['opt_fields' => 'gid,resource_subtype,text,html_text,created_at,created_by'], 100, $consumer);
    }

    public function eachAttachments(string $token, string $taskGid, callable $consumer): int
    {
        return $this->streamCollection($token, '/tasks/' . rawurlencode($taskGid) . '/attachments', ['opt_fields' => 'gid,name,resource_subtype,download_url,view_url,permanent_url,created_at,size,mime_type'], 100, $consumer);
    }

    public function eachSubtasks(string $token, string $taskGid, callable $consumer): int
    {
        return $this->streamCollection($token, '/tasks/' . rawurlencode($taskGid) . '/subtasks', ['opt_fields' => 'gid,name,notes,completed,completed_at,created_at,modified_at,due_on,due_at,start_on,start_at,assignee,followers,parent,projects,memberships,tags,custom_fields,dependencies,dependents,permalink_url,resource_subtype,archived'], 100, $consumer);
    }

    /** @return array<string,mixed> */
    public function downloadAttachment(string $token, string $url, int $maxBytes): array
    {
        return $this->downloadAttachmentUrl($token, $url, $maxBytes, 0);
    }

    /** Download an attachment while validating every redirect destination. */
    private function downloadAttachmentUrl(string $token, string $url, int $maxBytes, int $redirects): array
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || !$this->allowedAttachmentHost($host) || (!$this->allowedHost($host) && !$this->hasSignedStorageQuery((string)($parts['query'] ?? ''))) || $this->privateHost($host)) throw new RuntimeException('ASANA_SSRF_BLOCKED');
        if ($redirects > 3) throw new RuntimeException('ASANA_ATTACHMENT_REDIRECT_LIMIT');
        $tmp = tempnam(sys_get_temp_dir(), 'asana-');
        if ($tmp === false) throw new RuntimeException('ASANA_ATTACHMENT_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('ASANA_ATTACHMENT_TEMP_FAILED'); }
        $written = 0; $overflow = false; $headers = [];
        $ch = curl_init($url);
        $requestHeaders = ['Accept: */*', 'User-Agent: TropaTT-Asana-Migration/1.0'];
        if ($this->allowedHost($host)) $requestHeaders[] = 'Authorization: Bearer ' . $token;
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($fp, &$written, &$overflow, $maxBytes): int { $length = strlen($chunk); if ($written + $length > $maxBytes) { $overflow = true; return 0; } $written += $length; return fwrite($fp, $chunk) ?: 0; },
            CURLOPT_TIMEOUT => max(10, $this->timeout * 2), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int { $length = strlen($line); if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); } return $length; },
        ]);
        $ok = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); fclose($fp);
        if ($code >= 300 && $code < 400 && !empty($headers['location'])) {
            @unlink($tmp);
            $location = (string)$headers['location'];
            if (str_starts_with($location, '//')) { $location = (string)($parts['scheme'] ?? 'https') . ':' . $location; }
            elseif (!preg_match('/^https?:\\/\\//i', $location)) {
                $scheme = (string)($parts['scheme'] ?? 'https'); $origin = $scheme . '://' . $host; $path = (string)($parts['path'] ?? '/');
                if (str_starts_with($location, '?')) $location = $origin . $path . $location;
                elseif (str_starts_with($location, '/')) $location = $origin . $location;
                else { $directory = substr($path, 0, (int)strrpos($path, '/') + 1); $location = $origin . ($directory !== '' ? $directory : '/') . $location; }
            }
            return $this->downloadAttachmentUrl($token, $location, $maxBytes, $redirects + 1);
        }
        if ($ok === false || $code < 200 || $code >= 300 || $overflow || $written > $maxBytes) { @unlink($tmp); throw new RuntimeException($overflow || $written > $maxBytes ? 'ASANA_ATTACHMENT_TOO_LARGE' : 'ASANA_ATTACHMENT_DOWNLOAD_FAILED'); }
        return ['path' => $tmp, 'size' => $written, 'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream')];
    }

    /** @return array<string,mixed> */
    private function getData(string $token, string $path, array $query = []): array
    {
        $result = $this->request($token, $path, $query);
        return is_array($result['data'] ?? null) ? $result['data'] : [];
    }

    /** @return array<int,array<string,mixed>> */
    private function collection(string $token, string $path, array $query = [], int $limit = 100): array
    {
        $items = [];
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        $query['limit'] = min(100, max(1, $limit));
        $offset = null;
        do {
            $pageQuery = $query;
            if ($offset !== null) $pageQuery['offset'] = $offset;
            $response = $this->request($token, $path, $pageQuery);
            foreach ((array)($response['data'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $items[] = $item;
                if (count($items) > self::MAX_COLLECTION_ITEMS) throw new RuntimeException('ASANA_COLLECTION_LIMIT_EXCEEDED');
            }
            $offset = is_array($response['next_page'] ?? null) ? (string)($response['next_page']['offset'] ?? '') : null;
            if ($offset === '') $offset = null;
        } while ($offset !== null);
        return $items;
    }

    /** Stream one page at a time; a false callback result stops discovery cleanly. */
    private function streamCollection(string $token, string $path, array $query, int $limit, callable $consumer): int
    {
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        $query['limit'] = min(100, max(1, $limit));
        $offset = null;
        $count = 0;
        do {
            $pageQuery = $query;
            if ($offset !== null) $pageQuery['offset'] = $offset;
            $response = $this->request($token, $path, $pageQuery);
            foreach ((array)($response['data'] ?? []) as $item) {
                if (!is_array($item)) continue;
                $count++;
                if ($count > self::MAX_COLLECTION_ITEMS) throw new RuntimeException('ASANA_COLLECTION_LIMIT_EXCEEDED');
                if ($consumer($item) === false) return $count - 1;
            }
            $offset = is_array($response['next_page'] ?? null) ? (string)($response['next_page']['offset'] ?? '') : null;
            if ($offset === '') $offset = null;
        } while ($offset !== null);
        return $count;
    }

    /** @return array<string,mixed> */
    private function request(string $token, string $path, array $query = []): array
    {
        if ($this->connectionId !== null) $this->waitRateLimit($this->connectionId);
        $url = self::BASE_URL . $path . ($query !== [] ? '?' . http_build_query($query) : '');
        for ($attempt = 1; $attempt <= max(1, $this->maxRetries); $attempt++) {
            $headers = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(5, $this->timeout),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Asana-Version: ' . self::API_VERSION, 'User-Agent: TropaTT-Asana-Migration/1.0'],
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); }
                    return $length;
                },
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($this->connectionId !== null) $this->repo->recordRequest($this->connectionId, $code, $headers);
            $decoded = is_string($body) ? json_decode($body, true) : null;
            if ($code === 429) {
                $delay = isset($headers['retry-after']) ? max(1, (int)$headers['retry-after']) : min(60, 2 ** $attempt);
                if ($this->connectionId !== null) $this->repo->recordRetryAfter($this->connectionId, $delay);
                if ($attempt < $this->maxRetries) { sleep($delay); continue; }
                throw new RuntimeException('ASANA_RATE_LIMITED', 429);
            }
            if ($code === 401) throw new RuntimeException('ASANA_AUTH_FAILED', 401);
            if ($code === 403) throw new RuntimeException('ASANA_FORBIDDEN', 403);
            if ($code === 404) throw new RuntimeException('ASANA_NOT_FOUND', 404);
            if ($body === false || $code < 200 || $code >= 300) {
                if ($attempt < $this->maxRetries && ($code === 0 || $code >= 500)) { sleep(min(30, 2 ** $attempt)); continue; }
                throw new RuntimeException('ASANA_HTTP_' . $code . ($error !== '' ? ': ' . $error : ''), $code);
            }
            if (!is_array($decoded)) throw new RuntimeException('ASANA_INVALID_RESPONSE');
            return $decoded;
        }
        throw new RuntimeException('ASANA_REQUEST_FAILED');
    }

    private function waitRateLimit(int $connectionId): void
    {
        $state = $this->repo->rateState($connectionId);
        $until = strtotime((string)($state['retry_after_until'] ?? ''));
        if ($until > time()) sleep($until - time() + 1);
    }

    private function allowedHost(string $host): bool
    {
        return $host === 'app.asana.com' || str_ends_with($host, '.asana.com');
    }

    private function allowedAttachmentHost(string $host): bool
    {
        if ($this->allowedHost($host)) return true;
        // Asana may return a short-lived pre-signed S3 URL directly or redirect
        // the app URL to a regional S3 endpoint. Restrict this to S3-shaped
        // hostnames; never allow arbitrary *.amazonaws.com destinations.
        return $this->storageHost($host);
    }

    private function storageHost(string $host): bool
    {
        return $host === 's3.amazonaws.com'
            || (bool)preg_match('/^(?:[^.]+\.)?s3(?:[.-][a-z0-9-]+)?\.amazonaws\.com$/', $host);
    }

    private function hasSignedStorageQuery(string $query): bool
    {
        parse_str($query, $params);
        $normalized = [];
        foreach ($params as $key => $value) $normalized[strtolower((string)$key)] = trim((string)$value);
        $signature = $normalized['x-amz-signature'] ?? $normalized['signature'] ?? '';
        $credential = $normalized['x-amz-credential'] ?? '';
        $expires = $normalized['x-amz-expires'] ?? $normalized['expires'] ?? '';
        if ($signature === '' || !ctype_digit($expires) || (int)$expires < 1 || (int)$expires > 604800) return false;
        // Accept legacy Asana/S3 signatures and AWS Signature V4, but never treat
        // an arbitrary public S3 URL with dummy query parameters as trusted.
        if (isset($normalized['x-amz-signature'])) return $credential !== '' && strlen($signature) >= 16 && isset($normalized['x-amz-algorithm'], $normalized['x-amz-date']);
        return strlen($signature) >= 16;
    }

    private function privateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local')) return true;
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : [];
        if ($ips === [] && function_exists('dns_get_record')) {
            foreach ((array)@dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
                $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                if ($ip !== '') $ips[] = $ip;
            }
        }
        if ($ips === []) $ips = gethostbynamel($host) ?: [];
        if ($ips === []) return true;
        foreach ($ips as $ip) if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
        return false;
    }
}
