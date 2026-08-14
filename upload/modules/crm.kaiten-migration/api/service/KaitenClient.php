<?php
declare(strict_types=1);

namespace Module\Crm\KaitenMigration\Service;

use Module\Crm\KaitenMigration\Repository\KaitenMigrationRepository;
use RuntimeException;

final class KaitenClient
{
    private ?int $connectionId = null;
    private string $baseUrl;

    public function __construct(
        private readonly KaitenMigrationRepository $repo,
        string $baseUrl = '',
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    public function setConnectionId(?int $connectionId): void { $this->connectionId = $connectionId; }
    public function setBaseUrl(string $baseUrl): void { $this->baseUrl = $this->normalizeBaseUrl($baseUrl); }

    /** @return array<string,mixed> */
    public function test(string $token): array
    {
        return $this->request($token, '/users/current');
    }

    /** @return array<int,array<string,mixed>> */
    public function spaces(string $token, bool $includeArchived = false): array
    {
        return $this->collection($token, '/spaces', ['archived' => $includeArchived ? 'true' : 'false']);
    }

    /** @return array<int,array<string,mixed>> */
    public function boards(string $token, string $spaceId, bool $includeArchived = false): array
    {
        return $this->collection($token, '/spaces/' . rawurlencode($spaceId) . '/boards', ['archived' => $includeArchived ? 'true' : 'false']);
    }

    /** @return array<int,array<string,mixed>> */
    public function columns(string $token, string $boardId): array
    {
        return $this->collection($token, '/boards/' . rawurlencode($boardId) . '/columns');
    }

    /** @return array<int,array<string,mixed>> */
    public function cards(string $token, array $filters = []): array
    {
        return $this->collection($token, '/cards', array_merge(['limit' => 100, 'broken_api' => 'false'], $filters));
    }

    /** Stream cards page by page so a large board does not accumulate in PHP memory. */
    public function eachCards(string $token, array $filters, callable $consumer): int
    {
        $query = array_merge(['limit' => 100, 'broken_api' => 'false'], $filters); $offset = 0; $count = 0; $seen = [];
        for ($pageNumber = 0; $pageNumber < 100000; $pageNumber++) {
            if (isset($seen[$offset])) throw new RuntimeException('KAITEN_PAGINATION_LOOP'); $seen[$offset] = true;
            $page = $this->request($token, '/cards', array_merge($query, ['limit' => 100, 'offset' => $offset])); $batch = $this->extractItems($page); if ($batch === []) break;
            foreach ($batch as $item) { if (!is_array($item)) continue; $count++; if ($consumer($item) === false) return $count - 1; }
            $next = $page['next'] ?? $page['pagination']['next'] ?? null;
            if ($next === null || $next === '') { if (count($batch) < 100) break; $nextOffset = $offset + count($batch); }
            elseif (is_numeric($next)) $nextOffset = (int)$next;
            elseif (is_array($next)) $nextOffset = (int)($next['offset'] ?? $next['start_position'] ?? ($offset + count($batch)));
            else { parse_str((string)(parse_url((string)$next, PHP_URL_QUERY) ?? ''), $nextQuery); $nextOffset = isset($nextQuery['offset']) ? (int)$nextQuery['offset'] : $offset + count($batch); }
            if ($nextOffset <= $offset) throw new RuntimeException('KAITEN_PAGINATION_LOOP'); $offset = $nextOffset;
        }
        return $count;
    }

    /** @return array<string,mixed> */
    public function card(string $token, string $cardId): array
    {
        return $this->request($token, '/cards/' . rawurlencode($cardId), ['broken_api' => 'false']);
    }

    /** @return array<int,array<string,mixed>> */
    public function comments(string $token, string $cardId): array
    {
        return $this->collection($token, '/cards/' . rawurlencode($cardId) . '/comments');
    }

    /** @return array<int,array<string,mixed>> */
    public function attachments(string $token, string $cardId): array
    {
        return $this->collection($token, '/cards/' . rawurlencode($cardId) . '/files');
    }

    /** @return array<int,array<string,mixed>> */
    public function tags(string $token): array
    {
        return $this->collection($token, '/tags');
    }

    /** @return array<int,array<string,mixed>> */
    public function users(string $token): array
    {
        return $this->collection($token, '/users', ['limit' => 100]);
    }

    /** @return array<int,array<string,mixed>> */
    public function customFields(string $token): array
    {
        return $this->collection($token, '/company/custom-properties');
    }

    /** @return array<int,array<string,mixed>> */
    public function history(string $token, string $cardId): array
    {
        return $this->collection($token, '/cards/' . rawurlencode($cardId) . '/location-history');
    }

    /** @return array<string,mixed> */
    public function downloadAttachment(string $token, string $url, int $maxBytes, int $redirects = 0): array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') throw new RuntimeException('KAITEN_ATTACHMENT_URL_INVALID');
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || !$this->isTenantHost($host) || $this->isPrivateHost($host)) throw new RuntimeException('KAITEN_ATTACHMENT_HOST_NOT_ALLOWED');
        $tmp = tempnam(sys_get_temp_dir(), 'kaiten-');
        if ($tmp === false) throw new RuntimeException('KAITEN_ATTACHMENT_TEMP_FAILED');
        $fp = fopen($tmp, 'wb');
        if ($fp === false) { @unlink($tmp); throw new RuntimeException('KAITEN_ATTACHMENT_TEMP_FAILED'); }
        $written = 0; $overflow = false; $headers = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use ($fp, &$written, &$overflow, $maxBytes): int {
                $length = strlen($chunk);
                if ($written + $length > $maxBytes) { $overflow = true; return 0; }
                $written += $length;
                return fwrite($fp, $chunk) ?: 0;
            },
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); }
                return $length;
            },
            CURLOPT_RETURNTRANSFER => false, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT => max(10, $this->timeout * 2), CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Accept: */*', 'Authorization: Bearer ' . $token, 'User-Agent: TropaTT-Kaiten-Migration/1.0'],
        ]);
        $ok = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP); curl_close($ch); fclose($fp);
        if ($primaryIp === '' || $this->isPrivateIp($primaryIp)) { @unlink($tmp); throw new RuntimeException('KAITEN_ATTACHMENT_SSRF_BLOCKED'); }
        if (in_array($code,[301,302,303,307,308],true)&&!empty($headers['location'])) { @unlink($tmp); if($redirects>=3)throw new RuntimeException('KAITEN_ATTACHMENT_REDIRECT_LIMIT');$location=trim((string)$headers['location']);if(str_starts_with($location,'//'))$location='https:'.$location;elseif(!preg_match('~^https?://~i',$location)){$origin='https://'.$host;$path=(string)($parts['path']??'/');$location=str_starts_with($location,'/')?$origin.$location:$origin.'/'.ltrim(dirname($path).'/'.$location,'/');}return$this->downloadAttachment($token,$location,$maxBytes,$redirects+1); }
        if ($ok === false || $code < 200 || $code >= 300 || $overflow || $written > $maxBytes) { @unlink($tmp); throw new RuntimeException($overflow ? 'KAITEN_ATTACHMENT_TOO_LARGE' : 'KAITEN_ATTACHMENT_DOWNLOAD_FAILED'); }
        return ['path' => $tmp, 'size' => $written, 'mime_type' => (string)($headers['content-type'] ?? 'application/octet-stream')];
    }

    /** @return array<int,array<string,mixed>> */
    private function collection(string $token, string $path, array $query = []): array
    {
        $items = []; $offset = 0; $limit = min(100, max(1, (int)($query['limit'] ?? 100))); $seenOffsets = [];
        unset($query['offset']);
        for ($pageNumber = 0; $pageNumber < 100000; $pageNumber++) {
            if (isset($seenOffsets[$offset])) throw new RuntimeException('KAITEN_PAGINATION_LOOP');
            $seenOffsets[$offset] = true;
            $page = $this->request($token, $path, array_merge($query, ['limit' => $limit, 'offset' => $offset]));
            $batch = $this->extractItems($page);
            foreach ($batch as $item) { if (is_array($item)) $items[] = $item; if (count($items) > 10000) throw new RuntimeException('KAITEN_COLLECTION_LIMIT_EXCEEDED'); }
            if ($batch === []) break;
            $next = $page['next'] ?? $page['pagination']['next'] ?? null;
            if ($next === null || $next === '') { if (count($batch) < $limit) break; $nextOffset = $offset + count($batch); }
            elseif (is_numeric($next)) $nextOffset = (int)$next;
            elseif (is_array($next)) $nextOffset = (int)($next['offset'] ?? $next['start_position'] ?? ($offset + count($batch)));
            else { parse_str((string)(parse_url((string)$next, PHP_URL_QUERY) ?? ''), $nextQuery); $nextOffset = isset($nextQuery['offset']) ? (int)$nextQuery['offset'] : $offset + count($batch); }
            if ($nextOffset <= $offset) throw new RuntimeException('KAITEN_PAGINATION_LOOP');
            $offset = $nextOffset;
        }
        return $items;
    }

    /** @return array<string,mixed> */
    private function request(string $token, string $path, array $query = []): array
    {
        if ($this->baseUrl === '') throw new RuntimeException('KAITEN_BASE_URL_REQUIRED');
        if ($this->connectionId !== null) {
            $state = $this->repo->rateState($this->connectionId); $until = strtotime((string)($state['retry_after_until'] ?? ''));
            if ($until > time()) sleep($until - time() + 1);
        }
        $url = $this->baseUrl . $path . ($query !== [] ? '?' . http_build_query($query) : '');
        for ($attempt = 1; $attempt <= max(1, $this->maxRetries); $attempt++) {
            $headers = []; $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => max(5, $this->timeout), CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json', 'User-Agent: TropaTT-Kaiten-Migration/1.0'], CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int { $length = strlen($line); if (str_contains($line, ':')) { [$name, $value] = array_pad(explode(':', $line, 2), 2, ''); $headers[strtolower(trim($name))] = trim($value); } return $length; }]);
            $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP); $error = curl_error($ch); curl_close($ch);
            if ($primaryIp !== '' && $this->isPrivateIp($primaryIp)) throw new RuntimeException('KAITEN_API_SSRF_BLOCKED');
            if ($this->connectionId !== null) $this->repo->recordRequest($this->connectionId, $code, $headers);
            if ($code === 429) { $delay = isset($headers['retry-after']) ? max(1, (int)$headers['retry-after']) : min(60, 2 ** $attempt); if ($this->connectionId !== null) $this->repo->recordRetryAfter($this->connectionId, $delay); if ($attempt < $this->maxRetries) { sleep($delay); continue; } throw new RuntimeException('KAITEN_RATE_LIMITED', 429); }
            if ($code === 401) throw new RuntimeException('KAITEN_AUTH_FAILED', 401);
            if ($code === 403) throw new RuntimeException('KAITEN_FORBIDDEN', 403);
            if ($code === 404) throw new RuntimeException('KAITEN_NOT_FOUND', 404);
            if ($body === false || $code < 200 || $code >= 300) { if ($attempt < $this->maxRetries && ($code === 0 || $code >= 500)) { sleep(min(30, 2 ** $attempt)); continue; } throw new RuntimeException('KAITEN_HTTP_' . $code . ($error !== '' ? ': ' . $error : ''), $code); }
            $decoded = json_decode((string)$body, true); if (!is_array($decoded)) throw new RuntimeException('KAITEN_INVALID_RESPONSE'); return $decoded;
        }
        throw new RuntimeException('KAITEN_REQUEST_FAILED');
    }

    /** @return array<int,mixed> */
    private function extractItems(array $response): array
    {
        if (array_is_list($response)) return $response;
        foreach (['data', 'items', 'results', 'spaces', 'boards', 'cards', 'comments', 'files', 'users', 'tags'] as $key) if (isset($response[$key]) && is_array($response[$key])) return $response[$key];
        return [];
    }

    private function normalizeBaseUrl(string $url): string
    {
        $url = trim($url); if ($url === '') return '';
        if (!preg_match('~^https://[^/]+(?:/api/(?:v1|latest))?/?$~i', $url)) throw new RuntimeException('KAITEN_BASE_URL_INVALID');
        $host = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
        if ($host === '' || $this->isPrivateHost($host)) throw new RuntimeException('KAITEN_BASE_URL_PRIVATE_HOST');
        return rtrim($url, '/') . (preg_match('~/api/(?:v1|latest)$~i', rtrim($url, '/')) ? '' : '/api/latest');
    }

    private function isTenantHost(string $host): bool
    {
        $baseHost = strtolower((string)(parse_url($this->baseUrl, PHP_URL_HOST) ?? ''));
        return $baseHost !== '' && ($host === $baseHost || $host === 'files.' . $baseHost || str_ends_with($host, '.' . $baseHost));
    }

    private function isPrivateHost(string $host): bool
    {
        if ($host === 'localhost' || str_ends_with($host, '.local')) return true;
        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : (gethostbynamel($host) ?: []);
        if ($ips === []) return true;
        foreach ($ips as $ip) if ($this->isPrivateIp((string)$ip)) return true;
        return false;
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
