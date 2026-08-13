<?php
declare(strict_types=1);

namespace Module\Crm\TrelloMigration\Service;

use Module\Crm\TrelloMigration\Repository\TrelloMigrationRepository;
use RuntimeException;

final class TrelloClient
{
    private const BASE_URL = 'https://api.trello.com/1';
    private int $timeout;
    private int $maxRetries;
    private ?int $connectionId = null;

    public function __construct(
        private readonly TrelloMigrationRepository $repo,
        int $timeout = 60,
        int $maxRetries = 4,
    ) {
        $this->timeout = max(5, $timeout);
        $this->maxRetries = max(1, $maxRetries);
    }

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    /** @return array<string,mixed> */
    public function test(string $apiKey, string $token): array
    {
        $me = $this->request($apiKey, $token, '/members/me', ['fields' => 'id,fullName,username,url']);
        return [
            'id' => $me['id'] ?? null,
            'full_name' => $me['fullName'] ?? null,
            'username' => $me['username'] ?? null,
            'url' => $me['url'] ?? null,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function boards(string $apiKey, string $token): array
    {
        return $this->paginate($apiKey, $token, '/members/me/boards', ['filter' => 'all', 'fields' => 'id,name,desc,closed,url,shortUrl,dateLastActivity,idOrganization'], 1000);
    }

    /** @return array<int,array<string,mixed>> */
    public function lists(string $apiKey, string $token, string $boardId): array
    {
        return $this->paginate($apiKey, $token, '/boards/' . rawurlencode($boardId) . '/lists', ['filter' => 'all', 'fields' => 'id,name,closed,pos,idBoard'], 1000);
    }

    /** @return array<int,array<string,mixed>> */
    public function cards(string $apiKey, string $token, string $boardId): array
    {
        return $this->paginate($apiKey, $token, '/boards/' . rawurlencode($boardId) . '/cards', ['filter' => 'all', 'fields' => 'id,idBoard,idList,name,desc,closed,dateLastActivity,due,start,dueComplete,idMembers,idLabels,pos,url,shortUrl,idShort'], 1000);
    }

    /** @return array<string,mixed> */
    public function card(string $apiKey, string $token, string $cardId): array
    {
        return $this->request($apiKey, $token, '/cards/' . rawurlencode($cardId), [
            'fields' => 'all', 'members' => 'true', 'member_fields' => 'id,fullName,username',
            'attachments' => 'true', 'attachment_fields' => 'id,name,url,bytes,date,edgeColor,mimeType,isUpload',
            'checklists' => 'all', 'checkItemStates' => 'true', 'customFieldItems' => 'true',
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function actions(string $apiKey, string $token, string $cardId, int $maxPages = 10): array
    {
        $items = [];
        $before = null;
        $seen = [];
        for ($page = 1; $page <= max(1, $maxPages); $page++) {
            $query = ['filter' => 'all', 'limit' => 1000];
            if ($before !== null) $query['before'] = $before;
            $batch = $this->request($apiKey, $token, '/cards/' . rawurlencode($cardId) . '/actions', $query);
            if (!array_is_list($batch) || $batch === []) break;
            foreach ($batch as $action) {
                $actionId = (string)($action['id'] ?? '');
                if ($actionId !== '' && isset($seen[$actionId])) continue;
                if ($actionId !== '') $seen[$actionId] = true;
                $items[] = $action;
            }
            if (count($batch) < 1000) break;
            $last = end($batch);
            $before = is_array($last) ? (string)($last['id'] ?? '') : '';
            if ($before === '') break;
        }
        return $items;
    }

    /** @return array<int,array<string,mixed>> */
    public function members(string $apiKey, string $token, string $boardId): array
    {
        return $this->paginate($apiKey, $token, '/boards/' . rawurlencode($boardId) . '/members', ['filter' => 'all', 'fields' => 'id,fullName,username,initials,activityBlocked'], 1000);
    }

    /** @return array<int,array<string,mixed>> */
    public function labels(string $apiKey, string $token, string $boardId): array
    {
        return $this->paginate($apiKey, $token, '/boards/' . rawurlencode($boardId) . '/labels', ['fields' => 'id,idBoard,name,color'], 1000);
    }

    /** @return array<int,array<string,mixed>> */
    public function customFields(string $apiKey, string $token, string $boardId): array
    {
        return $this->paginate($apiKey, $token, '/boards/' . rawurlencode($boardId) . '/customFields', ['fields' => 'all'], 1000);
    }

    /** @return array<int,mixed> */
    public function batch(string $apiKey, string $token, array $paths): array
    {
        $paths = array_slice(array_values(array_filter(array_map('strval', $paths))), 0, 10);
        if ($paths === []) return [];
        $data = $this->request($apiKey, $token, '/batch', ['urls' => implode(',', $paths)]);
        return array_is_list($data) ? $data : [];
    }

    /** @return array<string,mixed> */
    public function webhook(string $apiKey, string $token, string $modelId, string $callbackUrl, string $description): array
    {
        return $this->request($apiKey, $token, '/tokens/' . rawurlencode($token) . '/webhooks', [
            'callbackURL' => $callbackUrl, 'idModel' => $modelId, 'description' => $description,
        ], 'POST');
    }

    public function deleteWebhook(string $apiKey, string $token, string $trelloWebhookId): bool
    {
        $this->request($apiKey, $token, '/webhooks/' . rawurlencode($trelloWebhookId), [], 'DELETE');
        return true;
    }

    public function downloadAttachment(string $apiKey, string $token, string $url, int $maxBytes, int $redirects = 0): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || trim((string)($parts['host'] ?? '')) === '') {
            throw new RuntimeException('TRELLO_ATTACHMENT_URL_INVALID');
        }
        $host = strtolower((string)$parts['host']);
        if (!$this->isAllowedAttachmentHost($host)) {
            throw new RuntimeException('TRELLO_ATTACHMENT_HOST_NOT_ALLOWED');
        }
        if ($this->isPrivateHost($host)) {
            throw new RuntimeException('TRELLO_ATTACHMENT_SSRF_BLOCKED');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'trello-');
        if ($tmp === false) throw new RuntimeException('TRELLO_ATTACHMENT_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('TRELLO_ATTACHMENT_TEMP_FAILED'); }
        $headers = [];
        $written = 0;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($fp, &$written, $maxBytes): int {
                $length = strlen($chunk);
                if ($written + $length > $maxBytes) {
                    return 0;
                }
                $written += $length;
                return fwrite($fp, $chunk) ?: 0;
            },
            CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: */*', 'User-Agent: TropaTT-Trello-Migration/1.0'],
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                if (str_contains($line, ':')) {
                    [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
                    $headers[strtolower(trim($name))] = trim($value);
                }
                return $length;
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);
        $size = is_file($tmp) ? (int)filesize($tmp) : 0;
        if ($ok !== false && in_array($code, [301, 302, 303, 307, 308], true) && !empty($headers['location'])) {
            @unlink($tmp);
            if ($redirects >= 3) throw new RuntimeException('TRELLO_ATTACHMENT_TOO_MANY_REDIRECTS');
            $location = trim((string)$headers['location']);
            if (str_starts_with($location, '/')) {
                $location = 'https://' . $host . $location;
            } elseif (str_starts_with($location, '//')) {
                $location = 'https:' . $location;
            }
            return $this->downloadAttachment($apiKey, $token, $location, $maxBytes, $redirects + 1);
        }
        if ($ok === false || $code < 200 || $code >= 300 || $size > $maxBytes || $written > $maxBytes) {
            @unlink($tmp);
            throw new RuntimeException($size > $maxBytes ? 'TRELLO_ATTACHMENT_TOO_LARGE' : 'TRELLO_ATTACHMENT_DOWNLOAD_FAILED');
        }
        return ['path' => $tmp, 'size' => $size, 'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream')];
    }

    /** @return array<string,mixed> */
    private function request(string $apiKey, string $token, string $path, array $query = [], string $method = 'GET'): array
    {
        $state = $this->repo->rateState((int)$this->connectionId);
        if ($state && !empty($state['retry_after_until']) && strtotime((string)$state['retry_after_until']) > time()) {
            sleep(max(1, strtotime((string)$state['retry_after_until']) - time()));
        }
        $query['key'] = $apiKey;
        $query['token'] = $token;
        $url = self::BASE_URL . $path . '?' . http_build_query($query);
        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            $responseHeaders = [];
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $this->timeout, CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: TropaTT-Trello-Migration/1.0'],
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$responseHeaders): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) {
                        [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
                        $responseHeaders[strtolower(trim($name))] = trim($value);
                    }
                    return $length;
                },
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $this->storeHeaders($responseHeaders);
            $decoded = is_string($body) ? json_decode($body, true) : null;
            if ($code === 429) {
                $delay = isset($responseHeaders['retry-after']) ? max(1, (int)$responseHeaders['retry-after']) : min(60, 2 ** $attempt);
                $this->storeRetry($delay);
                if ($attempt < $this->maxRetries) { sleep($delay); continue; }
                throw new RuntimeException('TRELLO_RATE_LIMITED', 429);
            }
            if ($code === 401) throw new RuntimeException('TRELLO_AUTH_FAILED', 401);
            if ($code === 403) throw new RuntimeException('TRELLO_FORBIDDEN', 403);
            if ($code === 404) throw new RuntimeException('TRELLO_NOT_FOUND', 404);
            if ($code < 200 || $code >= 300) throw new RuntimeException('TRELLO_HTTP_' . $code, $code);
            return is_array($decoded) ? $decoded : [];
        }
        throw new RuntimeException('TRELLO_REQUEST_FAILED');
    }

    /** @return array<int,array<string,mixed>> */
    private function paginate(string $key, string $token, string $path, array $query, int $limit): array
    {
        // Trello's structural collection endpoints return the complete
        // collection; they do not implement the page parameter. Sending page
        // repeatedly can return the same 1,000 records and create a runaway
        // migration. Labels are the exception and accept limit, so retain it
        // only for that endpoint.
        if (str_ends_with($path, '/labels')) {
            $query['limit'] = min(1000, max(1, $limit));
        } else {
            unset($query['limit'], $query['page']);
        }
        $items = $this->request($key, $token, $path, $query);
        return array_is_list($items) ? $items : [];
    }

    private function storeHeaders(array $headers): void
    {
        if ($this->connectionId === null) return;
        $state = $this->repo->rateState($this->connectionId) ?: [];
        $this->repo->updateRateState($this->connectionId, [
            'requests_made' => (int)($state['requests_made'] ?? 0) + 1,
            'window_started_at' => $state['window_started_at'] ?? gmdate('Y-m-d H:i:s'),
            'token_remaining' => isset($headers['x-rate-limit-api-token-remaining']) ? (int)$headers['x-rate-limit-api-token-remaining'] : ($state['token_remaining'] ?? null),
            'key_remaining' => isset($headers['x-rate-limit-api-key-remaining']) ? (int)$headers['x-rate-limit-api-key-remaining'] : ($state['key_remaining'] ?? null),
            'retry_after_until' => $state['retry_after_until'] ?? null,
        ]);
    }

    private function storeRetry(int $seconds): void
    {
        if ($this->connectionId === null) return;
        $state = $this->repo->rateState($this->connectionId) ?: [];
        $this->repo->updateRateState($this->connectionId, array_merge($state, ['retry_after_until' => gmdate('Y-m-d H:i:s', time() + $seconds)]));
    }

    private function isAllowedAttachmentHost(string $host): bool
    {
        return $host === 'trello.com'
            || str_ends_with($host, '.trello.com')
            || $host === 'trello-attachments.s3.amazonaws.com'
            || str_ends_with($host, '.trello-attachments.s3.amazonaws.com');
    }

    private function isPrivateHost(string $host): bool
    {
        $normalized = strtolower(trim($host, '[]'));
        if ($normalized === 'localhost' || str_ends_with($normalized, '.localhost') || str_ends_with($normalized, '.local')) {
            return true;
        }

        $addresses = [];
        $ip = filter_var($normalized, FILTER_VALIDATE_IP);
        if ($ip !== false) {
            $addresses[] = $ip;
        } else {
            // Resolve hostnames before downloading. Rejecting private and
            // reserved answers prevents a user-controlled Trello attachment
            // URL from becoming an SSRF primitive, including DNS rebinding
            // to RFC1918/link-local addresses.
            $resolved = gethostbynamel($normalized);
            if (is_array($resolved)) {
                $addresses = $resolved;
            }
        }

        if ($addresses === []) return true;
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }
        return false;
    }
}
