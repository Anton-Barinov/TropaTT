<?php
declare(strict_types=1);

namespace Module\Crm\TodoistMigration\Service;

use Module\Crm\TodoistMigration\Repository\TodoistMigrationRepository;
use RuntimeException;

final class TodoistClient
{
    // Todoist's current official API is v1. Keep all resource calls on the
    // unified API; REST v2 and Sync v9 are retired endpoints.
    private const API = 'https://api.todoist.com/api/v1';
    private const OAUTH_TOKEN = 'https://todoist.com/oauth/access_token';

    private ?int $connectionId = null;
    /** @var callable(string):?string|null */
    private $tokenRefreshHandler = null;

    public function __construct(
        private readonly TodoistMigrationRepository $repo,
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4
    ) {
    }

    public function setConnectionId(?int $id): void
    {
        $this->connectionId = $id;
    }

    public function setTokenRefreshHandler(?callable $handler): void
    {
        $this->tokenRefreshHandler = $handler;
    }

    /** @return array{projects_count:int,ok:bool} */
    public function test(string $token): array
    {
        $projects = $this->request($token, '/projects');
        return ['projects_count' => count($this->listFromResponse($projects)), 'ok' => true];
    }

    /** @return array<string,mixed> */
    public function oauthExchange(string $clientId, string $clientSecret, string $code, ?string $redirectUri = null): array
    {
        $fields = [
            'client_id' => trim($clientId),
            'client_secret' => $clientSecret,
            'code' => trim($code),
            'grant_type' => 'authorization_code',
        ];
        if ($redirectUri !== null && trim($redirectUri) !== '') {
            $fields['redirect_uri'] = trim($redirectUri);
        }

        $ch = curl_init(self::OAUTH_TOKEN);
        if ($ch === false) {
            throw new RuntimeException('TODOIST_OAUTH_EXCHANGE_FAILED');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($fields),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => max(5, $this->timeout),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($data) || trim((string)($data['access_token'] ?? '')) === '') {
            throw new RuntimeException('TODOIST_OAUTH_EXCHANGE_FAILED', $status);
        }
        return $data;
    }

