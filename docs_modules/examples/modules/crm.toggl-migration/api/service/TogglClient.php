<?php
declare(strict_types=1);

namespace Module\Crm\TogglMigration\Service;

use Module\Crm\TogglMigration\Repository\TogglMigrationRepository;
use RuntimeException;

final class TogglClient
{
    private const API_BASE = 'https://api.track.toggl.com/api/v9';
    private const REPORTS_BASE = 'https://api.track.toggl.com/reports/api/v3';
    private const MAX_COLLECTION_ITEMS = 10000;
    private const PAGE_SIZE = 100;

    private ?int $connectionId = null;

    public function __construct(
        private readonly TogglMigrationRepository $repo,
        private readonly int $timeout = 60,
        private readonly int $maxRetries = 4,
    ) {
    }

    public function setConnectionId(?int $connectionId): void
    {
        $this->connectionId = $connectionId;
    }

    /** @return array<string,mixed> */
    public function me(string $token): array
    {
        $response = $this->request($token, self::API_BASE . '/me');
        return is_array($response['payload']) ? $response['payload'] : [];
    }

    /** @return array<int,array<string,mixed>> */
    public function workspaces(string $token): array
    {
        return $this->collection($token, self::API_BASE . '/workspaces', []);
    }

    /** @return array<int,array<string,mixed>> */
    public function clients(string $token, string $workspaceId, bool $includeArchived = false): array
    {
        $url = self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/clients';
        $active = $this->collection($token, $url, ['status' => 'active']);
        if (!$includeArchived) return $active;
        // The v9 clients endpoint accepts a single status value; `both` is
        // not portable across accounts. Fetch both collections explicitly.
        return $this->mergeById($active, $this->collection($token, $url, ['status' => 'archived']));
    }

