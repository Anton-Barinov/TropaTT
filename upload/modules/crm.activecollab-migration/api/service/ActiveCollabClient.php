<?php
declare(strict_types=1);

namespace Module\Crm\ActiveCollabMigration\Service;

use Module\Crm\ActiveCollabMigration\Repository\ActiveCollabMigrationRepository;
use RuntimeException;

final class ActiveCollabClient
{
    private const PAGE_SIZE = 100;
    private const MAX_COLLECTION_ITEMS = 10000;
    private string $baseUrl;
    private ?int $connectionId = null;

    public function __construct(
        private readonly ActiveCollabMigrationRepository $repo,
        string $baseUrl = '',
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = self::normalizeBaseUrl($baseUrl);
    }

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    /** Connection test: projects is available on both cloud and self-hosted API roots. */
    public function me(string $token): array
    {
        $items = $this->collection($token, '/projects', ['limit' => 1], 'projects');
        return ['authenticated' => true, 'projects_count_sample' => count($items)];
    }

    /** ActiveCollab has no universal workspace resource; account_id is the scope. */
    public function workspaces(string $token, string $accountId = ''): array
    {
        $this->me($token);
        return [['gid' => $accountId !== '' ? $accountId : 'default', 'name' => $this->baseUrl]];
    }

    /** @return array<int,array<string,mixed>> */
    public function companies(string $token, bool $includeArchived = false): array
    {
        return $this->optionalCollection($token, $includeArchived ? '/companies/all' : '/companies', [], 'companies');
    }

    /** @return array<int,array<string,mixed>> */
    public function users(string $token): array
    {
        return $this->optionalCollection($token, '/users', [], 'users');
    }

    /** @return array<int,array<string,mixed>> */
    public function projects(string $token, bool $includeArchived = false): array
    {
        // ActiveCollab v1 documents only the active collection endpoint for
        // projects. The object flags are preserved when the API returns them,
        // but there is no supported /projects/all route to discover archived
        // projects globally.
        return $this->collection($token, '/projects', [], 'projects');
    }

    /** @return array<int,array<string,mixed>> */
    public function taskLists(string $token, string $projectId): array
    {
        return $this->optionalCollection($token, '/projects/' . rawurlencode($projectId) . '/task-lists', [], 'task_lists');
    }

    /** @return array<int,array<string,mixed>> */
    public function tasks(string $token, string $projectId, bool $includeArchived = false): array
    {
        // The documented v1 collection is project-scoped and active-only; do
        // not send undocumented is_trashed filters that some installations
        // interpret as an empty result.
        return $this->collection($token, '/projects/' . rawurlencode($projectId) . '/tasks', ['include_subtasks' => 1], 'tasks');
    }