    public function refreshAccessToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        $ch = curl_init(self::OAUTH_TOKEN);
        if ($ch === false) throw new RuntimeException('TODOIST_OAUTH_REFRESH_FAILED');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['client_id' => trim($clientId), 'client_secret' => $clientSecret, 'refresh_token' => $refreshToken, 'grant_type' => 'refresh_token']), CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT => max(5, $this->timeout), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true]);
        $body = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $data = is_string($body) ? json_decode($body, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($data) || trim((string)($data['access_token'] ?? '')) === '') throw new RuntimeException('TODOIST_OAUTH_REFRESH_FAILED', $status);
        return $data;
    }

    public function oauthAuthorizeUrl(string $clientId, string $state, string $scope = 'data:read_write', ?string $redirectUri = null): string
    {
        $query = ['client_id' => trim($clientId), 'scope' => $scope, 'state' => $state];
        if ($redirectUri !== null && trim($redirectUri) !== '') {
            $query['redirect_uri'] = trim($redirectUri);
        }
        return 'https://todoist.com/oauth/authorize?' . http_build_query($query);
    }

    /** @return array<int,array<string,mixed>> */
    public function projects(string $token, bool $includeArchived = false): array
    {
        $out = [];
        $this->eachProjects($token, $includeArchived, static function (array $item) use (&$out): void {
            $out[] = $item;
        });
        return $out;
    }

    public function eachProjects(string $token, bool $includeArchived, callable $cb): int
    {
        // API v1 lists active projects. The crawler still receives the flag so
        // it can report the archived-project limitation consistently.
        return $this->stream($token, '/projects', ['limit' => 200], $cb, self::API);
    }

    public function eachSections(string $token, string $projectId, callable $cb): int
    {
        return $this->stream($token, '/sections', ['project_id' => $projectId, 'limit' => 200], $cb, self::API);
    }

    public function eachTasks(string $token, ?string $projectId, callable $cb): int
    {
        $query = ['limit' => 200];
        if ($projectId !== null && $projectId !== '') {
            $query['project_id'] = $projectId;
        }
        return $this->stream($token, '/tasks', $query, $cb, self::API);
    }

    public function eachLabels(string $token, callable $cb): int
    {
        return $this->stream($token, '/labels', ['limit' => 200], $cb, self::API);
    }

    public function eachComments(string $token, string $taskId, callable $cb): int
    {
        return $this->stream($token, '/comments', ['task_id' => $taskId, 'limit' => 100], $cb, self::API);
    }

    public function eachProjectComments(string $token, string $projectId, callable $cb): int
    {
        return $this->stream($token, '/comments', ['project_id' => $projectId, 'limit' => 100], $cb, self::API);
    }

    public function eachCollaborators(string $token, string $projectId, callable $cb): int
    {
        return $this->stream($token, '/projects/' . rawurlencode($projectId) . '/collaborators', ['limit' => 100], $cb, self::API);
    }

    public function eachCompletedTasks(string $token, ?string $projectId, ?string $since, ?string $until, callable $cb): int
    {
        $query = ['limit' => 200];
        // API v1 completion-date history supports workspace/filter bounds, not
        // project_id. The crawler filters the account-wide stream locally.
        if ($since !== null && $since !== '') $query['since'] = $since;
        if ($until !== null && $until !== '') $query['until'] = $until;
        // API v1 returns completed tasks under `items` and requires a bounded
        // date range (the crawler keeps each request within the API limit).
        return $this->stream($token, '/tasks/completed/by_completion_date', $query, $cb, self::API);
    }

    /** @return array<int,array<string,mixed>> */
    private function listFromResponse(array $response): array
    {
        $data = $response['results'] ?? $response['items'] ?? $response['data'] ?? $response;
        if (!is_array($data)) return [];
        return array_values(array_filter($data, 'is_array'));
    }

    /** Stream cursor- or offset-paginated collections without retaining all pages. */
    private function stream(string $token, string $path, array $query, callable $cb, string $base, ?callable $filter = null): int
    {
        $query = array_filter($query, static fn(mixed $value): bool => $value !== null && $value !== '');
        $limit = min(200, max(1, (int)($query['limit'] ?? 100)));
        $query['limit'] = $limit;
        $offsetPaginationAllowed = $path === '/tasks/completed/by_completion_date' || $path === '/comments' || str_contains($path, '/collaborators');
        $cursor = null;
        $offset = null;
        $count = 0;
        $seen = [];
        $pageSignatures = [];

        for ($page = 0; $page < 10000; $page++) {
            $requestQuery = $query;
            if ($cursor !== null) {
                $requestQuery['cursor'] = $cursor;
                unset($requestQuery['offset']);
            } elseif ($offset !== null) {
                $requestQuery['offset'] = $offset;
                unset($requestQuery['cursor']);
            }

            $response = $this->request($token, $path, $requestQuery, $base);
            $items = $this->listFromResponse($response);
            if ($items === []) break;
            // Some compatible gateways omit pagination metadata and return the
            // same first page even when an offset is supplied. Stop on a
            // repeated page instead of issuing requests until the hard limit.
            $signature = hash('sha256', (string)json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($pageSignatures[$signature])) break;
            $pageSignatures[$signature] = true;

            $uniqueItems = 0;
            foreach ($items as $item) {
                $key = (string)($item['id'] ?? $item['task_id'] ?? $item['note_id'] ?? '');
                if ($key !== '' && isset($seen[$key])) continue;
                if ($key !== '') $seen[$key] = true;
                $uniqueItems++;
                if ($filter !== null && !$filter($item)) continue;
                $count++;
                if ($cb($item) === false) return $count;
            }

            $next = $response['next_cursor']
                ?? ($response['next_page']['cursor'] ?? null)
                ?? ($response['meta']['next_cursor'] ?? null);
            if (is_string($next) && $next !== '') {
                $cursor = $next;
                $offset = null;
                continue;
            }

            $nextOffset = $response['next_offset'] ?? ($response['meta']['next_offset'] ?? null);
            if ($nextOffset !== null && is_numeric($nextOffset)) {
                $nextOffset = (int)$nextOffset;
                if ($nextOffset > 0 && $nextOffset !== $offset) {
                    $offset = $nextOffset;
                    $cursor = null;
                    continue;
                }
            }

            // Keep a defensive fallback for older-compatible responses that omit
            // a cursor. API v1 normally terminates with next_cursor=null.
            if ($offsetPaginationAllowed && count($items) >= $limit && $uniqueItems === count($items)) {
                $offset = ($offset ?? 0) + count($items);
                $cursor = null;
                continue;
            }
            break;
        }
        return $count;
    }

    /** @return array<string,mixed> */
    private function request(string $token, string $path, array $query = [], string $base = self::API): array
    {
        $currentToken = $token;
        $attempts = max(1, $this->maxRetries);
        $refreshed = false;
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $url = $base . $path . ($query !== [] ? '?' . http_build_query($query) : '');
            if ($this->connectionId !== null) $this->waitRate($this->connectionId);
            $headers = [];
            $ch = curl_init($url);
            if ($ch === false) throw new RuntimeException('TODOIST_REQUEST_FAILED');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(5, $this->timeout),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $currentToken, 'Accept: application/json', 'User-Agent: TropaTT-Todoist-Migration/1.0'],
                CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) {
                        [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
                        $headers[strtolower(trim($key))] = trim($value);
                    }
                    return $length;
                },
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            $retryAfter = isset($headers['retry-after']) ? max(1, (int)$headers['retry-after']) : null;
            if ($this->connectionId !== null) $this->repo->recordRequest($this->connectionId, $status, $status === 429 ? $retryAfter : null);
            $decoded = is_string($body) ? json_decode($body, true) : null;

            if ($status === 429) {
                if ($attempt < $attempts) {
                    sleep(min(60, $retryAfter ?? (2 ** $attempt)));
                    continue;
                }
                throw new RuntimeException('TODOIST_RATE_LIMITED', 429);
            }
            if ($status === 401 && !$refreshed && is_callable($this->tokenRefreshHandler)) {
                $newToken = ($this->tokenRefreshHandler)($currentToken);
                if (is_string($newToken) && trim($newToken) !== '') {
                    $currentToken = trim($newToken);
                    $refreshed = true;
                    continue;
                }
            }
            if ($status === 401) throw new RuntimeException('TODOIST_AUTH_FAILED', 401);
            if ($status === 403) throw new RuntimeException('TODOIST_FORBIDDEN', 403);
            if ($status === 404) throw new RuntimeException('TODOIST_NOT_FOUND', 404);
            if ($body === false || $status < 200 || $status >= 300) {
                if ($attempt < $attempts && ($status === 0 || $status >= 500)) {
                    sleep(min(30, 2 ** $attempt));
                    continue;
                }
                throw new RuntimeException('TODOIST_HTTP_' . $status . ($error !== '' ? ': ' . $error : ''), $status);
            }
            if (!is_array($decoded)) throw new RuntimeException('TODOIST_INVALID_RESPONSE');
            return $decoded;
        }
        throw new RuntimeException('TODOIST_REQUEST_FAILED');
    }

    private function waitRate(int $id): void
    {
        $state = $this->repo->rateState($id);
        $rawUntil = trim((string)($state['retry_after_until'] ?? ''));
        if ($rawUntil === '') return;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $rawUntil, new \DateTimeZone('UTC'));
        $until = $date instanceof \DateTimeImmutable ? $date->getTimestamp() : null;
        if ($until !== null && $until > time()) sleep($until - time() + 1);
    }

    /** Download an attachment with size, HTTPS, redirect and SSRF checks. */
    public function downloadAttachment(string $token, string $url, int $maxBytes, int $redirects = 0): array
    {
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        $port = $parts['port'] ?? null;
        $resolvedAddress = $host !== '' ? $this->resolvePublicAddress($host) : null;
        if ($scheme !== 'https' || ($port !== null && (int)$port !== 443) || $host === '' || !$this->allowedAttachmentHost($host) || $resolvedAddress === null) {
            throw new RuntimeException('TODOIST_ATTACHMENT_SSRF_BLOCKED');
        }
        if ($redirects > 3) throw new RuntimeException('TODOIST_ATTACHMENT_REDIRECT_LIMIT');
        $tmp = tempnam(sys_get_temp_dir(), 'todoist-');
        if ($tmp === false) throw new RuntimeException('TODOIST_ATTACHMENT_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('TODOIST_ATTACHMENT_TEMP_FAILED'); }
        $written = 0;
        $headers = [];
        $ch = curl_init($url);
        if ($ch === false) { fclose($fp); @unlink($tmp); throw new RuntimeException('TODOIST_ATTACHMENT_DOWNLOAD_FAILED'); }
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use ($fp, &$written, $maxBytes): int {
                $length = strlen($chunk);
                if ($written + $length > $maxBytes) return 0;
                $written += $length;
                return fwrite($fp, $chunk) ?: 0;
            },
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_RESOLVE => [$host . ':443:' . $resolvedAddress],
            CURLOPT_HTTPHEADER => array_merge(['Accept: */*', 'User-Agent: TropaTT-Todoist-Migration/1.0'], $host === 'files.todoist.com' && trim($token) !== '' ? ['Authorization: Bearer ' . $token] : []),
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$headers): int {
                $length = strlen($line);
                if (str_contains($line, ':')) {
                    [$key, $value] = array_pad(explode(':', $line, 2), 2, '');
                    $headers[strtolower(trim($key))] = trim($value);
                }
                return $length;
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if ($ok !== false && in_array($status, [301, 302, 303, 307, 308], true) && !empty($headers['location'])) {
            $location = trim((string)$headers['location']);
            if (str_starts_with($location, '//')) $location = 'https:' . $location;
            elseif (str_starts_with($location, '/')) $location = 'https://' . $host . $location;
            @unlink($tmp);
            return $this->downloadAttachment($token, $location, $maxBytes, $redirects + 1);
        }
        if ($ok === false || $status < 200 || $status >= 300 || $written > $maxBytes) {
            @unlink($tmp);
            throw new RuntimeException($written > $maxBytes ? 'TODOIST_ATTACHMENT_TOO_LARGE' : 'TODOIST_ATTACHMENT_DOWNLOAD_FAILED');
        }
        return ['path' => $tmp, 'size' => $written, 'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream')];
    }

    private function allowedAttachmentHost(string $host): bool
    {
        return $host === 'files.todoist.com' || str_ends_with($host, '.cloudfront.net') || $host === 'todoist.com' || str_ends_with($host, '.todoist.com');
    }

    private function resolvePublicAddress(string $host): ?string
    {
        $records = function_exists('dns_get_record') ? (array)@dns_get_record($host, DNS_A) : [];
        $addresses = [];
        foreach ($records as $record) {
            $ip = trim((string)($record['ip'] ?? ''));
            if ($ip !== '') $addresses[] = $ip;
        }
        if ($addresses === []) $addresses = gethostbynamel($host) ?: [];
        foreach ($addresses as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) return $ip;
        }
        return null;
    }

    private function privateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local')) return true;
        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) $ips[] = $host;
        elseif (function_exists('dns_get_record')) {
            foreach ((array)@dns_get_record($host, DNS_A | DNS_AAAA) as $record) {
                $ip = (string)($record['ip'] ?? $record['ipv6'] ?? '');
                if ($ip !== '') $ips[] = $ip;
            }
        }
        if ($ips === []) $ips = gethostbynamel($host) ?: [];
        if ($ips === []) return true;
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) return true;
        }
        return false;
    }
}
