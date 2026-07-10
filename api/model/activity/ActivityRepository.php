<?php
declare(strict_types=1);

namespace Api\Model\Activity;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Database\Builder\SqlExecutor;
use PDO;

final class ActivityRepository
{
    private SqlExecutor $sqlExecutor;

    public function __construct(private readonly PDO $pdo)
    {
        $this->sqlExecutor = new SqlExecutor($pdo);
    }

    /**
     * @return array{0:array<int,array<string,mixed>>,1:int,2:int,3:int}
     */
    public function feed(array $filters, string $actorPublicId, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $channels = $this->normalizeChannels((string)($filters['channel'] ?? 'all'));

        $parts = [];
        $params = [];

        if (in_array('audit', $channels, true)) {
            [$sql, $bind] = $this->buildAuditPart($filters, $actorPublicId, $actorIsRoot);
            $parts[] = $sql;
            $params = array_merge($params, $bind);
        }

        if (in_array('security', $channels, true)) {
            [$sql, $bind] = $this->buildSecurityPart($filters, $actorPublicId, $actorIsRoot);
            $parts[] = $sql;
            $params = array_merge($params, $bind);
        }

        if (in_array('request', $channels, true)) {
            [$sql, $bind] = $this->buildRequestPart($filters, $actorPublicId, $actorIsRoot);
            $parts[] = $sql;
            $params = array_merge($params, $bind);
        }

        if ($parts === []) {
            return [[], 0, $page, $limit];
        }

        $union = implode("\nUNION ALL\n", $parts);

        $countSql = 'SELECT COUNT(*) FROM (' . $union . ') x';
        $total = (int)$this->sqlExecutor->fetchValue($countSql, $params);

        // A global feed only needs rows that can occur on the requested page.
        // Sorting full request/audit/security logs makes the dashboard slower as
        // logs grow. Limit each independent channel first, then merge its window.
        $windowSize = max(1, $offset + $limit);
        $windowParts = [];
        $windowParams = [];
        foreach ($parts as $index => $part) {
            $alias = 'activity_window_' . $index;
            $placeholder = ':activity_window_limit_' . $index;
            $windowParts[] = 'SELECT * FROM (SELECT * FROM (' . $part . ') AS ' . $alias
                . ' ORDER BY created_at DESC LIMIT ' . $placeholder . ') AS ' . $alias . '_limited';
            $windowParams[$placeholder] = $windowSize;
        }

        $sql = 'SELECT * FROM (' . implode("\nUNION ALL\n", $windowParts) . ') x'
            . ' ORDER BY created_at DESC LIMIT :limit OFFSET :offset';
        $items = $this->sqlExecutor->fetchAll($sql, $params + $windowParams + [
            ':limit' => $limit,
            ':offset' => $offset,
        ]);

        return [$items, $total, $page, $limit];
    }

