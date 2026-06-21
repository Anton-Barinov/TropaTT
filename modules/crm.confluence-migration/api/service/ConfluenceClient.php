<?php
declare(strict_types=1);

namespace Module\Crm\ConfluenceMigration\Service;

use Module\Crm\ConfluenceMigration\Repository\ConfluenceMigrationRepository;
use RuntimeException;

final class ConfluenceClient
{
    private const MAX_REQUESTS_PER_WINDOW = 100;
    private const WINDOW_SECONDS = 60;

    private int $timeout;
    private int $maxRetries;
    private ?ConfluenceMigrationRepository $repo = null;
    private ?int $connectionId = null;

    public function __construct(int $timeout = 30, int $maxRetries = 3, ?ConfluenceMigrationRepository $repo = null)
    {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
        $this->repo = $repo;
    }

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    private function request(string $baseUrl, string $email, string $token, string $path, string $method = 'GET', ?array $query = null): array
    {
        $url = rtrim($baseUrl, '/') . $path;
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
                    'User-Agent: TropaTT-Confluence-Migration/1.0',
                ],
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HEADERFUNCTION => function ($curl, $headerLine) use (&$responseHeaders) {
                    $len = strlen($headerLine);
                    if (stripos($headerLine, 'Retry-After:') === 0) {
                        $responseHeaders['retry-after'] = (int)trim(substr($headerLine, 12));
                    } elseif (stripos($headerLine, 'X-RateLimit-Remaining:') === 0) {
                        $responseHeaders['rate-remaining'] = (int)trim(substr($headerLine, 21));
                    }
                    return $len;
                },
            ]);

            if ($method === 'HEAD') {
                curl_setopt($ch, CURLOPT_NOBODY, true);
            }

            $body = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($body === false || $body === '') {
                $body = '{}';
            }

            // Track request for rate limiting
            $this->trackRequest();

            if ($httpCode === 429) {
                $retryAfter = isset($responseHeaders['retry-after']) ? max(1, (int)$responseHeaders['retry-after']) : (5 * $attempt);
                $this->storeRateLimitRetry($retryAfter);

                if ($attempt < $this->maxRetries) {
                    fwrite(STDERR, "Rate limited (429), retrying in {$retryAfter}s (attempt {$attempt}/{$this->maxRetries})...\n");
                    sleep($retryAfter);
                    continue;
                }

                throw new RuntimeException("CONFLUENCE_RATE_LIMITED: Exhausted {$this->maxRetries} retries", 429);
            }

            if ($httpCode === 401) {
                throw new RuntimeException('CONFLUENCE_AUTH_FAILED: Invalid email or API token', 401);
            }

            if ($httpCode === 403) {
                throw new RuntimeException('CONFLUENCE_FORBIDDEN: Account lacks permissions', 403);
            }

            if ($httpCode === 404) {
                throw new RuntimeException('CONFLUENCE_NOT_FOUND: Resource not found', 404);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException("CONFLUENCE_ERROR: HTTP $httpCode: " . mb_substr($body, 0, 200), $httpCode);
            }

            // Success - reset rate limit window if we were close
            $this->trackRequestSuccess();

            $decoded = json_decode($body, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('CONFLUENCE_RATE_LIMITED: Max retries reached', 429);
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

        // Check retry-after
        if (!empty($rateLimit['retry_after_until'])) {
            $retryUntil = strtotime((string)$rateLimit['retry_after_until']);
            if ($retryUntil > time()) {
                $sleep = $retryUntil - time() + 1;
                fwrite(STDERR, "Rate limited, sleeping {$sleep}s...\n");
                sleep($sleep);
            }
        }

        // Check window-based limit
        if (!empty($rateLimit['window_started_at'])) {
            $windowStart = strtotime((string)$rateLimit['window_started_at']);
            $requestsMade = (int)($rateLimit['requests_made'] ?? 0);
            $elapsed = time() - $windowStart;

            if ($elapsed < self::WINDOW_SECONDS && $requestsMade >= self::MAX_REQUESTS_PER_WINDOW) {
                $sleep = self::WINDOW_SECONDS - $elapsed + 1;
                fwrite(STDERR, "Request window exhausted, sleeping {$sleep}s...\n");
                sleep($sleep);
            }
        }
    }

    private function trackRequest(): void
    {
        if ($this->repo === null || $this->connectionId === null) {
            return;
        }
        // Increment request counter (or reset window if expired)
        $rateLimit = $this->repo->getRateLimit($this->connectionId);
        if ($rateLimit) {
            $windowStart = !empty($rateLimit['window_started_at']) ? strtotime((string)$rateLimit['window_started_at']) : 0;
            $reset = (time() - $windowStart) >= self::WINDOW_SECONDS;
            $this->repo->updateRateLimitAfterRequest($this->connectionId, $reset);
        }
    }

    private function trackRequestSuccess(): void
    {
        // No-op: success tracking is handled by trackRequest
    }

    private function storeRateLimitRetry(int $seconds): void
    {
        if ($this->repo === null || $this->connectionId === null) {
            return;
        }
        $retryUntil = gmdate('Y-m-d H:i:s', time() + $seconds);
        $this->repo->updateRateLimitAfterRequest($this->connectionId, false, $retryUntil);
    }

    private function paginate(string $baseUrl, string $email, string $token, string $path, array $query = [], int $limit = 50): iterable
    {
        $query['limit'] = min($limit, 100);
        $cursor = null;

        while (true) {
            $params = $query;
            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $data = $this->request($baseUrl, $email, $token, $path, 'GET', $params);
            foreach (($data['results'] ?? []) as $item) {
                yield $item;
            }

            if (!empty($data['_links']['next'])) {
                // Extract cursor from next link
                $nextUrl = (string)$data['_links']['next'];
                parse_str(parse_url($nextUrl, PHP_URL_QUERY) ?? '', $nextParams);
                $cursor = $nextParams['cursor'] ?? null;
                if ($cursor === null) {
                    break;
                }
            } else {
                break;
            }
        }
    }

    // ── Connection test ──

    public function testConnection(string $baseUrl, string $email, string $token): array
    {
        try {
            $data = $this->request($baseUrl, $email, $token, '/wiki/api/v2/spaces', 'GET', ['limit' => 1]);
            $spacesCount = $data['size'] ?? 0;
            $user = null;

            // Try to get current user info via v1 API
            try {
                $userData = $this->request($baseUrl, $email, $token, '/wiki/rest/api/user/current', 'GET');
                $user = [
                    'account_id' => $userData['accountId'] ?? null,
                    'display_name' => $userData['displayName'] ?? null,
                    'email' => $userData['email'] ?? null,
                ];
            } catch (\Throwable) {
            }

            return [
                'success' => true,
                'message' => 'Connection successful',
                'spaces_count' => $spacesCount,
                'user' => $user,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    // ── Spaces ──

    public function getSpaces(string $baseUrl, string $email, string $token, ?array $keys = null, bool $includeArchived = false): array
    {
        $query = ['limit' => 100];
        if ($includeArchived) {
            $query['status'] = 'current,archived';
        }
        $spaces = [];
        foreach ($this->paginate($baseUrl, $email, $token, '/wiki/api/v2/spaces', $query, 100) as $space) {
            if ($keys !== null && !in_array($space['key'], $keys, true)) {
                continue;
            }
            $spaces[] = [
                'id' => (string)$space['id'],
                'key' => (string)$space['key'],
                'name' => (string)($space['name'] ?? ''),
                'description' => (string)($space['description']['plain']['value'] ?? $space['description'] ?? ''),
                'status' => (string)($space['status'] ?? 'current'),
                '_links' => $space['_links'] ?? [],
            ];
        }
        return $spaces;
    }

    // ── Pages ──

    public function getPagesForSpace(string $baseUrl, string $email, string $token, string $spaceId, int $limit = 0, int $sampleLimit = 100): array
    {
        $query = ['limit' => min($sampleLimit, 100)];
        $pages = [];
        $totalCount = 0;
        foreach ($this->paginate($baseUrl, $email, $token, "/wiki/api/v2/spaces/{$spaceId}/pages", $query, $sampleLimit) as $page) {
            $totalCount++;
            if ($limit > 0 && $totalCount > $limit) {
                break;
            }
            $pages[] = [
                'id' => (string)$page['id'],
                'title' => (string)($page['title'] ?? ''),
                'spaceId' => $spaceId,
                'parentId' => isset($page['parentId']) ? (string)$page['parentId'] : null,
                'status' => (string)($page['status'] ?? 'current'),
                'version' => (int)($page['version']['number'] ?? 1),
                'createdAt' => $page['createdAt'] ?? null,
                'updatedAt' => $page['version']['createdAt'] ?? $page['updatedAt'] ?? null,
            ];
        }
        return ['pages' => $pages, 'totalCount' => $totalCount];
    }

    public function getAllPagesForSpace(string $baseUrl, string $email, string $token, string $spaceId): array
    {
        $query = ['limit' => 100];
        $pages = [];
        foreach ($this->paginate($baseUrl, $email, $token, "/wiki/api/v2/spaces/{$spaceId}/pages", $query, 100) as $page) {
            $pages[] = [
                'id' => (string)$page['id'],
                'title' => (string)($page['title'] ?? ''),
                'spaceId' => $spaceId,
                'parentId' => isset($page['parentId']) ? (string)$page['parentId'] : null,
                'status' => (string)($page['status'] ?? 'current'),
                'version' => (int)($page['version']['number'] ?? 1),
                'createdAt' => $page['createdAt'] ?? null,
                'updatedAt' => $page['version']['createdAt'] ?? $page['updatedAt'] ?? null,
            ];
        }
        return $pages;
    }

    public function getPage(string $baseUrl, string $email, string $token, string $pageId, string $bodyFormat = 'storage'): array
    {
        // Try v2 first
        try {
            $data = $this->request($baseUrl, $email, $token, "/wiki/api/v2/pages/{$pageId}", 'GET', ['body-format' => $bodyFormat]);
            return [
                'id' => (string)($data['id'] ?? $pageId),
                'title' => (string)($data['title'] ?? ''),
                'spaceId' => (string)($data['spaceId'] ?? ''),
                'parentId' => isset($data['parentId']) ? (string)$data['parentId'] : null,
                'status' => (string)($data['status'] ?? 'current'),
                'version' => (int)($data['version']['number'] ?? 1),
                'body' => $data['body'] ?? [],
                'createdAt' => $data['createdAt'] ?? null,
                'updatedAt' => $data['version']['createdAt'] ?? $data['updatedAt'] ?? null,
                'authorId' => $data['version']['authorId'] ?? null,
            ];
        } catch (\Throwable $e) {
            // Fallback to v1
            $data = $this->request($baseUrl, $email, $token, "/wiki/rest/api/content/{$pageId}", 'GET', ['expand' => 'body.storage,version,history,ancestors,metadata.labels,metadata.properties']);
            $body = $data['body']['storage'] ?? [];
            return [
                'id' => (string)($data['id'] ?? $pageId),
                'title' => (string)($data['title'] ?? ''),
                'spaceId' => (string)(isset($data['space']['id']) ? $data['space']['id'] : ''),
                'parentId' => isset($data['ancestors'][0]['id']) ? (string)$data['ancestors'][0]['id'] : null,
                'status' => (string)($data['status'] ?? 'current'),
                'version' => (int)($data['version']['number'] ?? 1),
                'body' => [
                    'storage' => $body,
                    'view' => $data['body']['view'] ?? null,
                ],
                'createdAt' => $data['history']['createdDate'] ?? null,
                'updatedAt' => $data['version']['when'] ?? null,
                'authorId' => $data['history']['createdBy']['accountId'] ?? $data['version']['by']['accountId'] ?? null,
                'ancestors' => $data['ancestors'] ?? [],
                'metadata' => $data['metadata'] ?? [],
            ];
        }
    }

    // ── Attachments ──

    public function getPageAttachments(string $baseUrl, string $email, string $token, string $pageId): array
    {
        $attachments = [];
        try {
            foreach ($this->paginate($baseUrl, $email, $token, "/wiki/api/v2/pages/{$pageId}/attachments", ['limit' => 50], 50) as $item) {
                $attachments[] = [
                    'id' => (string)$item['id'],
                    'title' => (string)($item['title'] ?? ''),
                    'mediaType' => (string)($item['mediaType'] ?? 'application/octet-stream'),
                    'fileSize' => (int)($item['fileSize'] ?? 0),
                    'comment' => (string)($item['comment'] ?? ''),
                    'createdAt' => $item['createdAt'] ?? null,
                    'version' => (int)($item['version']['number'] ?? 1),
                    'downloadLink' => $item['_links']['download'] ?? null,
                ];
            }
        } catch (\Throwable) {
            // v1 fallback
            try {
                $data = $this->request($baseUrl, $email, $token, "/wiki/rest/api/content/{$pageId}/child/attachment", 'GET', ['expand' => 'version', 'limit' => 100]);
                foreach ($data['results'] ?? [] as $item) {
                    $attachments[] = [
                        'id' => (string)$item['id'],
                        'title' => (string)($item['title'] ?? ''),
                        'mediaType' => (string)($item['metadata']['mediaType'] ?? 'application/octet-stream'),
                        'fileSize' => (int)($item['extensions']['fileSize'] ?? 0),
                        'comment' => (string)($item['extensions']['comment'] ?? ''),
                        'createdAt' => $item['version']['when'] ?? null,
                        'version' => (int)($item['version']['number'] ?? 1),
                        'downloadLink' => $item['_links']['download'] ?? null,
                    ];
                }
            } catch (\Throwable) {
            }
        }
        return $attachments;
    }

    public function downloadAttachment(string $baseUrl, string $email, string $token, array $attachment, string $targetPath): array
    {
        $downloadPath = $attachment['downloadLink'] ?? $attachment['_links']['download'] ?? '';
        if ($downloadPath === '') {
            return ['success' => false, 'error' => 'No download link available'];
        }

        // Resolve relative download links against base URL
        if (str_starts_with($downloadPath, '/')) {
            $url = $baseUrl . $downloadPath;
        } elseif (str_starts_with($downloadPath, 'http://') || str_starts_with($downloadPath, 'https://')) {
            $url = $downloadPath;
        } else {
            $url = $baseUrl . '/wiki' . $downloadPath;
        }

        // Validate URL host matches base URL host (SSRF protection)
        $allowedHost = parse_url($baseUrl, PHP_URL_HOST);
        $actualHost = parse_url($url, PHP_URL_HOST);
        if ($actualHost === false || $actualHost === null || $actualHost === '' || $actualHost !== $allowedHost) {
            return ['success' => false, 'error' => 'SSRF_BLOCKED: Download host mismatch'];
        }

        $fp = fopen($targetPath, 'w+b');
        if ($fp === false) {
            return ['success' => false, 'error' => 'Failed to open temp file'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => $this->timeout * 2,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($email . ':' . $token),
                'User-Agent: TropaTT-Confluence-Migration/1.0',
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
            return ['success' => false, 'error' => "Download failed: HTTP $httpCode" . ($error ? " ($error)" : '')];
        }

        return [
            'success' => true,
            'size' => (int)$size,
            'mime_type' => $contentType ?: 'application/octet-stream',
            'path' => $targetPath,
        ];
    }

    public function downloadContent(string $baseUrl, string $email, string $token, string $url): array
    {
        $actualHost = parse_url($url, PHP_URL_HOST);
        $allowedHost = parse_url($baseUrl, PHP_URL_HOST);
        if ($actualHost === false || $actualHost === null || $actualHost === '' || $actualHost !== $allowedHost) {
            return ['success' => false, 'error' => 'SSRF_BLOCKED: URL host mismatch'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Basic ' . base64_encode($email . ':' . $token),
                'User-Agent: TropaTT-Confluence-Migration/1.0',
            ],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($httpCode < 200 || $httpCode >= 300 || $body === false) {
            return ['success' => false, 'error' => "Download failed: HTTP $httpCode" . ($error ? " ($error)" : '')];
        }

        return [
            'success' => true,
            'body' => $body,
            'mime_type' => $contentType ?: 'application/octet-stream',
        ];
    }

    // ── Blog posts ──

    public function getBlogPostsForSpace(string $baseUrl, string $email, string $token, string $spaceId, bool $v2Only = false): array
    {
        $posts = [];

        // Try v2 API first
        try {
            foreach ($this->paginate($baseUrl, $email, $token, "/wiki/api/v2/spaces/{$spaceId}/blogposts", ['limit' => 50], 50) as $post) {
                $posts[] = [
                    'id' => (string)$post['id'],
                    'title' => (string)($post['title'] ?? ''),
                    'spaceId' => $spaceId,
                    'status' => (string)($post['status'] ?? 'current'),
                    'version' => (int)($post['version']['number'] ?? 1),
                    'createdAt' => $post['createdAt'] ?? null,
                    'updatedAt' => $post['version']['createdAt'] ?? $post['updatedAt'] ?? null,
                    'authorId' => $post['version']['authorId'] ?? null,
                ];
            }
            return $posts;
        } catch (\Throwable) {
            if ($v2Only) return [];
        }

        // Fallback to v1
        try {
            $spaceKey = ''; // Need to extract from spaceId - use search instead
            foreach ($this->paginate($baseUrl, $email, $token, '/wiki/rest/api/content', ['type' => 'blogpost', 'spaceId' => $spaceId, 'limit' => 50, 'expand' => 'version,history'], 50) as $post) {
                $posts[] = [
                    'id' => (string)$post['id'],
                    'title' => (string)($post['title'] ?? ''),
                    'spaceId' => $spaceId,
                    'status' => (string)($post['status'] ?? 'current'),
                    'version' => (int)($post['version']['number'] ?? 1),
                    'createdAt' => $post['history']['createdDate'] ?? $post['version']['when'] ?? null,
                    'updatedAt' => $post['version']['when'] ?? null,
                    'authorId' => $post['history']['createdBy']['accountId'] ?? $post['version']['by']['accountId'] ?? null,
                ];
            }
        } catch (\Throwable) {
        }

        return $posts;
    }

    public function getBlogPost(string $baseUrl, string $email, string $token, string $postId): array
    {
        try {
            $data = $this->request($baseUrl, $email, $token, "/wiki/api/v2/blogposts/{$postId}", 'GET', ['body-format' => 'storage']);
            return [
                'id' => (string)($data['id'] ?? $postId),
                'title' => (string)($data['title'] ?? ''),
                'spaceId' => (string)($data['spaceId'] ?? ''),
                'status' => (string)($data['status'] ?? 'current'),
                'version' => (int)($data['version']['number'] ?? 1),
                'body' => $data['body'] ?? [],
                'createdAt' => $data['createdAt'] ?? null,
                'updatedAt' => $data['version']['createdAt'] ?? $data['updatedAt'] ?? null,
                'authorId' => $data['version']['authorId'] ?? null,
            ];
        } catch (\Throwable $e) {
            // Fallback to v1
            $data = $this->request($baseUrl, $email, $token, "/wiki/rest/api/content/{$postId}", 'GET', ['expand' => 'body.storage,version,history,metadata.labels,metadata.properties']);
            $body = $data['body']['storage'] ?? [];
            return [
                'id' => (string)($data['id'] ?? $postId),
                'title' => (string)($data['title'] ?? ''),
                'spaceId' => (string)(isset($data['space']['id']) ? $data['space']['id'] : ''),
                'status' => (string)($data['status'] ?? 'current'),
                'version' => (int)($data['version']['number'] ?? 1),
                'body' => [
                    'storage' => $body,
                    'view' => $data['body']['view'] ?? null,
                ],
                'createdAt' => $data['history']['createdDate'] ?? null,
                'updatedAt' => $data['version']['when'] ?? null,
                'authorId' => $data['history']['createdBy']['accountId'] ?? $data['version']['by']['accountId'] ?? null,
            ];
        }
    }

    // ── Labels ──

    public function getPageLabels(string $baseUrl, string $email, string $token, string $pageId): array
    {
        $labels = [];
        try {
            foreach ($this->paginate($baseUrl, $email, $token, "/wiki/api/v2/pages/{$pageId}/labels", ['limit' => 50], 50) as $item) {
                $labels[] = [
                    'id' => (string)$item['id'],
                    'name' => (string)($item['name'] ?? ''),
                    'prefix' => (string)($item['prefix'] ?? 'global'),
                ];
            }
        } catch (\Throwable) {
        }
        return $labels;
    }

    // ── Comments ──

    public function getPageComments(string $baseUrl, string $email, string $token, string $pageId): array
    {
        $comments = [];
        try {
            $data = $this->request($baseUrl, $email, $token, "/wiki/rest/api/content/{$pageId}/child/comment", 'GET', ['expand' => 'body.storage,version,history', 'limit' => 100]);
            foreach ($data['results'] ?? [] as $item) {
                $comments[] = [
                    'id' => (string)$item['id'],
                    'body' => $item['body']['storage']['value'] ?? $item['body']['view']['value'] ?? '',
                    'authorId' => $item['version']['by']['accountId'] ?? $item['history']['createdBy']['accountId'] ?? null,
                    'authorName' => $item['version']['by']['displayName'] ?? $item['history']['createdBy']['displayName'] ?? 'Unknown',
                    'createdAt' => $item['version']['when'] ?? $item['history']['createdDate'] ?? null,
                    'updatedAt' => $item['version']['when'] ?? null,
                    'parentId' => !empty($item['ancestors']) ? (string)$item['ancestors'][count($item['ancestors']) - 1]['id'] : null,
                ];
            }
        } catch (\Throwable) {
        }
        return $comments;
    }

    // ── Versions ──

    public function getPageVersions(string $baseUrl, string $email, string $token, string $pageId): array
    {
        $versions = [];
        try {
            $data = $this->request($baseUrl, $email, $token, "/wiki/rest/api/content/{$pageId}/version", 'GET', ['expand' => 'content.body.storage', 'limit' => 50]);
            foreach ($data['results'] ?? [] as $item) {
                $versions[] = [
                    'number' => (int)$item['number'],
                    'by' => $item['by']['displayName'] ?? 'Unknown',
                    'byAccountId' => $item['by']['accountId'] ?? null,
                    'when' => $item['when'] ?? null,
                    'message' => $item['message'] ?? '',
                    'body' => $item['content']['body']['storage']['value'] ?? null,
                ];
            }
        } catch (\Throwable) {
        }
        return $versions;
    }

    // ── Restrictions ──

    public function getPageRestrictions(string $baseUrl, string $email, string $token, string $pageId): array
    {
        try {
            $data = $this->request($baseUrl, $email, $token, "/wiki/rest/api/content/{$pageId}/restriction/byOperation", 'GET');
            $restrictions = [];
            foreach ($data['results'] ?? [] as $operation) {
                $operationKey = (string)($operation['operation'] ?? 'read');
                $users = [];
                foreach (($operation['restrictions']['user']['results'] ?? []) as $u) {
                    $users[] = [
                        'accountId' => (string)($u['accountId'] ?? ''),
                        'displayName' => (string)($u['displayName'] ?? ''),
                    ];
                }
                $groups = [];
                foreach (($operation['restrictions']['group']['results'] ?? []) as $g) {
                    $groups[] = [
                        'id' => (string)($g['id'] ?? ''),
                        'name' => (string)($g['name'] ?? ''),
                    ];
                }
                $restrictions[$operationKey] = ['users' => $users, 'groups' => $groups];
            }
            return $restrictions;
        } catch (\Throwable) {
            return [];
        }
    }

    // ── CQL Search ──

    public function searchByCql(string $baseUrl, string $email, string $token, string $cql, int $limit = 50): array
    {
        $results = [];
        try {
            $data = $this->request($baseUrl, $email, $token, '/wiki/rest/api/content/search', 'GET', ['cql' => $cql, 'limit' => $limit, 'expand' => 'version']);
            foreach ($data['results'] ?? [] as $item) {
                $results[] = [
                    'id' => (string)$item['id'],
                    'title' => (string)($item['title'] ?? ''),
                    'type' => (string)($item['type'] ?? 'page'),
                    'spaceId' => (string)($item['space']['id'] ?? ''),
                    'status' => (string)($item['status'] ?? 'current'),
                ];
            }
        } catch (\Throwable) {
        }
        return $results;
    }

    // ── URL Validation ──

    public static function isValidConfluenceUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === false || $host === null || $host === '') {
            return false;
        }

        // Block private IPs and localhost
        $ip = gethostbyname($host);
        if ($ip === $host) {
            // Could not resolve, still let it pass (will fail at connection time)
        } elseif (filter_var($ip, FILTER_VALIDATE_IP)) {
            if (self::isPrivateIp($ip)) {
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
        if ($ipLong === false) {
            return false;
        }
        // 10.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x0A000000) return true;
        // 172.16.0.0/12
        if (($ipLong & 0xFFF00000) === 0xAC100000) return true;
        // 192.168.0.0/16
        if (($ipLong & 0xFFFF0000) === 0xC0A80000) return true;
        // 127.0.0.0/8
        if (($ipLong & 0xFF000000) === 0x7F000000) return true;
        // ::1 (IPv6 localhost)
        return in_array($ip, ['::1', '0:0:0:0:0:0:0:1'], true);
    }
}
