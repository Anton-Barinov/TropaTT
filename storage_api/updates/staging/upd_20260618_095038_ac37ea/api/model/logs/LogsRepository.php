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

        return $qb;
    }

    private function buildSecurityQuery(array $filters): QueryBuilder
    {
        $qb = (new QueryBuilder($this->pdo))->from('security_logs');

        if (!empty($filters['event_type'])) {
            $qb->where('event_type', '=', (string)$filters['event_type']);
        }
        if (!empty($filters['actor_public_id'])) {
            $qb->where('actor_public_id', '=', (string)$filters['actor_public_id']);
        }

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

        return $qb;
    }
}