    /** @return array<int,array<string,mixed>> */
    public function subtasks(string $token, string $projectId, string $taskId): array
    {
        return $this->optionalCollection($token, '/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId) . '/subtasks', [], 'subtasks');
    }

    /** @return array<int,array<string,mixed>> */
    public function comments(string $token, string $projectId, string $taskId): array
    {
        try { return $this->collection($token, '/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId) . '/comments', [], 'comments'); }
        catch (RuntimeException $e) {
            if (!in_array($e->getCode(), [403, 404], true) && !in_array($e->getMessage(), ['ACTIVECOLLAB_FORBIDDEN', 'ACTIVECOLLAB_NOT_FOUND'], true)) throw $e;
            return $this->optionalCollection($token, '/comments/task/' . rawurlencode($taskId), [], 'comments');
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function attachments(string $token, string $projectId, string $taskId): array
    {
        try { return $this->collection($token, '/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId) . '/attachments', [], 'attachments'); }
        catch (RuntimeException $e) {
            if (!in_array($e->getCode(), [403, 404], true) && !in_array($e->getMessage(), ['ACTIVECOLLAB_FORBIDDEN', 'ACTIVECOLLAB_NOT_FOUND'], true)) throw $e;
            return $this->optionalCollection($token, '/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId) . '/files', [], 'files');
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function timeRecords(string $token, string $projectId, string $taskId): array
    {
        return $this->optionalCollection($token, '/projects/' . rawurlencode($projectId) . '/tasks/' . rawurlencode($taskId) . '/time-records', [], 'time_records');
    }

    /** Download an attachment URL without forwarding the API token to redirects. */
    public function downloadAttachment(string $token, string $url, int $maxBytes): array
    {
        $current = $url;
        $sendAuthorization = true;
        for ($redirect = 0; $redirect <= 3; $redirect++) {
            $parts = parse_url($current);
            $host = strtolower((string)($parts['host'] ?? ''));
            if (($parts['scheme'] ?? '') !== 'https' || $host === '' || $this->isPrivateHost($host)) throw new RuntimeException('ACTIVECOLLAB_SSRF_BLOCKED');
            $port = (int)($parts['port'] ?? 443);
            if ($port !== 443) throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_PORT_NOT_ALLOWED');
            $sameHost = $host === strtolower((string)parse_url($this->baseUrl, PHP_URL_HOST));
            $signed = $this->isSignedStorageUrl((string)($parts['query'] ?? ''));
            if (!$sameHost && !$signed && !$this->isStorageHost($host)) throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_HOST_BLOCKED');
            $resolvedIp = $this->publicIpForHost($host);

            $tmp = tempnam(sys_get_temp_dir(), 'activecollab-');
            if ($tmp === false) throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_TEMP_FAILED');
            $fp = fopen($tmp, 'wb');
            if ($fp === false) { @unlink($tmp); throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_TEMP_FAILED'); }
            $written = 0;
            $tooLarge = false;
            $headers = [];
            $ch = curl_init($current);
            $requestHeaders = ['Accept: */*', 'User-Agent: TropaTT-ActiveCollab-Migration/1.0'];
            if ($sameHost && $sendAuthorization) $requestHeaders[] = 'X-Angie-AuthApiToken: ' . $token;
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
            if ($primaryIp === '' || $this->isPrivateIp($primaryIp)) { @unlink($tmp); throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_SSRF_BLOCKED'); }
            fclose($fp);
            if ($code >= 300 && $code < 400 && !empty($headers['location'])) {
                @unlink($tmp);
                $location = $this->resolveRedirect($current, (string)$headers['location']);
                if ($location === null) throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_REDIRECT_BLOCKED');
                $current = $location;
                // Never forward the tenant credential across a redirect, even
                // when the redirect remains on the same host.
                $sendAuthorization = false;
                continue;
            }
            if ($ok === false || $code < 200 || $code >= 300 || $tooLarge || $written > $maxBytes) {
                @unlink($tmp);
                throw new RuntimeException($tooLarge || $written > $maxBytes ? 'ACTIVECOLLAB_ATTACHMENT_TOO_LARGE' : 'ACTIVECOLLAB_ATTACHMENT_DOWNLOAD_FAILED');
            }
            return ['path' => $tmp, 'size' => $written, 'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream')];
        }
        throw new RuntimeException('ACTIVECOLLAB_ATTACHMENT_REDIRECT_LIMIT');
    }

    /** @return array<string,mixed> */
    private function request(string $token, string $path, array $query = []): array
    {
        if ($this->connectionId !== null) $this->waitRateLimit($this->connectionId);
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        if ($query !== []) $url .= '?' . http_build_query($query);
        for ($attempt = 1; $attempt <= max(1, $this->maxRetries); $attempt++) {
            $headers = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => max(5, $this->timeout),
                CURLOPT_RESOLVE => [$this->curlResolveEntry($this->apiHost(), 443, $this->publicIpForHost($this->apiHost()))],
                CURLOPT_HTTPHEADER => ['X-Angie-AuthApiToken: ' . $token, 'Accept: application/json', 'Content-Type: application/json', 'User-Agent: TropaTT-ActiveCollab-Migration/1.0'],
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); }
                    return $length;
                },
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
            $error = curl_error($ch);
            curl_close($ch);
            if ($primaryIp === '' || $this->isPrivateIp($primaryIp)) throw new RuntimeException('ACTIVECOLLAB_API_SSRF_BLOCKED');
            if ($this->connectionId !== null) $this->repo->recordRequest($this->connectionId, $code, $headers);
            $decoded = is_string($body) ? json_decode($body, true) : null;
            if ($code === 429) {
                $delay = isset($headers['retry-after'])
                    ? max(1, (int)$headers['retry-after'])
                    : (isset($headers['x-angie-retry-after']) ? max(1, (int)$headers['x-angie-retry-after']) : min(60, 2 ** $attempt));
                if ($this->connectionId !== null) $this->repo->recordRetryAfter($this->connectionId, $delay);
                if ($attempt < $this->maxRetries) { sleep($delay); continue; }
                throw new RuntimeException('ACTIVECOLLAB_RATE_LIMITED', 429);
            }
            if ($code === 401) throw new RuntimeException('ACTIVECOLLAB_AUTH_FAILED', 401);
            if ($code === 403) throw new RuntimeException('ACTIVECOLLAB_FORBIDDEN', 403);
            if ($code === 404) throw new RuntimeException('ACTIVECOLLAB_NOT_FOUND', 404);
            if ($body === false || $code < 200 || $code >= 300) {
                if ($attempt < $this->maxRetries && ($code === 0 || $code >= 500)) { sleep(min(30, 2 ** $attempt)); continue; }
                throw new RuntimeException('ACTIVECOLLAB_HTTP_' . $code . ($error !== '' ? ': ' . $error : ''), $code);
            }
            if (!is_array($decoded)) throw new RuntimeException('ACTIVECOLLAB_INVALID_RESPONSE');
            return $decoded;
        }
        throw new RuntimeException('ACTIVECOLLAB_REQUEST_FAILED');
    }

    /** @return array<int,array<string,mixed>> */
    private function collection(string $token, string $path, array $query, string $key): array
    {
        $items = [];
        $page = 1;
        $seen = [];
        do {
            if ($page > 200) throw new RuntimeException('ACTIVECOLLAB_PAGINATION_LIMIT_EXCEEDED');
            $pageQuery = array_merge($query, ['page' => $page, 'per_page' => self::PAGE_SIZE]);
            $response = $this->request($token, $path, $pageQuery);
            $pageItems = $this->items($response, $key);
            $signature = hash('sha256', (string)json_encode($pageItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($seen[$signature])) throw new RuntimeException('ACTIVECOLLAB_PAGINATION_LOOP');
            $seen[$signature] = true;
            foreach ($pageItems as $item) {
                $items[] = $item;
                if (count($items) > self::MAX_COLLECTION_ITEMS) throw new RuntimeException('ACTIVECOLLAB_COLLECTION_LIMIT_EXCEEDED');
            }
            // ActiveCollab v1 commonly returns a complete JSON list without a
            // pagination envelope. Do not request page 2 for such responses:
            // page/per_page may be ignored and would duplicate the full list.
            $hasPaginationEnvelope = array_key_exists('pagination', $response)
                || (isset($response['meta']) && is_array($response['meta']) && array_key_exists('pagination', $response['meta']))
                || array_key_exists('next', $response);
            if (!$hasPaginationEnvelope) break;
            $pagination = (array)($response['pagination'] ?? $response['meta']['pagination'] ?? []);
            $hasMore = (bool)($pagination['has_more'] ?? $pagination['hasMore'] ?? false);
            $total = isset($pagination['total']) ? (int)$pagination['total'] : null;
            if ($pageItems === [] || (!$hasMore && $total === null && count($pageItems) < self::PAGE_SIZE) || (!$hasMore && $total !== null && count($items) >= $total)) break;
            if (!$hasMore && $total === null && count($pageItems) < self::PAGE_SIZE) break;
            $page++;
        } while (true);
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    private function optionalCollection(string $token, string $path, array $query, string $key): array
    {
        try { return $this->collection($token, $path, $query, $key); }
        catch (RuntimeException $e) {
            if (in_array($e->getCode(), [403, 404], true) || in_array($e->getMessage(), ['ACTIVECOLLAB_FORBIDDEN', 'ACTIVECOLLAB_NOT_FOUND'], true)) return [];
            throw $e;
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function items(array $payload, string $preferredKey): array
    {
        $candidates = [$preferredKey, 'items', 'data', 'collection', 'results', 'values'];
        foreach ($candidates as $key) if (isset($payload[$key]) && is_array($payload[$key]) && array_is_list($payload[$key])) return array_values(array_filter($payload[$key], 'is_array'));
        foreach ($payload as $key => $value) if (is_array($value) && array_is_list($value)) return array_values(array_filter($value, 'is_array'));
        return array_is_list($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    private static function normalizeBaseUrl(string $value): string
    {
        $value = trim($value);
        if ($value === '') return 'https://app.activecollab.com/api/v1';
        $parts = parse_url($value);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('ACTIVECOLLAB_BASE_URL_INVALID');
        if (isset($parts['port']) && (int)$parts['port'] !== 443) throw new RuntimeException('ACTIVECOLLAB_BASE_URL_PORT_NOT_ALLOWED');
        return rtrim($value, '/');
    }

    private function waitRateLimit(int $connectionId): void
    {
        $state = $this->repo->rateState($connectionId) ?: [];
        $until = strtotime((string)($state['retry_after_until'] ?? ''));
        if ($until > time()) sleep($until - time() + 1);
        $last = strtotime((string)($state['last_request_at'] ?? ''));
        if ($last > 0 && time() - $last < 1) usleep(1000000);
    }

    private function apiHost(): string
    {
        $host = strtolower((string)parse_url($this->baseUrl, PHP_URL_HOST));
        if ($host === '') throw new RuntimeException('ACTIVECOLLAB_BASE_URL_INVALID');
        return $host;
    }

    private function isStorageHost(string $host): bool
    {
        return $host === 's3.amazonaws.com' || (bool)preg_match('/^(?:[^.]+\.)?s3(?:[.-][a-z0-9-]+)?\.amazonaws\.com$/', $host);
    }

    private function isSignedStorageUrl(string $query): bool
    {
        parse_str($query, $params);
        $normalized = [];
        foreach ($params as $key => $value) $normalized[strtolower((string)$key)] = trim((string)$value);
        $signature = $normalized['x-amz-signature'] ?? $normalized['signature'] ?? '';
        $expires = $normalized['x-amz-expires'] ?? $normalized['expires'] ?? '';
        return $signature !== '' && strlen($signature) >= 16 && ctype_digit($expires) && (int)$expires > 0 && (int)$expires <= 604800;
    }

    private function isPrivateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local')) return true;
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) return true;
        foreach ($ips as $ip) if ($this->isPrivateIp((string)$ip)) return true;
        return false;
    }

    private function publicIpForHost(string $host): string
    {
        if ($host === '' || $this->isPrivateHost($host)) throw new RuntimeException('ACTIVECOLLAB_HOST_RESOLUTION_BLOCKED');
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        foreach ($ips as $ip) if (!$this->isPrivateIp((string)$ip)) return (string)$ip;
        throw new RuntimeException('ACTIVECOLLAB_HOST_RESOLUTION_BLOCKED');
    }

    private function curlResolveEntry(string $host, int $port, string $ip): string
    {
        return str_contains($host, ':') ? '[' . $host . ']:' . $port . ':' . $ip : $host . ':' . $port . ':' . $ip;
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    private function resolveRedirect(string $from, string $location): ?string
    {
        if (str_starts_with($location, '//')) return (string)parse_url($from, PHP_URL_SCHEME) . ':' . $location;
        if (preg_match('/^https?:\/\//i', $location)) return $location;
        $parts = parse_url($from);
        if (!is_array($parts) || empty($parts['host'])) return null;
        $origin = ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        if (str_starts_with($location, '/')) return $origin . $location;
        $path = (string)($parts['path'] ?? '/');
        $dir = substr($path, 0, (int)strrpos($path, '/') + 1);
        return $origin . ($dir !== '' ? $dir : '/') . $location;
    }
}
