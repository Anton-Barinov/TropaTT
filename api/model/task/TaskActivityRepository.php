<?php
declare(strict_types=1);

namespace Api\Model\Task;

use Api\System\Library\Database\Builder\Expression;
use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class TaskActivityRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function create(array $payload): array
    {
        $publicId = $payload['public_id'] ?? throw new \InvalidArgumentException('public_id is required');

        $qb = new QueryBuilder($this->pdo);
        $qb->from('task_activity_events')
            ->insert([
                'public_id' => $publicId,
                'task_id' => (int)($payload['task_id'] ?? 0),
                'task_public_id' => (string)($payload['task_public_id'] ?? ''),
                'actor_user_id' => isset($payload['actor_user_id']) ? (int)$payload['actor_user_id'] : null,
                'actor_type' => (string)($payload['actor_type'] ?? 'user'),
                'actor_public_id' => (string)($payload['actor_public_id'] ?? ''),
                'actor_display_name' => (string)($payload['actor_display_name'] ?? ''),
                'event_type' => (string)($payload['event_type'] ?? ''),
                'field_name' => (string)($payload['field_name'] ?? ''),
                'old_value' => (string)($payload['old_value'] ?? ''),
                'new_value' => (string)($payload['new_value'] ?? ''),
                'old_label' => (string)($payload['old_label'] ?? ''),
                'new_label' => (string)($payload['new_label'] ?? ''),
                'related_entity_type' => (string)($payload['related_entity_type'] ?? ''),
                'related_entity_id' => isset($payload['related_entity_id']) ? (int)$payload['related_entity_id'] : null,
                'related_entity_public_id' => (string)($payload['related_entity_public_id'] ?? ''),
                'related_entity_label' => (string)($payload['related_entity_label'] ?? ''),
                'message_key' => (string)($payload['message_key'] ?? ''),
                'message_text' => (string)($payload['message_text'] ?? ''),
                'payload_json' => !empty($payload['payload_json']) ? json_encode($payload['payload_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'visibility' => (string)($payload['visibility'] ?? 'default'),
                'request_id' => (string)($payload['request_id'] ?? ''),
                'source_type' => (string)($payload['source_type'] ?? ''),
                'source_ref' => (string)($payload['source_ref'] ?? ''),
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

        // Return the created event
        $row = $this->pdo->query("SELECT * FROM task_activity_events WHERE public_id = " . $this->pdo->quote($publicId))->fetch(PDO::FETCH_ASSOC);
        return $row ?: $payload;
    }

    public function listByTaskPublicId(string $taskPublicId, array $filters = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 30)));
        $offset = ($page - 1) * $limit;

        $qb = (new QueryBuilder($this->pdo))
            ->from('task_activity_events e')
            ->where('e.task_public_id', '=', $taskPublicId)
            ->where('e.deleted_at', '=', null);

        // Optional filters
        if (!empty($filters['event_type'])) {
            $qb->where('e.event_type', '=', (string)$filters['event_type']);
        }
        if (!empty($filters['actor_user_id'])) {
            $qb->where('e.actor_user_id', '=', (int)$filters['actor_user_id']);
        }
        if (!empty($filters['actor_type'])) {
            $qb->where('e.actor_type', '=', (string)$filters['actor_type']);
        }
        if (!empty($filters['created_from'])) {
            $qb->where('e.created_at', '>=', (string)$filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $qb->where('e.created_at', '<=', (string)$filters['created_to']);
        }
        if (!empty($filters['visibility'])) {
            $qb->where('e.visibility', '=', (string)$filters['visibility']);
        }
        if (!empty($filters['related_entity_type'])) {
            $qb->where('e.related_entity_type', '=', (string)$filters['related_entity_type']);
        }
        if (!empty($filters['fields_only'])) {
            $qb->where('e.field_name', '!=', '');
        }

        // Sort whitelist
        $sortWhitelist = ['created_at', 'event_type', 'actor_type'];
        $sort = in_array((string)($filters['sort'] ?? ''), $sortWhitelist, true) ? (string)$filters['sort'] : 'created_at';
        $order = strtoupper((string)($filters['order'] ?? 'desc')) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy('e.' . $sort, $order)
            ->limit($limit)
            ->offset($offset);

        $items = $qb->get();

        // Get total count
        $countQb = (new QueryBuilder($this->pdo))
            ->from('task_activity_events')
            ->where('task_public_id', '=', $taskPublicId)
            ->where('deleted_at', '=', null);

        if (!empty($filters['event_type'])) {
            $countQb->where('event_type', '=', (string)$filters['event_type']);
        }
        if (!empty($filters['actor_user_id'])) {
            $countQb->where('actor_user_id', '=', (int)$filters['actor_user_id']);
        }
        if (!empty($filters['actor_type'])) {
            $countQb->where('actor_type', '=', (string)$filters['actor_type']);
        }
        if (!empty($filters['visibility'])) {
            $countQb->where('visibility', '=', (string)$filters['visibility']);
        }
        if (!empty($filters['fields_only'])) {
            $countQb->where('field_name', '!=', '');
        }

        $total = $countQb->count();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        $qb = new QueryBuilder($this->pdo);
        $qb->from('task_activity_events')
            ->update(['deleted_at' => $deletedAt])
            ->where('public_id', '=', $publicId);

        return true;
    }

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tasks WHERE public_id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$taskPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)$row['id'] : null;
    }
}
