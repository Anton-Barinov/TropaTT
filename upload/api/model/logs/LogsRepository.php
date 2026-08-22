<?php
declare(strict_types=1);

namespace Api\Model\Logs;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class LogsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function insertRequest(array $context): void
    {
        (new QueryBuilder($this->pdo))
            ->from('request_logs')
            ->insert([
            'public_id' => Ulid::generate('rql'),
            'request_id' => (string)($context['request_id'] ?? ''),
            'correlation_id' => (string)($context['correlation_id'] ?? ''),
            'user_public_id' => $context['user_public_id'] ?? null,
            'route' => (string)($context['route'] ?? ''),
            'method' => (string)($context['method'] ?? ''),
            'status_code' => (int)($context['response_status'] ?? 0),
            'result_code' => (string)($context['result_code'] ?? ''),
            'duration_ms' => (int)($context['execution_time_ms'] ?? 0),
            'payload' => json_encode($context['payload'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function insertAudit(array $context): void
    {
        (new QueryBuilder($this->pdo))
            ->from('audit_logs')
            ->insert([
            'public_id' => Ulid::generate('adl'),
            'actor_public_id' => $context['actor_public_id'] ?? null,
            'entity_type' => (string)($context['entity_type'] ?? ''),
            'entity_public_id' => (string)($context['entity_public_id'] ?? ''),
            'action' => (string)($context['action'] ?? 'audit_event'),
            'details' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function insertSecurity(array $context): void
    {
        (new QueryBuilder($this->pdo))
            ->from('security_logs')
            ->insert([
            'public_id' => Ulid::generate('sql'),
            'actor_public_id' => $context['actor_public_id'] ?? ($context['user_public_id'] ?? null),
            'event_type' => (string)($context['event_type'] ?? 'security_event'),
            'ip' => (string)($context['ip'] ?? ''),
            'user_agent' => (string)($context['user_agent'] ?? ''),
            'details' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    public function listRequests(array $filters): array
    {
        return $this->listWithPagination(
            $this->buildRequestQuery($filters)
                ->select(['public_id', 'request_id', 'correlation_id', 'user_public_id', 'route', 'method', 'status_code', 'result_code', 'duration_ms', 'payload', 'created_at']),
            fn() => $this->buildRequestQuery($filters),
            $filters
        );
    }

    public function listSecurity(array $filters): array
    {
        return $this->listWithPagination(
            $this->buildSecurityQuery($filters)
                ->select(['public_id', 'actor_public_id', 'event_type', 'ip', 'user_agent', 'details', 'created_at']),
            fn() => $this->buildSecurityQuery($filters),
            $filters
        );
    }

    public function listAudit(array $filters): array
    {
        return $this->listWithPagination(
            $this->buildAuditQuery($filters)
                ->select(['public_id', 'actor_public_id', 'entity_type', 'entity_public_id', 'action', 'details', 'created_at']),
            fn() => $this->buildAuditQuery($filters),
            $filters
        );
    }

    /**
     * Hourly histogram of frontend_api_error events for transport-error
     * monitoring (Admin → Logs). Rows are bucketed in PHP so the same code
     * runs on MySQL and SQLite. Only created_at + details are fetched.
     *
     * @param array<string,mixed> $filters accepts from / to (Y-m-d H:i:s)
     * @return array<int,array{hour:string,total:int,transport:int,other:int,codes:array<string,int>}>
     */
    public function frontendErrorChart(array $filters): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('security_logs')
            ->select(['created_at', 'details'])
            ->where('event_type', '=', 'frontend_api_error');

        if (!empty($filters['from'])) {
            $qb->where('created_at', '>=', (string)$filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->where('created_at', '<=', (string)$filters['to']);
        }

        $rows = $qb
            ->orderBy('created_at', 'ASC')
            ->limit(50000)
            ->get();

        $buckets = [];
        $order = [];
        foreach ($rows as $row) {
            $created = (string)($row['created_at'] ?? '');
            $hour = mb_substr($created, 0, 13) . ':00:00';
            if ($hour === ':00:00') {
                continue;
            }
            if (!isset($buckets[$hour])) {
                $buckets[$hour] = ['hour' => $hour, 'total' => 0, 'transport' => 0, 'other' => 0, 'codes' => []];
                $order[] = $hour;
            }

            $code = $this->extractErrorCode((string)($row['details'] ?? ''));
            $buckets[$hour]['total']++;
            $buckets[$hour]['codes'][$code] = ($buckets[$hour]['codes'][$code] ?? 0) + 1;
            if (in_array($code, self::TRANSPORT_ERROR_CODES, true)) {
                $buckets[$hour]['transport']++;
            } else {
                $buckets[$hour]['other']++;
            }
        }

        $result = [];
        foreach ($order as $hour) {
            $result[] = $buckets[$hour];
        }

        return $result;
    }

    /**
     * Error codes that indicate transport-level failures (network drops,
     * timeouts, unparseable responses). They are the ones the frontend retry
     * layer tries to hide; everything else is an HTTP/business error.
     */
    private const TRANSPORT_ERROR_CODES = [
        'NETWORK_ERROR',
        'NETWORK_TIMEOUT',
        'INVALID_API_RESPONSE',
    ];

    /**
     * Pulls payload.code from the JSON details blob written by
     * TelemetryController. Falls back to UNKNOWN when absent.
     */
    private function extractErrorCode(string $details): string
    {
        $decoded = json_decode($details, true);
        if (!is_array($decoded)) {
            return 'UNKNOWN';
        }

        $nested = $decoded['details'] ?? null;
        $payload = is_array($nested) ? ($nested['payload'] ?? null) : null;
        if (is_array($payload)) {
            $code = trim((string)($payload['code'] ?? ''));
            if ($code !== '') {
                return strtoupper($code);
            }
        }

        $event = $decoded['event_type'] ?? '';
        if (is_string($event) && $event !== '') {
            return strtoupper($event);
        }

        return 'UNKNOWN';
    }

    private function listWithPagination(QueryBuilder $listQuery, callable $countQueryFactory, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $items = $listQuery
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
        /** @var QueryBuilder $countQuery */
        $countQuery = $countQueryFactory();
        $total = $countQuery->count();

        return [$items, $total, $page, $limit];
    }

    private function buildRequestQuery(array $filters): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))->from('request_logs');

        if (!empty($filters['method'])) {
            $qb->where('method', '=', (string)$filters['method']);
        }
        if (!empty($filters['request_route'])) {
            $qb->where('route', 'LIKE', '%' . (string)$filters['request_route'] . '%');
        }
        if (!empty($filters['request_id'])) {
            $qb->where('request_id', '=', (string)$filters['request_id']);
        }
        if (!empty($filters['user_public_id'])) {
            $qb->where('user_public_id', '=', (string)$filters['user_public_id']);
        }
        // Status group filter for the API tab: success (2xx), client (4xx),
        // server (5xx). Applied as a range over status_code.
        if (!empty($filters['status_class'])) {
            $class = (string)$filters['status_class'];
            if ($class === '2xx') {
                $qb->where('status_code', '>=', 200)->where('status_code', '<=', 299);
            } elseif ($class === '4xx') {
                $qb->where('status_code', '>=', 400)->where('status_code', '<=', 499);
            } elseif ($class === '5xx') {
                $qb->where('status_code', '>=', 500);
            }
        }
        $this->applyDateRange($qb, $filters);

        return $qb;
    }

    private function buildSecurityQuery(array $filters): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))->from('security_logs');

        if (!empty($filters['event_type'])) {
            $qb->where('event_type', '=', (string)$filters['event_type']);
        }
        // Security tab excludes browser telemetry events (they are shown on
        // the dedicated "Errors" tab instead).
        if (!empty($filters['exclude_event_prefix'])) {
            $qb->where('event_type', 'NOT LIKE', (string)$filters['exclude_event_prefix'] . '%');
        }
        if (!empty($filters['actor_public_id'])) {
            $qb->where('actor_public_id', '=', (string)$filters['actor_public_id']);
        }
        $this->applyDateRange($qb, $filters);

        return $qb;
    }

    private function buildAuditQuery(array $filters): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))->from('audit_logs');

        if (!empty($filters['entity_type'])) {
            $qb->where('entity_type', '=', (string)$filters['entity_type']);
        }
        if (!empty($filters['entity_public_id'])) {
            $qb->where('entity_public_id', '=', (string)$filters['entity_public_id']);
        }
        if (!empty($filters['actor_public_id'])) {
            $qb->where('actor_public_id', '=', (string)$filters['actor_public_id']);
        }
        if (!empty($filters['action'])) {
            $qb->where('action', '=', (string)$filters['action']);
        }
        if (!empty($filters['action_prefix'])) {
            $qb->where('action', 'LIKE', (string)$filters['action_prefix'] . '%');
        }
        $this->applyDateRange($qb, $filters);

        return $qb;
    }

    /**
     * Shared created_at range filter — previously from/to were accepted by
     * the controllers but silently ignored by these list queries.
     */
    private function applyDateRange(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['from'])) {
            $qb->where('created_at', '>=', (string)$filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->where('created_at', '<=', (string)$filters['to']);
        }
    }
}
