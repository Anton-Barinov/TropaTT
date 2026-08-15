<?php
declare(strict_types=1);

namespace Module\Crm\LinearMigration\Service;

use RuntimeException;

/**
 * Низкоуровневый клиент Linear GraphQL API.
 *
 * Base URL фиксирован (https://api.linear.app/graphql) — пользовательский ввод
 * не участвует в построении URL, SSRF-поверхность отсутствует.
 */
final class LinearClient
{
    public const BASE_URL = 'https://api.linear.app/graphql';

    private int $timeout;
    private int $maxRetries;

    public function __construct(int $timeout = 30, int $maxRetries = 3)
    {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function query(string $apiKey, string $query, array $variables = []): array
    {
        $body = json_encode(['query' => $query, 'variables' => (object)$variables], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('LINEAR_QUERY_ENCODE_FAILED');
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $ch = curl_init(self::BASE_URL);
            if ($ch === false) {
                throw new RuntimeException('LINEAR_TRANSPORT: curl_init failed');
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . $apiKey,
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'User-Agent: TropaTT-Linear-Migration/1.0',
                ],
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $raw = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === '') {
                $raw = '{}';
            }

            $decoded = json_decode((string)$raw, true);
            $decoded = is_array($decoded) ? $decoded : [];

            if ($httpCode === 401) {
                throw new RuntimeException('LINEAR_AUTH_FAILED: invalid API key', 401);
            }
            if ($httpCode === 429) {
                if ($attempt < $this->maxRetries) {
                    sleep(5 * $attempt);
                    continue;
                }
                throw new RuntimeException('LINEAR_RATE_LIMITED', 429);
            }
            if ($httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('LINEAR_ERROR: HTTP ' . $httpCode, $httpCode);
            }
            if ($curlError !== '') {
                throw new RuntimeException('LINEAR_TRANSPORT: ' . $curlError, 0);
            }
            if (isset($decoded['errors']) && is_array($decoded['errors']) && $decoded['errors'] !== []) {
                $msg = (string)($decoded['errors'][0]['message'] ?? 'GraphQL error');
                throw new RuntimeException('LINEAR_GRAPHQL: ' . mb_substr($msg, 0, 300));
            }

            return $decoded['data'] ?? [];
        }

        throw new RuntimeException('LINEAR_RATE_LIMITED: max retries reached', 429);
    }

    /**
     * @return array{success: bool, message: string, user: array<string, mixed>|null}
     */
    public function testConnection(string $apiKey): array
    {
        try {
            $data = $this->query($apiKey, 'query { viewer { id name email } }');
            $viewer = $data['viewer'] ?? null;
            return [
                'success' => true,
                'message' => 'Connection successful',
                'user' => is_array($viewer) ? $viewer : null,
            ];
        } catch (\Throwable $e) {
            error_log('[LinearClient::testConnection] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Connection test failed. Check the API key.', 'user' => null];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listTeams(string $apiKey): array
    {
        $data = $this->query($apiKey, 'query { teams { nodes { id name key } } }');
        return $data['teams']['nodes'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProjects(string $apiKey): array
    {
        $data = $this->query($apiKey, 'query { projects { nodes { id name description } } }');
        return $data['projects']['nodes'] ?? [];
    }

    /**
     * @param array<int, string> $teamIds
     * @return array<int, array<string, mixed>>
     */
    public function listIssues(string $apiKey, array $teamIds): array
    {
        $query = <<<'GQL'
query Issues($filter: IssueFilter) {
  issues(filter: $filter) {
    nodes {
      id
      identifier
      title
      description
      priority
      priorityLabel
      dueDate
      state { name type }
      assignee { id name email }
      project { id name }
      parent { id }
      labels { nodes { id name color } }
      createdAt
      updatedAt
    }
  }
}
GQL;

        $filter = ['team' => ['id' => ['in' => array_values($teamIds)]]];
        $data = $this->query($apiKey, $query, ['filter' => $filter]);
        return $data['issues']['nodes'] ?? [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listIssueComments(string $apiKey, string $issueId): array
    {
        $query = <<<'GQL'
query Comments($issueId: String!) {
  issue(id: $issueId) {
    comments { nodes { id body user { id name email } createdAt } }
  }
}
GQL;
        $data = $this->query($apiKey, $query, ['issueId' => $issueId]);
        return $data['issue']['comments']['nodes'] ?? [];
    }
}
