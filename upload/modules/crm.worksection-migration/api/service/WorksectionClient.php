<?php
declare(strict_types=1);

namespace Module\Crm\WorksectionMigration\Service;

use Module\Crm\WorksectionMigration\Repository\WorksectionMigrationRepository;
use RuntimeException;

final class WorksectionClient
{
    private const MAX_COLLECTION_ITEMS = 10000;
    private const MIN_REQUEST_INTERVAL_SECONDS = 1;
    private string $accountUrl;
    private string $authType = 'api_key';
    private ?int $connectionId = null;

    public function __construct(
        private readonly WorksectionMigrationRepository $repo,
        string $accountUrl = '',
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
        $this->accountUrl = self::normalizeAccountUrl($accountUrl);
    }

    public function setAccountUrl(string $accountUrl): void
    {
        $this->accountUrl = self::normalizeAccountUrl($accountUrl);
    }

    public function setAuthType(string $authType): void
    {
        $this->authType = $authType === 'oauth2' ? 'oauth2' : 'api_key';
    }

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    /** Connection test: OAuth2 uses me, admin keys use the projects collection. */
    public function me(string $token): array
    {
        if ($this->authType === 'oauth2') {
            $data = $this->action($token, 'me');
            return ['authenticated' => true, 'source' => $data];
        }
        $items = $this->collection($token, 'get_projects', ['filter' => 'active'], 'data');
        return ['authenticated' => true, 'projects_count_sample' => count($items)];
    }

    /** Worksection is a single-account service; the account URL is the workspace key. */
    public function workspaces(string $token, string $accountId = ''): array
    {
        $this->me($token);
        return [['gid' => $accountId !== '' ? $accountId : $this->workspaceGid(), 'name' => $this->accountUrl]];
    }

    public function workspaceGid(): string
    {
        $host = strtolower((string)parse_url($this->accountUrl, PHP_URL_HOST));
        return $host !== '' ? $host : preg_replace('/^https?:\/\//', '', $this->accountUrl);
    }

    /** @return array<int,array<string,mixed>> */
    public function users(string $token): array
    {
        return $this->optionalCollection($token, 'get_users', [], 'data');
    }

    /** @return array<int,array<string,mixed>> */
    public function projects(string $token, bool $includeArchived = false): array
    {
        $query = [];
        if (!$includeArchived) $query['filter'] = 'active';
        return $this->collection($token, 'get_projects', $query, 'data');
    }

    /** @return array<int,array<string,mixed>> */
    public function projectGroups(string $token): array
    {
        return $this->optionalCollection($token, 'get_project_groups', [], 'data');
    }

    /**
     * All tasks (including completed and subtasks) for a project. Worksection
     * list endpoints hide completed tasks; search_tasks with status=all returns
     * the flat task set including subtasks as first-class rows.
     *
     * @return array<int,array<string,mixed>>
     */
    public function tasks(string $token, string $projectId): array
    {
        $query = ['id_project' => $projectId, 'status' => 'all', 'extra' => 'text,files,comments,relations,subscribers'];
        return $this->optionalCollection($token, 'search_tasks', $query, 'data');
    }

    /** @return array<int,array<string,mixed>> */
    public function taskComments(string $token, string $taskId): array
    {
        return $this->optionalCollection($token, 'get_comments', ['id_task' => $taskId, 'extra' => 'files'], 'data');
    }

    /** @return array<int,array<string,mixed>> */
    public function taskTags(string $token, string $taskId): array
    {
        return $this->optionalCollection($token, 'get_task_tags', ['id_task' => $taskId], 'data');
    }

    /** @return array<int,array<string,mixed>> */
    public function taskFiles(string $token, string $taskId): array
    {
        return $this->optionalCollection($token, 'get_files', ['id_task' => $taskId], 'data');
    }

    /** @return array<int,array<string,mixed>> */
    public function projectCosts(string $token, string $projectId): array
    {
        return $this->optionalCollection($token, 'get_costs', ['id_project' => $projectId], 'data');
    }