    private function normalizeChannels(string $channel): array
    {
        $channel = strtolower(trim($channel));
        if ($channel === '' || $channel === 'all') {
            return ['audit', 'security', 'request'];
        }

        $allowed = ['audit', 'security', 'request'];
        return in_array($channel, $allowed, true) ? [$channel] : ['audit', 'security', 'request'];
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildAuditPart(array $filters, string $actorPublicId, bool $actorIsRoot): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('audit_logs a')
            ->select([
                'a.public_id',
                'a.created_at',
                'a.actor_public_id',
                'a.entity_type',
                'a.entity_public_id',
                'a.action',
                'a.details AS details_json',
                '"audit" AS channel',
                'NULL AS event_type',
                'NULL AS request_route',
                'NULL AS request_id',
                'NULL AS method',
                'NULL AS result_code',
                'NULL AS status_code',
            ]);

        if (!$actorIsRoot) {
            $query->where('a.actor_public_id', '=', $actorPublicId);
        }

        if (!empty($filters['actor_public_id'])) {
            $query->where('a.actor_public_id', '=', (string)$filters['actor_public_id']);
        }
        if (!empty($filters['entity_type'])) {
            $query->where('a.entity_type', '=', (string)$filters['entity_type']);
        }
        if (!empty($filters['entity_public_id'])) {
            $query->where('a.entity_public_id', '=', (string)$filters['entity_public_id']);
        }
        if (!empty($filters['action'])) {
            $query->where('a.action', '=', (string)$filters['action']);
        }
        if (!empty($filters['from'])) {
            $query->where('a.created_at', '>=', (string)$filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('a.created_at', '<=', (string)$filters['to']);
        }

        return $this->buildUnionPart($query, 'audit');
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildSecurityPart(array $filters, string $actorPublicId, bool $actorIsRoot): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('security_logs s')
            ->select([
                's.public_id',
                's.created_at',
                's.actor_public_id',
                'NULL AS entity_type',
                'NULL AS entity_public_id',
                'NULL AS action',
                's.details AS details_json',
                '"security" AS channel',
                's.event_type',
                'NULL AS request_route',
                'NULL AS request_id',
                'NULL AS method',
                'NULL AS result_code',
                'NULL AS status_code',
            ]);

        if (!$actorIsRoot) {
            $query->where('s.actor_public_id', '=', $actorPublicId);
        }

        if (!empty($filters['actor_public_id'])) {
            $query->where('s.actor_public_id', '=', (string)$filters['actor_public_id']);
        }
        if (!empty($filters['event_type'])) {
            $query->where('s.event_type', '=', (string)$filters['event_type']);
        }
        if (!empty($filters['from'])) {
            $query->where('s.created_at', '>=', (string)$filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('s.created_at', '<=', (string)$filters['to']);
        }

        return $this->buildUnionPart($query, 'security');
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildRequestPart(array $filters, string $actorPublicId, bool $actorIsRoot): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('request_logs r')
            ->select([
                'r.public_id',
                'r.created_at',
                'r.user_public_id AS actor_public_id',
                'NULL AS entity_type',
                'NULL AS entity_public_id',
                'NULL AS action',
                'r.payload AS details_json',
                '"request" AS channel',
                'NULL AS event_type',
                'r.route AS request_route',
                'r.request_id',
                'r.method',
                'r.result_code',
                'r.status_code',
            ]);

        if (!$actorIsRoot) {
            $query->where('r.user_public_id', '=', $actorPublicId);
        }

        if (!empty($filters['actor_public_id'])) {
            $query->where('r.user_public_id', '=', (string)$filters['actor_public_id']);
        }
        if (!empty($filters['request_route'])) {
            $query->where('r.route', 'LIKE', '%' . (string)$filters['request_route'] . '%');
        }
        if (!empty($filters['method'])) {
            $query->where('r.method', '=', strtoupper((string)$filters['method']));
        }
        if (!empty($filters['result_code'])) {
            $query->where('r.result_code', '=', (string)$filters['result_code']);
        }
        if (!empty($filters['from'])) {
            $query->where('r.created_at', '>=', (string)$filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('r.created_at', '<=', (string)$filters['to']);
        }

        return $this->buildUnionPart($query, 'request');
    }

    /** @return array{0:string,1:array<string,mixed>} */
    private function buildUnionPart(QueryBuilder $query, string $prefix): array
    {
        return $this->renameBindings($query->toSql(), $query->getBindings(), $prefix);
    }

    /** @param array<string,mixed> $bindings
     *  @return array{0:string,1:array<string,mixed>}
     */
    private function renameBindings(string $sql, array $bindings, string $prefix): array
    {
        if ($bindings === []) {
            return [$sql, []];
        }

        $replacements = [];
        $renamed = [];

        uksort($bindings, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($bindings as $placeholder => $value) {
            $newPlaceholder = ':' . $prefix . '_' . ltrim($placeholder, ':');
            $replacements[$placeholder] = $newPlaceholder;
            $renamed[$newPlaceholder] = $value;
        }

        return [strtr($sql, $replacements), $renamed];
    }
}
