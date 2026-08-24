<?php
declare(strict_types=1);

namespace Api\Model\Webhook;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;
use Api\System\Library\Support\LikeEscaper;

final class WebhookRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listSubscriptions(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildSubscriptionsListQuery($filters)->count();
        $rows = $this->buildSubscriptionsListQuery($filters)
            ->select(['public_id', 'title', 'endpoint', 'events', 'is_active', 'created_at', 'updated_at'])
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();
        foreach ($rows as &$row) {
            $row['events'] = $this->decodeList($row['events'] ?? null);
            $row['is_active'] = (int)($row['is_active'] ?? 0);
        }
        unset($row);

        return [$rows, $total, $page, $limit];
    }

    public function findSubscriptionByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions')
            ->select(['*'])
            ->where('public_id', '=', $publicId)
            ->first();
        if (!$row) {
            return null;
        }

        $row['events'] = $this->decodeList($row['events'] ?? null);
        $row['is_active'] = (int)($row['is_active'] ?? 0);
        return $row;
    }

    public function createSubscription(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions')
            ->insert($payload);
    }

    public function updateSubscriptionByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteSubscriptionByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function createDelivery(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->insert($payload);
    }

    public function claimNextRunnableDelivery(string $now): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries d')
            ->join('webhook_subscriptions w', 'w.id', '=', 'd.webhook_id')
            ->select([
                'd.*',
                'w.public_id AS webhook_public_id',
                'w.endpoint',
                'w.secret_hash',
                'w.is_active',
            ])
            ->where('d.status', '=', 'queued')
            ->where('d.dead_letter', '=', 0)
            ->whereRaw('(d.next_run_at IS NULL OR d.next_run_at <= ?)', [$now])
            ->whereRaw('(d.locked_at IS NULL OR d.locked_at <= ?)', [gmdate('Y-m-d H:i:s', time() - 900)])
            ->orderBy('d.created_at', 'ASC')
            ->orderBy('d.id', 'ASC')
            ->limit(1)
            ->first();

        if (!$row) {
            return null;
        }

        $locked = (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->where('id', '=', (int)$row['id'])
            ->where('status', '=', 'queued')
            ->update([
                'locked_at' => $now,
                'updated_at' => $now,
            ]);

        return $locked > 0 ? $row : null;
    }

    public function updateDeliveryByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function listDeliveries(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 50)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildDeliveriesListQuery($filters)->count();
        $items = $this->buildDeliveriesListQuery($filters)
            ->select([
                'd.public_id',
                'd.event_code',
                'd.status',
                'd.response_code',
                'd.created_at',
                'w.public_id AS webhook_public_id',
                'w.title AS webhook_title',
                'w.endpoint',
            ])
            ->orderBy('d.created_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildSubscriptionsListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions');

        if (!empty($filters['search'])) {
            $needle = '%' . LikeEscaper::escape(trim((string)$filters['search'])) . '%';
            $query->whereRaw('(title LIKE ? OR endpoint LIKE ?)', [$needle, $needle]);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== '' && $filters['is_active'] !== null) {
            $query->where(
                'is_active',
                '=',
                (int)(((string)$filters['is_active'] === '1' || (string)$filters['is_active'] === 'true') ? 1 : 0)
            );
        }

        return $query;
    }

    private function buildDeliveriesListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries d')
            ->join('webhook_subscriptions w', 'w.id', '=', 'd.webhook_id');

        if (!empty($filters['status'])) {
            $query->where('d.status', '=', trim((string)$filters['status']));
        }

        if (!empty($filters['event_code'])) {
            $query->where('d.event_code', '=', trim((string)$filters['event_code']));
        }

        if (!empty($filters['webhook_public_id'])) {
            $query->where('w.public_id', '=', trim((string)$filters['webhook_public_id']));
        }

        return $query;
    }

    public function summary(): array
    {
        $total = (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions')
            ->count();
        $active = (new QueryBuilder($this->pdo))
            ->from('webhook_subscriptions')
            ->where('is_active', '=', 1)
            ->count();
        $deliveries = (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->count();
        $failed = (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->whereRaw('status IN (?, ?)', ['failed', 'error'])
            ->count();

        return [
            'subscriptions_total' => $total,
            'subscriptions_active' => $active,
            'deliveries_total' => $deliveries,
            'deliveries_failed' => $failed,
        ];
    }

    /** @return list<string> */
    public function recentDeliveryStatuses(int $webhookId, int $limit = 20): array
    {
        $limit = min(200, max(1, $limit));
        $rows = (new QueryBuilder($this->pdo))
            ->from('webhook_deliveries')
            ->select(['status'])
            ->where('webhook_id', '=', $webhookId)
            ->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();

        return array_values(array_map(static fn(array $row): string => (string)($row['status'] ?? ''), $rows));
    }

    /** @return list<string> */
    private function decodeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $value), static fn(string $v): bool => $v !== ''));
        }

        $raw = trim((string)$value);
        if ($raw === '') {
            return [];
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            return array_values(array_filter(array_map(static fn($v): string => trim((string)$v), $json), static fn(string $v): bool => $v !== ''));
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn(string $v): bool => $v !== ''));
    }
}