    /**
     * Download a file by its id_file. The download action returns either a JSON
     * object with a url/page (possibly with binary content in the same body) or
     * direct binary. Auth is only forwarded to the configured account host.
     */
    public function downloadFile(string $token, string $fileId, int $maxBytes): array
    {
        $raw = $this->rawAction($token, 'download', ['id_file' => $fileId], $maxBytes);
        $body = $raw['body'];
        $decoded = json_decode($body, true);
        if (is_array($decoded)) {
            $url = trim((string)($decoded['url'] ?? $decoded['page'] ?? ''));
            if ($url !== '') {
                // The action response only carried the URL stub; discard the
                // stub temp file before following the validated download URL.
                if ($raw['path'] !== null && is_file((string)$raw['path'])) @unlink((string)$raw['path']);
                $target = $this->resolveDownloadUrl($url);
                $this->validateDownloadHost($target);
                $fetched = $this->httpGet($token, $target, $maxBytes);
                return ['path' => $fetched['path'], 'size' => $fetched['size'], 'mime_type' => (string)($fetched['content_type'] ?? 'application/octet-stream')];
            }
        }
        if ($raw['path'] === null || !is_file((string)$raw['path'])) {
            // Defensive: persist an inline binary body so callers always
            // receive a real temp file they can unlink in a finally block.
            $tmp = tempnam(sys_get_temp_dir(), 'worksection-');
            if ($tmp === false || file_put_contents($tmp, $body) !== strlen($body)) { if ($tmp !== false) @unlink($tmp); throw new RuntimeException('WORKSECTION_ATTACHMENT_TEMP_FAILED'); }
            return ['path' => $tmp, 'size' => strlen($body), 'mime_type' => (string)($raw['content_type'] ?? 'application/octet-stream')];
        }
        return ['path' => $raw['path'], 'size' => $raw['size'], 'mime_type' => (string)($raw['content_type'] ?? 'application/octet-stream')];
    }

    /** @return array<string,mixed> */
    private function action(string $token, string $actionName, array $params = []): array
    {
        $body = $this->rawAction($token, $actionName, $params, 0);
        $data = json_decode($body['body'], true);
        if (!is_array($data)) throw new RuntimeException('WORKSECTION_INVALID_RESPONSE');
        if (isset($data['status']) && (string)$data['status'] !== 'ok') {
            throw new RuntimeException('WORKSECTION_API_' . strtoupper((string)($data['error']['code'] ?? $data['status'] ?? 'ERROR')));
        }
        return $data;
    }

