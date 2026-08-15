<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration\Service;

use RuntimeException;

final class GitLabClient
{
    private int $timeout;
    private int $maxRetries;

    public function __construct(int $timeout = 30, int $maxRetries = 3)
    {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function request(string $token, string $baseUrl, string $method, string $path, array $query = []): array
    {
        $url = rtrim($baseUrl, '/') . $path;
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $ch = curl_init($url);
            if ($ch === false) {
                throw new RuntimeException('GITLAB_TRANSPORT: curl_init failed');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'PRIVATE-TOKEN: ' . $token,
                    'User-Agent: TropaTT-GitLab-Integration/1.0',
                ],
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
            }

            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === '') {
                $raw = '{}';
            }

            if ($httpCode === 401) {
                throw new RuntimeException('GITLAB_AUTH_FAILED: invalid token', 401);
            }
            if ($httpCode === 403 || $httpCode === 429) {
                if ($attempt < $this->maxRetries) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException('GITLAB_RATE_LIMITED', 429);
            }
            if ($httpCode === 404) {
                throw new RuntimeException('GITLAB_NOT_FOUND', 404);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('GITLAB_ERROR: HTTP ' . $httpCode, $httpCode);
            }
            if ($curlError !== '') {
                throw new RuntimeException('GITLAB_TRANSPORT: ' . $curlError, 0);
            }

            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('GITLAB_RATE_LIMITED: max retries reached', 429);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listAll(string $token, string $baseUrl, string $path, array $query = []): array
    {
        $items = [];
        $page = 1;
        do {
            $data = $this->request($token, $baseUrl, 'GET', $path, array_merge($query, ['page' => $page, 'per_page' => 100]));
            if (!is_array($data)) {
                break;
            }
            foreach ($data as $item) {
                if (is_array($item)) {
                    $items[] = $item;
                }
            }
            $page++;
        } while (count($data) >= 100 && $page <= 50);
        return $items;
    }

    /**
     * @return array{success: bool, message: string, username: string|null}
     */
    public function testConnection(string $token, string $baseUrl): array
    {
        try {
            $data = $this->request($token, $baseUrl, 'GET', '/user');
            return ['success' => true, 'message' => 'Connection successful', 'username' => (string)($data['username'] ?? '')];
        } catch (\Throwable $e) {
            error_log('[GitLabClient::testConnection] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Connection test failed. Check the token.', 'username' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjects(string $token, string $baseUrl): array
    {
        return $this->listAll($token, $baseUrl, '/projects', ['membership' => 'true', 'order_by' => 'last_activity_at']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMergeRequests(string $token, string $baseUrl, string $projectPath): array
    {
        return $this->listAll($token, $baseUrl, '/projects/' . rawurlencode($projectPath) . '/merge_requests', ['state' => 'all']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMergeRequestNotes(string $token, string $baseUrl, string $projectPath, int $iid): array
    {
        return $this->listAll($token, $baseUrl, '/projects/' . rawurlencode($projectPath) . '/merge_requests/' . $iid . '/notes');
    }
}
