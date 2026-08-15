<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration\Service;

use RuntimeException;

final class GitHubClient
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
                throw new RuntimeException('GITHUB_TRANSPORT: curl_init failed');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/vnd.github+json',
                    'User-Agent: TropaTT-GitHub-Integration/1.0',
                    'X-GitHub-Api-Version: 2022-11-28',
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
                throw new RuntimeException('GITHUB_AUTH_FAILED: invalid token', 401);
            }
            if ($httpCode === 403 || $httpCode === 429) {
                if ($attempt < $this->maxRetries) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException('GITHUB_RATE_LIMITED', 429);
            }
            if ($httpCode === 404) {
                throw new RuntimeException('GITHUB_NOT_FOUND', 404);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('GITHUB_ERROR: HTTP ' . $httpCode, $httpCode);
            }
            if ($curlError !== '') {
                throw new RuntimeException('GITHUB_TRANSPORT: ' . $curlError, 0);
            }

            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('GITHUB_RATE_LIMITED: max retries reached', 429);
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
     * @return array{success: bool, message: string, login: string|null}
     */
    public function testConnection(string $token, string $baseUrl): array
    {
        try {
            $data = $this->request($token, $baseUrl, 'GET', '/user');
            return ['success' => true, 'message' => 'Connection successful', 'login' => (string)($data['login'] ?? '')];
        } catch (\Throwable $e) {
            error_log('[GitHubClient::testConnection] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Connection test failed. Check the token.', 'login' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRepos(string $token, string $baseUrl): array
    {
        return $this->listAll($token, $baseUrl, '/user/repos', ['affiliation' => 'owner,collaborator,organization_member', 'sort' => 'updated']);
    }

    /**
     * List issues + pull requests for a repository.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listIssues(string $token, string $baseUrl, string $owner, string $repo): array
    {
        return $this->listAll($token, $baseUrl, '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues', ['state' => 'all']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssueComments(string $token, string $baseUrl, string $owner, string $repo, int $number): array
    {
        return $this->listAll($token, $baseUrl, '/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/issues/' . $number . '/comments');
    }
}