    /**
     * POST with API parameters in the query string (Worksection rejects
     * form-encoded bodies) and the response capped at maxBytes (0 = unlimited).
     *
     * @return array{body:string,path:?string,size:int,content_type:?string}
     */
    private function rawAction(string $token, string $actionName, array $params = [], int $maxBytes = 0): array
    {
        if ($this->connectionId !== null) $this->waitRateLimit($this->connectionId);
        $attempts = max(1, $this->maxRetries);
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $headers = [];
            $query = ['action' => $actionName];
            foreach ($params as $key => $value) if (trim((string)$value) !== '') $query[$key] = (string)$value;
            if ($this->authType === 'api_key') $query['hash'] = self::adminHash($actionName, $params, $token);
            $endpoint = rtrim($this->accountUrl, '/') . ($this->authType === 'oauth2' ? '/api/oauth2' : '/api/admin/v2/');
            $url = $endpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
            $this->assertPublicApiHost();
            $ch = curl_init($url);
            if ($ch === false) throw new RuntimeException('WORKSECTION_REQUEST_FAILED');
            $tmp = $maxBytes > 0 ? tempnam(sys_get_temp_dir(), 'worksection-') : null;
            $fp = $tmp !== false ? fopen($tmp, 'wb') : null;
            $written = 0; $tooLarge = false;
            $requestHeaders = ['Accept: */*', 'Content-Type: application/json', 'User-Agent: TropaTT-Worksection-Migration/1.0'];
            if ($this->authType === 'oauth2' && $token !== '') $requestHeaders[] = 'Authorization: Bearer ' . $token;
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => $fp === null,
                CURLOPT_POST => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => max(5, $this->timeout),
                CURLOPT_RESOLVE => [$this->curlResolveEntry($this->apiHost(), 443, $this->publicIpForHost($this->apiHost()))],
                CURLOPT_HTTPHEADER => $requestHeaders,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); }
                    return $length;
                },
            ]);
            if ($fp !== null) {
                curl_setopt($ch, CURLOPT_WRITEFUNCTION, static function ($curl, string $chunk) use ($fp, &$written, &$tooLarge, $maxBytes): int {
                    $length = strlen($chunk);
                    if ($written + $length > $maxBytes) { $tooLarge = true; return 0; }
                    $written += $length;
                    return fwrite($fp, $chunk) ?: 0;
                });
            }
            $ok = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
            $error = curl_error($ch);
            curl_close($ch);
            if ($fp !== null) fclose($fp);
            if ($primaryIp === '' || $this->isPrivateIp($primaryIp)) { if ($tmp !== false && is_file($tmp)) @unlink($tmp); throw new RuntimeException('WORKSECTION_API_SSRF_BLOCKED'); }
            if ($this->connectionId !== null) $this->repo->recordRequest($this->connectionId, $code, $headers);
            $body = is_string($ok) ? $ok : '';
            if ($fp !== null && $tmp !== false && is_file($tmp)) $body = (string)file_get_contents($tmp);
            if ($code === 429) {
                $delay = isset($headers['retry-after']) ? max(1, (int)$headers['retry-after']) : min(60, 2 ** $attempt);
                if ($this->connectionId !== null) $this->repo->recordRetryAfter($this->connectionId, $delay);
                if ($attempt < $attempts) { if ($tmp !== false && is_file($tmp)) @unlink($tmp); sleep($delay); continue; }
                if ($tmp !== false && is_file($tmp)) @unlink($tmp);
                throw new RuntimeException('WORKSECTION_RATE_LIMITED', 429);
            }
            if ($code === 401) { if ($tmp !== false && is_file($tmp)) @unlink($tmp); throw new RuntimeException('WORKSECTION_AUTH_FAILED', 401); }
            if ($code === 403) { if ($tmp !== false && is_file($tmp)) @unlink($tmp); throw new RuntimeException('WORKSECTION_FORBIDDEN', 403); }
            if ($code === 404) { if ($tmp !== false && is_file($tmp)) @unlink($tmp); throw new RuntimeException('WORKSECTION_NOT_FOUND', 404); }
            if ($ok === false || $code < 200 || $code >= 300) {
                if ($tmp !== false && is_file($tmp)) @unlink($tmp);
                if ($attempt < $attempts && ($code === 0 || $code >= 500)) { sleep(min(30, 2 ** $attempt)); continue; }
                throw new RuntimeException('WORKSECTION_HTTP_' . $code . ($error !== '' ? ': ' . $error : ''), $code);
            }
            return ['body' => $body, 'path' => $tmp !== false && is_file($tmp) ? $tmp : null, 'size' => $written, 'content_type' => isset($headers['content-type']) ? (string)$headers['content-type'] : null];
        }
        throw new RuntimeException('WORKSECTION_REQUEST_FAILED');
    }

    /** @return array{path:string,size:int,content_type:?string} */
    private function httpGet(string $token, string $url, int $maxBytes): array
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || (int)($parts['port'] ?? 443) !== 443) throw new RuntimeException('WORKSECTION_ATTACHMENT_URL_NOT_ALLOWED');
        $resolvedIp = $this->publicIpForHost($host);
        $tmp = tempnam(sys_get_temp_dir(), 'worksection-');
        if ($tmp === false) throw new RuntimeException('WORKSECTION_ATTACHMENT_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('WORKSECTION_ATTACHMENT_TEMP_FAILED'); }
        $written = 0; $tooLarge = false; $headers = [];
        $requestHeaders = ['Accept: */*', 'User-Agent: TropaTT-Worksection-Migration/1.0'];
        // The account URL may redirect file downloads to the same host without
        // a signed URL; forward credentials only to the exact account host.
        if ($this->authType === 'oauth2' && $host === $this->apiHost() && $token !== '') $requestHeaders[] = 'Authorization: Bearer ' . $token;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => max(10, $this->timeout * 2),
            CURLOPT_RESOLVE => [$this->curlResolveEntry($host, 443, $resolvedIp)],
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); }
                return $length;
            },
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($fp, &$written, &$tooLarge, $maxBytes): int {
                $length = strlen($chunk);
                if ($written + $length > $maxBytes) { $tooLarge = true; return 0; }
                $written += $length;
                return fwrite($fp, $chunk) ?: 0;
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
        curl_close($ch);
        fclose($fp);
        if ($primaryIp === '' || $this->isPrivateIp($primaryIp)) { @unlink($tmp); throw new RuntimeException('WORKSECTION_ATTACHMENT_SSRF_BLOCKED'); }
        if ($ok === false || $code < 200 || $code >= 300 || $tooLarge || $written > $maxBytes) { @unlink($tmp); throw new RuntimeException($tooLarge || $written > $maxBytes ? 'WORKSECTION_ATTACHMENT_TOO_LARGE' : 'WORKSECTION_ATTACHMENT_DOWNLOAD_FAILED'); }
        return ['path' => $tmp, 'size' => $written, 'content_type' => isset($headers['content-type']) ? (string)$headers['content-type'] : null];
    }

    /** @return array<int,array<string,mixed>> */
    private function collection(string $token, string $actionName, array $params, string $key): array
    {
        $response = $this->action($token, $actionName, $params);
        $items = $this->items($response, $key);
        if (count($items) > self::MAX_COLLECTION_ITEMS) throw new RuntimeException('WORKSECTION_COLLECTION_LIMIT_EXCEEDED');
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function optionalCollection(string $token, string $actionName, array $params, string $key): array
    {
        try { return $this->collection($token, $actionName, $params, $key); }
        catch (RuntimeException $e) {
            if (in_array($e->getCode(), [403, 404], true) || in_array($e->getMessage(), ['WORKSECTION_FORBIDDEN', 'WORKSECTION_NOT_FOUND'], true)) return [];
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function items(array $payload, string $preferredKey): array
    {
        foreach ([$preferredKey, 'data', 'items', 'results', 'values'] as $key) if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) return array_values(array_filter($payload[$key], 'is_array'));
        foreach ($payload as $key => $value) if (is_array($value) && array_is_list($value)) return array_values(array_filter($value, 'is_array'));
        return array_is_list($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    /** Worksection admin signature: md5 of sorted url-escaped k=v pairs + key. */
    public static function adminHash(string $actionName, array $params, string $apiKey): string
    {
        $keys = ['action'];
        foreach (array_keys($params) as $k) if (trim((string)$params[$k]) !== '') $keys[] = $k;
        sort($keys, SORT_STRING);
        $pairs = [];
        foreach ($keys as $k) {
            if ($k === 'action') { $pairs[] = 'action=' . $actionName; continue; }
            $pairs[] = str_replace('%20', '+', rawurlencode((string)$k)) . '=' . str_replace('%20', '+', rawurlencode((string)$params[$k]));
        }
        return md5(implode('&', $pairs) . $apiKey);
    }

    private function resolveDownloadUrl(string $url): string
    {
        if (str_starts_with($url, '//')) return 'https:' . $url;
        if (preg_match('/^https?:\/\//i', $url)) return $url;
        return rtrim($this->accountUrl, '/') . '/' . ltrim($url, '/');
    }

    private function validateDownloadHost(string $url): void
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || (int)($parts['port'] ?? 443) !== 443) throw new RuntimeException('WORKSECTION_ATTACHMENT_URL_NOT_ALLOWED');
        // File downloads must stay on the configured account host; never fetch
        // external storage with the account credential.
        if ($host !== $this->apiHost()) throw new RuntimeException('WORKSECTION_ATTACHMENT_HOST_BLOCKED');
    }

    private static function normalizeAccountUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') return '';
        $parts = parse_url($value);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('WORKSECTION_ACCOUNT_URL_INVALID');
        if (isset($parts['port']) && (int)$parts['port'] !== 443) throw new RuntimeException('WORKSECTION_ACCOUNT_URL_PORT_NOT_ALLOWED');
        return rtrim($value, '/');
    }

    private function waitRateLimit(int $connectionId): void
    {
        $state = $this->repo->rateState($connectionId) ?: [];
        $until = strtotime((string)($state['retry_after_until'] ?? ''));
        if ($until > time()) sleep($until - time() + 1);
        $last = strtotime((string)($state['last_request_at'] ?? ''));
        if ($last > 0) {
            $elapsed = microtime(true) - $last;
            if ($elapsed < self::MIN_REQUEST_INTERVAL_SECONDS) usleep((int)((self::MIN_REQUEST_INTERVAL_SECONDS - $elapsed) * 1000000) + 50000);
        }
    }

    private function apiHost(): string
    {
        $host = strtolower((string)parse_url($this->accountUrl, PHP_URL_HOST));
        if ($host === '') throw new RuntimeException('WORKSECTION_ACCOUNT_URL_INVALID');
        return $host;
    }

    private function assertPublicApiHost(): void
    {
        $this->publicIpForHost($this->apiHost());
    }

    /** @return array<int,string> */
    private function resolveHostIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) return [$host];
        $ips = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                    if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false) $ips[] = $ip;
                }
            }
        }
        if ($ips === []) $ips = gethostbynamel($host) ?: [];
        return array_values(array_unique(array_map('strval', $ips)));
    }

    private function isPrivateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local')) return true;
        $ips = $this->resolveHostIps($host);
        if ($ips === []) return true;
        foreach ($ips as $ip) if ($this->isPrivateIp($ip)) return true;
        return false;
    }

    private function publicIpForHost(string $host): string
    {
        if ($host === '' || $this->isPrivateHost($host)) throw new RuntimeException('WORKSECTION_HOST_RESOLUTION_BLOCKED');
        foreach ($this->resolveHostIps($host) as $ip) if (!$this->isPrivateIp($ip)) return $ip;
        throw new RuntimeException('WORKSECTION_HOST_RESOLUTION_BLOCKED');
    }

    private function curlResolveEntry(string $host, int $port, string $ip): string
    {
        return str_contains($host, ':') ? '[' . $host . ']:' . $port . ':' . $ip : $host . ':' . $port . ':' . $ip;
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
