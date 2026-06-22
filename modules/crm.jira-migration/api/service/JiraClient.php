<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration\Service;

use Module\Crm\JiraMigration\Repository\JiraMigrationRepository;
use RuntimeException;

final class JiraClient
{
    private const MAX_REQUESTS_PER_WINDOW = 100;
    private const WINDOW_SECONDS = 60;

    private int $timeout;
    private int $maxRetries;
    private ?JiraMigrationRepository $repo = null;
    private ?int $connectionId = null;

    public function __construct(int $timeout = 60, int $maxRetries = 3, ?JiraMigrationRepository $repo = null)
    {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->repo = $repo;
    }

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    private function request(string $baseUrl, string $email, string $token, string $path, string $method = 'GET', ?array $query = null, ?string $body = null): array
    {
        $baseUrl = rtrim($baseUrl, '/');
        $url = $baseUrl . $path;
        if ($query !== null && $query !== []) {
            $url .= '?' . http_build_query($query);
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $this->waitIfRateLimited();

            $responseHeaders = [];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Basic ' . base64_encode($email . ':' . $token),
                    'Accept: application/json',
                    'User-Agent: TropaTT-Jira-Migration/1.0',
                    'Content-Type: application/json',
                ],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                    $len = strlen($headerLine);
                    if (stripos($headerLine, 'Retry-After:') === 0) {
                        $responseHeaders['retry-after'] = (int)trim(substr($headerLine, 12));
                    }
                    return $len;
                },
            ]);

            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body !== null) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || $response === '') {
                $response = '{}';
            }

            $this->trackRequest();

            if ($httpCode === 429) {
                $retryAfter = isset($responseHeaders['retry-after']) ? max(1, (int)$responseHeaders['retry-after']) : (5 * $attempt);
                $this->storeRateLimitRetry($retryAfter);

                if ($attempt < $this->maxRetries) {
                    sleep($retryAfter);
                    continue;
                }
                throw new RuntimeException("JIRA_RATE_LIMITED: Exhausted {$this->maxRetries} retries", 429);
            }

            if ($httpCode === 401) {
                throw new RuntimeException('JIRA_AUTH_FAILED: Invalid email or API token', 401);
            }

            if ($httpCode === 403) {
                throw new RuntimeException('JIRA_FORBIDDEN: Account lacks permissions', 403);
            }

            if ($httpCode === 404) {
                throw new RuntimeException('JIRA_NOT_FOUND: Resource not found', 404);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException("JIRA_ERROR: HTTP $httpCode", $httpCode);
            }

            $decoded = json_decode($response, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('JIRA_RATE_LIMITED: Max retries reached', 429);
    }

    private function waitIfRateLimited(): void
    {
        if ($this->repo === null || $this->connectionId === null) {
            return;
        }
        $rateLimit = $this->repo->getRateLimit($this->connectionId);
        if ($rateLimit === null) {
            $this->repo->initRateLimit($this->connectionId);
            return;
        }

        if (!empty($rateLimit['retry_after_until'])) {
            $retryUntil = strtotime((string)$rateLimit['retry_after_until']);
            if ($retryUntil > time()) {
                sleep($retryUntil - time() + 1);
            }
        }

        if (!empty($rateLimit['window_started_at'])) {
            $windowStart = strtotime((string)$rateLimit['window_started_at']);
            $requestsMade = (int)($rateLimit['requests_made'] ?? 0);
            $elapsed = time() - $windowStart;
            if ($elapsed < self::WINDOW_SECONDS && $requestsMade >= self::MAX_REQUESTS_PER_WINDOW) {
                sleep(self::WINDOW_SECONDS - $elapsed + 1);
            }
        }
    }

    private function trackRequest(): void
    {
        if ($this->repo === null || $this->connectionId === null) {
            return;
        }
        $rateLimit = $this->repo->getRateLimit($this->connectionId);
        if ($rateLimit) {
            $windowStart = !empty($rateLimit['window_started_at']) ? strtotime((string)$rateLimit['window_started_at']) : 0;
            $reset = (time() - $windowStart) >= self::WINDOW_SECONDS;
            $this->repo->updateRateLimitAfterRequest($this->connectionId, $reset);
        }
    }

    private function storeRateLimitRetry(int $seconds): void
    {
        if ($this->repo === null || $this->connectionId === null) {
            return;
        }
        $retryUntil = gmdate('Y-m-d H:i:s', time() + $seconds);
        $this->repo->updateRateLimitAfterRequest($this->connectionId, false, $retryUntil);
    }

    private function paginate(string $baseUrl, string $email, string $token, string $path, array $query = [], int $maxResults = 100): iterable
    {
        $query['maxResults'] = min($maxResults, 100);
        $startAt = 0;
        $isLast = false;

        while (!$isLast) {
            $query['startAt'] = $startAt;
            $data = $this->request($baseUrl, $email, $token, $path, 'GET', $query);

            foreach (($data['values'] ?? $data['issues'] ?? $data['sprints'] ?? []) as $item) {
                yield $item;
            }

            $total = (int)($data['total'] ?? 0);
            $startAt += count($data['values'] ?? $data['issues'] ?? $data['sprints'] ?? []);
            $isLast = ($startAt >= $total);
        }
    }

    // ── Connection test ──

    public function testConnection(string $siteUrl, string $email, string $token): array
    {
        try {
            $myself = $this->request($siteUrl, $email, $token, '/rest/api/3/myself');
            $serverInfo = $this->request($siteUrl, $email, $token, '/rest/api/3/serverInfo');

            $projects = $this->request($siteUrl, $email, $token, '/rest/api/3/project/search', 'GET', ['maxResults' => 1]);

            return [
                'success' => true,
                'message' => 'Connection successful',
                'user' => [
                    'account_id' => $myself['accountId'] ?? null,
                    'display_name' => $myself['displayName'] ?? null,
                    'email' => $myself['emailAddress'] ?? null,
                ],
                'server_info' => [
                    'base_url' => $serverInfo['baseUrl'] ?? null,
                    'version' => $serverInfo['version'] ?? null,
                    'deployment_type' => $serverInfo['deploymentType'] ?? null,
                ],
                'projects_count' => (int)($projects['total'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // ── Projects ──

    public function getProjects(string $siteUrl, string $email, string $token, ?array $keys = null): array
    {
        $projects = [];
        foreach ($this->paginate($siteUrl, $email, $token, '/rest/api/3/project/search', ['maxResults' => 100], 100) as $project) {
            $key = (string)($project['key'] ?? '');
            if ($keys !== null && !in_array($key, $keys, true)) {
                continue;
            }
            $projects[] = [
                'id' => (string)$project['id'],
                'key' => $key,
                'name' => (string)($project['name'] ?? ''),
                'project_type_key' => (string)($project['projectTypeKey'] ?? ''),
                'lead' => [
                    'account_id' => $project['lead']['accountId'] ?? null,
                    'display_name' => $project['lead']['displayName'] ?? null,
                ],
                'style' => (string)($project['style'] ?? 'classic'),
            ];
        }
        return $projects;
    }

    // ── Fields ──

    public function getFields(string $siteUrl, string $email, string $token): array
    {
        try {
            return $this->request($siteUrl, $email, $token, '/rest/api/3/field');
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Statuses ──

    public function getStatuses(string $siteUrl, string $email, string $token): array
    {
        try {
            $data = $this->request($siteUrl, $email, $token, '/rest/api/3/status');
            $result = [];
            foreach ($data as $status) {
                $result[] = [
                    'id' => (string)($status['id'] ?? ''),
                    'name' => (string)($status['name'] ?? ''),
                    'status_category' => $status['statusCategory']['name'] ?? null,
                ];
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Boards (Jira Software) ──

    public function getBoards(string $siteUrl, string $email, string $token): array
    {
        $boards = [];
        try {
            foreach ($this->paginate($siteUrl, $email, $token, '/rest/agile/1.0/board', ['maxResults' => 50], 50) as $board) {
                $boards[] = [
                    'id' => (int)($board['id'] ?? 0),
                    'name' => (string)($board['name'] ?? ''),
                    'type' => (string)($board['type'] ?? ''),
                ];
            }
        } catch (\Throwable) {
        }
        return $boards;
    }

    // ── Sprints (Jira Software) ──

    public function getBoardSprints(string $siteUrl, string $email, string $token, int $boardId): array
    {
        $sprints = [];
        try {
            foreach ($this->paginate($siteUrl, $email, $token, "/rest/agile/1.0/board/{$boardId}/sprint", ['maxResults' => 50], 50) as $sprint) {
                $sprints[] = [
                    'id' => (int)($sprint['id'] ?? 0),
                    'name' => (string)($sprint['name'] ?? ''),
                    'state' => (string)($sprint['state'] ?? ''),
                    'start_date' => $sprint['startDate'] ?? null,
                    'end_date' => $sprint['endDate'] ?? null,
                    'goal' => (string)($sprint['goal'] ?? ''),
                ];
            }
        } catch (\Throwable) {
        }
        return $sprints;
    }

    // ── Issues ──

    public function searchIssues(string $siteUrl, string $email, string $token, string $jql, array $fields = ['*all'], int $maxResults = 100): array
    {
        $allIssues = [];
        $startAt = 0;
        $isLast = false;

        while (!$isLast) {
            $body = json_encode([
                'jql' => $jql,
                'startAt' => $startAt,
                'maxResults' => $maxResults,
                'fields' => $fields,
                'expand' => ['renderedFields', 'changelog'],
            ], JSON_UNESCAPED_UNICODE);

            try {
                $data = $this->request($siteUrl, $email, $token, '/rest/api/3/search', 'POST', null, $body);
            } catch (\Throwable $e) {
                throw new RuntimeException('JIRA_SEARCH_FAILED: ' . $e->getMessage(), 500);
            }

            foreach ($data['issues'] ?? [] as $issue) {
                $allIssues[] = $issue;
            }

            $total = (int)($data['total'] ?? 0);
            $startAt += count($data['issues'] ?? []);
            $isLast = ($startAt >= $total);
        }

        return $allIssues;
    }

    public function getIssue(string $siteUrl, string $email, string $token, string $issueKey): array
    {
        try {
            return $this->request($siteUrl, $email, $token, '/rest/api/3/issue/' . $issueKey, 'GET', ['expand' => 'renderedFields,changelog']);
        } catch (\Throwable $e) {
            throw new RuntimeException('JIRA_ISSUE_FAILED: ' . $e->getMessage(), 500);
        }
    }

    // ── Comments ──

    public function getIssueComments(string $siteUrl, string $email, string $token, string $issueKey): array
    {
        $comments = [];
        try {
            foreach ($this->paginate($siteUrl, $email, $token, '/rest/api/3/issue/' . $issueKey . '/comment', ['maxResults' => 100], 100) as $comment) {
                $comments[] = $comment;
            }
        } catch (\Throwable) {
        }
        return $comments;
    }

    // ── Worklogs ──

    public function getIssueWorklogs(string $siteUrl, string $email, string $token, string $issueKey): array
    {
        $worklogs = [];
        try {
            foreach ($this->paginate($siteUrl, $email, $token, '/rest/api/3/issue/' . $issueKey . '/worklog', ['maxResults' => 100], 100) as $wl) {
                $worklogs[] = $wl;
            }
        } catch (\Throwable) {
        }
        return $worklogs;
    }

    // ── Attachments ──

    public function downloadAttachment(string $siteUrl, string $email, string $token, string $contentUrl, string $targetPath): array
    {
        // Validate URL
        $allowedHost = parse_url($siteUrl, PHP_URL_HOST);
        $actualHost = parse_url($contentUrl, PHP_URL_HOST);
        if ($actualHost === false || $actualHost === null || $actualHost === '' || $actualHost !== $allowedHost) {
            return ['success' => false, 'error' => 'SSRF_BLOCKED: Download host mismatch'];
        }

        $fp = fopen($targetPath, 'w+b');
        if ($fp === false) {
            return ['success' => false, 'error' => 'Failed to open temp file'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $contentUrl,
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => $this->timeout * 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($email . ':' . $token),
                'User-Agent: TropaTT-Jira-Migration/1.0',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $size = curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);
        curl_close($ch);
        fclose($fp);

        if ($httpCode < 200 || $httpCode >= 300) {
            @unlink($targetPath);
            return ['success' => false, 'error' => "Download failed: HTTP $httpCode"];
        }

        return [
            'success' => true,
            'size' => (int)$size,
            'mime_type' => $contentType ?: 'application/octet-stream',
            'path' => $targetPath,
        ];
    }

    // ── Versions ──

    public function getProjectVersions(string $siteUrl, string $email, string $token, string $projectKey): array
    {
        $versions = [];
        try {
            foreach ($this->paginate($siteUrl, $email, $token, '/rest/api/3/project/' . $projectKey . '/version', ['maxResults' => 100], 100) as $version) {
                $versions[] = [
                    'id' => (string)($version['id'] ?? ''),
                    'name' => (string)($version['name'] ?? ''),
                    'description' => (string)($version['description'] ?? ''),
                    'released' => (bool)($version['released'] ?? false),
                    'release_date' => $version['releaseDate'] ?? null,
                ];
            }
        } catch (\Throwable) {
        }
        return $versions;
    }

    // ── Components ──

    public function getProjectComponents(string $siteUrl, string $email, string $token, string $projectKey): array
    {
        try {
            return $this->request($siteUrl, $email, $token, '/rest/api/3/project/' . $projectKey . '/component');
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Users ──

    public function searchUsers(string $siteUrl, string $email, string $token, string $query = '', int $maxResults = 50): array
    {
        try {
            return $this->request($siteUrl, $email, $token, '/rest/api/3/users/search', 'GET', ['maxResults' => $maxResults, 'query' => $query]);
        } catch (\Throwable) {
            return [];
        }
    }

    public function searchAllUsers(string $siteUrl, string $email, string $token): array
    {
        $all = [];
        try {
            $result = $this->request($siteUrl, $email, $token, '/rest/api/3/users/search', 'GET', ['maxResults' => 1000, 'startAt' => 0]);
            if (is_array($result)) {
                return $result;
            }
        } catch (\Throwable) {
        }
        return $all;
    }

    // ── Priorities ──

    public function getPriorities(string $siteUrl, string $email, string $token): array
    {
        try {
            $data = $this->request($siteUrl, $email, $token, '/rest/api/3/priority');
            $result = [];
            foreach ((array)$data as $p) {
                $result[] = [
                    'id' => (string)($p['id'] ?? ''),
                    'name' => (string)($p['name'] ?? ''),
                ];
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Issue Types ──

    public function getIssueTypes(string $siteUrl, string $email, string $token): array
    {
        try {
            $data = $this->request($siteUrl, $email, $token, '/rest/api/3/issuetype');
            $result = [];
            foreach ((array)$data as $it) {
                $result[] = [
                    'id' => (string)($it['id'] ?? ''),
                    'name' => (string)($it['name'] ?? ''),
                ];
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── URL Validation ──

    public static function isValidJiraUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null || $host === '') {
            return false;
        }

        $ip = gethostbyname($host);
        if ($ip !== $host) {
            if (filter_var($ip, FILTER_VALIDATE_IP) && self::isPrivateIp($ip)) {
                return false;
            }
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if ($scheme !== 'https') {
            return false;
        }

        return true;
    }

    public static function isPrivateIp(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) return false;
        if (($ipLong & 0xFF000000) === 0x0A000000) return true;
        if (($ipLong & 0xFFF00000) === 0xAC100000) return true;
        if (($ipLong & 0xFFFF0000) === 0xC0A80000) return true;
        if (($ipLong & 0xFF000000) === 0x7F000000) return true;
        return in_array($ip, ['::1', '0:0:0:0:0:0:0:1'], true);
    }
}