    /** @return array<int,array<string,mixed>> */
    public function projects(string $token, string $workspaceId, bool $includeArchived = false): array
    {
        $active = $this->collection($token, self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/projects', ['active' => 'true']);
        if (!$includeArchived) return $active;
        return $this->mergeById($active, $this->collection($token, self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/projects', ['active' => 'false']));
    }

    /** @return array<int,array<string,mixed>> */
    public function tasks(string $token, string $workspaceId, string $projectId, bool $includeArchived = false): array
    {
        $url = self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/projects/' . rawurlencode($projectId) . '/tasks';
        $active = $this->collection($token, $url, ['active' => 'true']);
        if (!$includeArchived) return $active;
        return $this->mergeById($active, $this->collection($token, $url, ['active' => 'false']));
    }

    /** @return array<int,array<string,mixed>> */
    public function tags(string $token, string $workspaceId): array
    {
        return $this->collection($token, self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/tags', []);
    }

    /**
     * Workspace users are exposed under the organization hierarchy for
     * organization workspaces. A compatibility fallback is kept for personal
     * workspaces and older v9 responses.
     *
     * @return array<int,array<string,mixed>>
     */
    public function users(string $token, string $workspaceId, ?string $organizationId = null): array
    {
        $paths = [];
        if ($organizationId !== null && $organizationId !== '') {
            $paths[] = self::API_BASE . '/organizations/' . rawurlencode($organizationId) . '/workspaces/' . rawurlencode($workspaceId) . '/workspace_users';
        }
        $paths[] = self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/workspace_users';
        $paths[] = self::API_BASE . '/workspaces/' . rawurlencode($workspaceId) . '/users';
        foreach ($paths as $path) {
            try {
                return $this->collection($token, $path, ['active' => 'true']);
            } catch (RuntimeException $e) {
                // Workspace-user visibility is optional for non-admin tokens;
                // continue with explicit mappings for the users we can resolve.
                if (!in_array($e->getCode(), [403, 404], true)) throw $e;
            }
        }
        return [];
    }

    /**
     * Stream the modern Reports API detailed time-entry search. Reports uses
     * response headers X-Next-ID/X-Next-Row-Number rather than a JSON cursor.
     * The date range is split into bounded windows to keep shared-hosting jobs
     * resumable and avoid report-size limits.
     */
    public function eachTimeEntries(string $token, string $workspaceId, string $from, string $to, array $filters, callable $consumer): int
    {
        $start = $this->date($from);
        $end = $this->date($to);
        if ($start === null || $end === null || $start > $end) throw new RuntimeException('TOGGL_INVALID_TIME_ENTRY_RANGE');
        $accepted = 0;
        $stop = false;
        $seenEntries = [];
        $windowConsumer = function (array $entry) use (&$seenEntries, &$accepted, &$stop, $workspaceId, $consumer): mixed {
            $id = (string)($entry['id'] ?? $entry['time_entry_id'] ?? '');
            if ($id === '') {
                $id = hash('sha256', implode('|', [
                    $workspaceId,
                    (string)($entry['project_id'] ?? $entry['pid'] ?? ''),
                    (string)($entry['task_id'] ?? $entry['tid'] ?? ''),
                    (string)($entry['user_id'] ?? $entry['uid'] ?? ''),
                    (string)($entry['start'] ?? ''),
                    (string)($entry['stop'] ?? $entry['end'] ?? ''),
                    (string)($entry['duration'] ?? $entry['seconds'] ?? ''),
                    !empty($entry['billable']) ? '1' : '0',
                    (string)($entry['description'] ?? ''),
                    (string)json_encode($entry['tags'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]));
            }
            if (isset($seenEntries[$id])) return true;
            $seenEntries[$id] = true;
            $result = $consumer($entry);
            if ($result === false) {
                $stop = true;
                return false;
            }
            ++$accepted;
            return $result;
        };
        $cursorDate = new \DateTimeImmutable($start . ' 00:00:00', new \DateTimeZone('UTC'));
        $endDate = new \DateTimeImmutable($end . ' 00:00:00', new \DateTimeZone('UTC'));
        while ($cursorDate <= $endDate) {
            $windowEnd = $cursorDate->modify('+30 days');
            if ($windowEnd > $endDate) $windowEnd = $endDate;
            $this->eachReportWindow($token, $workspaceId, $cursorDate->format('Y-m-d'), $windowEnd->format('Y-m-d'), $filters, $windowConsumer);
            if ($stop) break;
            $cursorDate = $windowEnd->modify('+1 day');
        }
        return $accepted;
    }

    /** @return array<string,mixed> */
    private function request(string $token, string $url, string $method = 'GET', ?array $body = null): array
    {
        if ($this->connectionId !== null) $this->waitRateLimit((int)$this->connectionId);
        for ($attempt = 1; $attempt <= max(1, $this->maxRetries); ++$attempt) {
            $headers = [];
            $ch = curl_init($url);
            if ($ch === false) throw new RuntimeException('TOGGL_CURL_INIT_FAILED');
            $options = [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => max(5, $this->timeout),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
                CURLOPT_USERPWD => $token . ':api_token',
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'User-Agent: TropaTT-Toggl-Migration/1.0'],
                CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                    $length = strlen($line);
                    if (str_contains($line, ':')) {
                        [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
                        $headers[strtolower(trim($name))] = trim($value);
                    }
                    return $length;
                },
            ];
            if ($method === 'POST') {
                $options[CURLOPT_POST] = true;
                $options[CURLOPT_POSTFIELDS] = json_encode($body ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            curl_setopt_array($ch, $options);
            $raw = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($this->connectionId !== null) $this->repo->recordRequest((int)$this->connectionId, $code, $headers);
            if ($code === 429) {
                $delay = isset($headers['retry-after']) ? max(1, (int)$headers['retry-after']) : min(60, 2 ** $attempt);
                if ($this->connectionId !== null) $this->repo->recordRetryAfter((int)$this->connectionId, $delay);
                if ($attempt < $this->maxRetries) { sleep($delay); continue; }
                throw new RuntimeException('TOGGL_RATE_LIMITED', 429);
            }
            if ($code === 401) throw new RuntimeException('TOGGL_AUTH_FAILED', 401);
            if ($code === 403) throw new RuntimeException('TOGGL_FORBIDDEN', 403);
            if ($code === 404) throw new RuntimeException('TOGGL_NOT_FOUND', 404);
            if ($raw === false || $code < 200 || $code >= 300) {
                if ($attempt < $this->maxRetries && ($code === 0 || $code >= 500)) { sleep(min(30, 2 ** $attempt)); continue; }
                throw new RuntimeException('TOGGL_HTTP_' . $code . ($error !== '' ? ': ' . $error : ''), $code);
            }
            $payload = json_decode((string)$raw, true);
            if (!is_array($payload)) throw new RuntimeException('TOGGL_INVALID_RESPONSE');
            return ['payload' => $payload, 'headers' => $headers, 'status' => $code];
        }
        throw new RuntimeException('TOGGL_REQUEST_FAILED');
    }

    /** @return array<int,array<string,mixed>> */
    private function collection(string $token, string $url, array $query): array
    {
        $items = [];
        $page = 1;
        $seenPages = [];
        do {
            if ($page > 200) throw new RuntimeException('TOGGL_PAGINATION_LIMIT_EXCEEDED');
            $params = array_merge($query, ['page' => $page, 'per_page' => self::PAGE_SIZE]);
            $separator = str_contains($url, '?') ? '&' : '?';
            $response = $this->request($token, $url . $separator . http_build_query($params));
            $pageItems = $this->itemsFromPayload($response['payload']);
            $signature = hash('sha256', (string)json_encode($pageItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            if (isset($seenPages[$signature])) throw new RuntimeException('TOGGL_PAGINATION_LOOP');
            $seenPages[$signature] = true;
            foreach ($pageItems as $item) {
                $items[] = $item;
                if (count($items) > self::MAX_COLLECTION_ITEMS) throw new RuntimeException('TOGGL_COLLECTION_LIMIT_EXCEEDED');
            }
            if (count($pageItems) < self::PAGE_SIZE) break;
            ++$page;
        } while (true);
        return $items;
    }

    private function eachReportWindow(string $token, string $workspaceId, string $from, string $to, array $filters, callable $consumer): int
    {
        $nextRow = 1;
        $nextId = null;
        $total = 0;
        $pages = 0;
        $seen = [];
        do {
            if (++$pages > 100 || $total > 100000) throw new RuntimeException('TOGGL_REPORT_LIMIT_EXCEEDED');
            $body = array_merge([
                'start_date' => $from,
                'end_date' => $this->reportEndDate($from, $to),
                'page_size' => 50,
                'enrich_response' => true,
                'first_row_number' => $nextRow,
            ], $filters);
            if ($nextId !== null) $body['first_id'] = (int)$nextId;
            $response = $this->request($token, self::REPORTS_BASE . '/workspace/' . rawurlencode($workspaceId) . '/search/time_entries', 'POST', $body);
            $items = $this->itemsFromPayload($response['payload']);
            foreach ($items as $item) {
                ++$total;
                if ($consumer($item) === false) return $total;
            }
            $headers = (array)($response['headers'] ?? []);
            $headerId = trim((string)($headers['x-next-id'] ?? ''));
            $headerRow = trim((string)($headers['x-next-row-number'] ?? ''));
            if ($headerId === '' || $headerRow === '' || $items === []) break;
            $signature = $headerId . ':' . $headerRow;
            if (isset($seen[$signature])) throw new RuntimeException('TOGGL_REPORT_PAGINATION_LOOP');
            $seen[$signature] = true;
            $nextId = $headerId;
            $nextRow = max(1, (int)$headerRow);
        } while (true);
        return $total;
    }

    /** @return array<int,array<string,mixed>> */
    private function itemsFromPayload(mixed $payload): array
    {
        if (!is_array($payload)) return [];
        foreach (['time_entries', 'items', 'projects', 'clients', 'tasks', 'tags', 'users', 'workspaces'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) return array_values(array_filter($payload[$key], 'is_array'));
        }
        foreach (['data', 'report', 'results'] as $envelope) {
            if (isset($payload[$envelope]) && is_array($payload[$envelope])) {
                foreach (['time_entries', 'items', 'data'] as $key) {
                    if (isset($payload[$envelope][$key]) && is_array($payload[$envelope][$key])) return array_values(array_filter($payload[$envelope][$key], 'is_array'));
                }
                if (array_is_list($payload[$envelope])) return array_values(array_filter($payload[$envelope], 'is_array'));
            }
        }
        return array_is_list($payload) ? array_values(array_filter($payload, 'is_array')) : [];
    }

    /** @param array<int,array<string,mixed>> $left @param array<int,array<string,mixed>> $right */
    private function mergeById(array $left, array $right): array
    {
        $merged = [];
        foreach (array_merge($left, $right) as $item) {
            $id = (string)($item['id'] ?? $item['gid'] ?? '');
            $merged[$id !== '' ? $id : hash('sha256', json_encode($item) ?: '')] = $item;
        }
        return array_values($merged);
    }

    private function reportEndDate(string $from, string $to): string
    {
        if ($from !== $to) return $to;
        return (new \DateTimeImmutable($to . ' 00:00:00', new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
    }

    private function date(string $value): ?string
    {
        $time = strtotime(trim($value));
        return $time === false ? null : gmdate('Y-m-d', $time);
    }

    private function waitRateLimit(int $connectionId): void
    {
        $state = $this->repo->rateState($connectionId);
        $until = strtotime((string)($state['retry_after_until'] ?? ''));
        if ($until > time()) sleep($until - time() + 1);
        $last = strtotime((string)($state['last_request_at'] ?? ''));
        if ($last > 0 && time() - $last < 1) usleep(1000000);
    }
}
