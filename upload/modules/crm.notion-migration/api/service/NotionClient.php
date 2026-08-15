<?php
declare(strict_types=1);

namespace Module\Crm\NotionMigration\Service;

use RuntimeException;

/**
 * Низкоуровневый HTTP-клиент Notion API.
 *
 * Base URL фиксирован (https://api.notion.com/v1) — пользовательский ввод не участвует
 * в построении URL, поэтому SSRF-поверхность отсутствует.
 */
final class NotionClient
{
    public const BASE_URL = 'https://api.notion.com/v1';
    public const NOTION_VERSION = '2022-06-28';

    private int $timeout;
    private int $maxRetries;

    public function __construct(int $timeout = 30, int $maxRetries = 4)
    {
        $this->timeout = $timeout;
        $this->maxRetries = $maxRetries;
    }

    private function request(string $token, string $method, string $path, ?array $query = null, ?array $body = null): array
    {
        $url = self::BASE_URL . $path;
        if ($query !== null && $query !== []) {
            $url .= '?' . http_build_query($query);
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $responseHeaders = [];
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Notion-Version: ' . self::NOTION_VERSION,
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'User-Agent: TropaTT-Notion-Migration/1.0',
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

            if ($method === 'POST' || $method === 'PATCH') {
                $payload = $body === null || $body === [] ? new \stdClass() : $body;
                if ($method === 'POST') {
                    curl_setopt($ch, CURLOPT_POST, true);
                } else {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
            }

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === '') {
                $raw = '{}';
            }

            if ($httpCode === 429) {
                $retryAfter = isset($responseHeaders['retry-after']) ? max(1, (int)$responseHeaders['retry-after']) : (5 * $attempt);
                if ($attempt < $this->maxRetries) {
                    sleep($retryAfter);
                    continue;
                }
                throw new RuntimeException('NOTION_RATE_LIMITED: exhausted retries', 429);
            }

            if ($httpCode === 401) {
                throw new RuntimeException('NOTION_AUTH_FAILED: invalid integration token', 401);
            }

            if ($httpCode === 403) {
                throw new RuntimeException('NOTION_FORBIDDEN: integration lacks access to this object', 403);
            }

            if ($httpCode === 404) {
                throw new RuntimeException('NOTION_NOT_FOUND: object not found or not shared with the integration', 404);
            }

            if ($httpCode < 200 || $httpCode >= 300) {
                $snippet = mb_substr((string)$raw, 0, 300);
                throw new RuntimeException('NOTION_ERROR: HTTP ' . $httpCode . ': ' . $snippet, $httpCode);
            }

            if ($curlError !== '') {
                throw new RuntimeException('NOTION_TRANSPORT: ' . $curlError, 0);
            }

            $decoded = json_decode((string)$raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        throw new RuntimeException('NOTION_RATE_LIMITED: max retries reached', 429);
    }

    private function paginateResults(array $data): array
    {
        return is_array($data['results'] ?? null) ? $data['results'] : [];
    }

    public function testConnection(string $token): array
    {
        try {
            $data = $this->request($token, 'GET', '/users', ['page_size' => 1]);
            $users = $this->paginateResults($data);
            $me = null;
            if ($users !== []) {
                $me = [
                    'id' => (string)($users[0]['id'] ?? ''),
                    'name' => (string)($users[0]['name'] ?? ''),
                    'email' => (string)($users[0]['person']['email'] ?? ''),
                ];
            }
            return ['success' => true, 'message' => 'Connection successful', 'user' => $me];
        } catch (\Throwable $e) {
            error_log('[NotionClient::testConnection] ' . $e->getMessage());
            return ['success' => false, 'message' => 'Connection test failed. Check server logs for details.'];
        }
    }

    public function listUsers(string $token): array
    {
        $users = [];
        $cursor = null;
        do {
            $query = ['page_size' => 100];
            if ($cursor !== null) {
                $query['start_cursor'] = $cursor;
            }
            $data = $this->request($token, 'GET', '/users', $query);
            foreach ($this->paginateResults($data) as $user) {
                $users[] = [
                    'id' => (string)($user['id'] ?? ''),
                    'name' => (string)($user['name'] ?? ''),
                    'email' => (string)($user['person']['email'] ?? ''),
                    'type' => (string)($user['type'] ?? 'person'),
                ];
            }
            $cursor = $data['has_more'] ? (string)($data['next_cursor'] ?? '') : null;
        } while ($cursor !== null && $cursor !== '');

        return $users;
    }

    /**
     * Поиск страниц и баз данных, доступных интеграции.
     */
    public function searchObjects(string $token, string $objectType = 'page', string $query = ''): array
    {
        $objects = [];
        $body = [
            'query' => $query,
            'filter' => ['value' => $objectType, 'property' => 'object'],
            'page_size' => 100,
        ];
        do {
            $data = $this->request($token, 'POST', '/search', null, $body);
            foreach ($this->paginateResults($data) as $item) {
                $objects[] = $item;
            }
            $next = $data['has_more'] ? (string)($data['next_cursor'] ?? '') : '';
            $body['start_cursor'] = $next !== '' ? $next : null;
        } while ($next !== '');

        return $objects;
    }

    public function getPage(string $token, string $pageId): array
    {
        return $this->request($token, 'GET', '/pages/' . rawurlencode($pageId));
    }

    public function getDatabase(string $token, string $databaseId): array
    {
        return $this->request($token, 'GET', '/databases/' . rawurlencode($databaseId));
    }

    public function queryDatabase(string $token, string $databaseId): array
    {
        $rows = [];
        $body = ['page_size' => 100];
        do {
            $data = $this->request($token, 'POST', '/databases/' . rawurlencode($databaseId) . '/query', null, $body);
            foreach ($this->paginateResults($data) as $item) {
                $rows[] = $item;
            }
            $next = $data['has_more'] ? (string)($data['next_cursor'] ?? '') : '';
            $body['start_cursor'] = $next !== '' ? $next : null;
        } while ($next !== '');

        return $rows;
    }

    public function getBlockChildren(string $token, string $blockId): array
    {
        $blocks = [];
        $query = ['page_size' => 100];
        do {
            $data = $this->request($token, 'GET', '/blocks/' . rawurlencode($blockId) . '/children', $query);
            foreach ($this->paginateResults($data) as $item) {
                $blocks[] = $item;
            }
            $next = $data['has_more'] ? (string)($data['next_cursor'] ?? '') : '';
            $query['start_cursor'] = $next !== '' ? $next : null;
        } while ($next !== '');

        return $blocks;
    }

    public function listComments(string $token, string $pageId): array
    {
        $comments = [];
        $query = ['block_id' => $pageId, 'page_size' => 100];
        do {
            $data = $this->request($token, 'GET', '/comments', $query);
            foreach ($this->paginateResults($data) as $item) {
                $comments[] = $item;
            }
            $next = $data['has_more'] ? (string)($data['next_cursor'] ?? '') : '';
            $query['start_cursor'] = $next !== '' ? $next : null;
        } while ($next !== '');

        return $comments;
    }

    /**
     * Рекурсивно собирает все блоки страницы, включая дочерние (toggle, table, column_list и т.д.).
     *
     * @return array<int, array{id: string, type: string, data: array, children: array}>
     */
    public function fetchBlockTree(string $token, string $pageId, int $maxDepth = 20): array
    {
        return $this->walkBlocks($token, $pageId, $maxDepth, 0);
    }

    private function walkBlocks(string $token, string $blockId, int $maxDepth, int $depth): array
    {
        if ($depth > $maxDepth) {
            return [];
        }
        $result = [];
        try {
            $blocks = $this->getBlockChildren($token, $blockId);
        } catch (\Throwable $e) {
            error_log('[NotionClient::walkBlocks] Failed to fetch children of ' . $blockId . ': ' . $e->getMessage());
            return [];
        }

        foreach ($blocks as $block) {
            $id = (string)($block['id'] ?? '');
            $type = (string)($block['type'] ?? '');
            $hasChildren = (bool)($block['has_children'] ?? false);
            $children = $hasChildren ? $this->walkBlocks($token, $id, $maxDepth, $depth + 1) : [];
            $result[] = [
                'id' => $id,
                'type' => $type,
                'data' => $block[$type] ?? [],
                'children' => $children,
                'has_children' => $hasChildren,
            ];
        }

        return $result;
    }
}
