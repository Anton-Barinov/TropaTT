<?php
declare(strict_types=1);

namespace Api\Model\Sticky;

use PDO;

final class StickyNoteRepository
{
    private const ALLOWED_SORT = ['sort_order', 'created_at', 'updated_at', 'title', 'color'];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, int $actorUserId, bool $isRoot): array
    {
        $where = ['sn.deleted_at IS NULL'];
        $params = [];

        if (!$isRoot) {
            $where[] = '(sn.owner_user_id = :actor_user_id OR (sn.visibility = \'shared\' AND sn.owner_user_id != :actor_user_id2))';
            $params['actor_user_id'] = $actorUserId;
            $params['actor_user_id2'] = $actorUserId;
        }

        if (isset($filters['archived'])) {
            $archived = (bool)$filters['archived'];
            if ($archived) {
                $where[] = 'sn.archived_at IS NOT NULL';
            } else {
                $where[] = 'sn.archived_at IS NULL';
            }
        } else {
            $where[] = 'sn.archived_at IS NULL';
        }

        if (!empty($filters['context_type'])) {
            $where[] = 'sn.context_type = :context_type';
            $params['context_type'] = (string)$filters['context_type'];
        }
        if (array_key_exists('context_public_id', $filters)) {
            if ($filters['context_public_id'] !== '' && $filters['context_public_id'] !== null) {
                $where[] = 'sn.context_public_id = :context_public_id';
                $params['context_public_id'] = (string)$filters['context_public_id'];
            } else {
                $where[] = 'sn.context_public_id IS NULL';
            }
        }
        if (!empty($filters['owner_user_public_id'])) {
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
            $stmt->execute(['public_id' => (string)$filters['owner_user_public_id']]);
            $ownerId = $stmt->fetchColumn();
            if ($ownerId) {
                $where[] = 'sn.owner_user_id = :owner_user_id';
                $params['owner_user_id'] = (int)$ownerId;
            }
        }
        if (!empty($filters['visibility'])) {
            $where[] = 'sn.visibility = :visibility';
            $params['visibility'] = (string)$filters['visibility'];
        }
        if (!empty($filters['color'])) {
            $where[] = 'sn.color = :color';
            $params['color'] = (string)$filters['color'];
        }
        if (isset($filters['pinned'])) {
            $where[] = 'sn.is_pinned = :is_pinned';
            $params['is_pinned'] = (bool)$filters['pinned'] ? 1 : 0;
        }
        if (isset($filters['converted'])) {
            $converted = (bool)$filters['converted'];
            if ($converted) {
                $where[] = 'sn.converted_to_entity_type IS NOT NULL';
            } else {
                $where[] = 'sn.converted_to_entity_type IS NULL';
            }
        }
        if (!empty($filters['q'])) {
            $q = '%' . str_replace(['%', '_'], ['\\\\%', '\\\\_'], (string)$filters['q']) . '%';
            $where[] = '(sn.title LIKE :q_title OR sn.body LIKE :q_body)';
            $params['q_title'] = $q;
            $params['q_body'] = $q;
        }

        $limit = min(100, max(1, (int)($filters['limit'] ?? 50)));
        $page = max(1, (int)($filters['page'] ?? 1));
        $offset = ($page - 1) * $limit;
        $sort = in_array((string)($filters['sort'] ?? ''), self::ALLOWED_SORT, true) ? (string)$filters['sort'] : 'sort_order';
        $order = strtoupper((string)($filters['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';

        // Pinned first, then by sort_order
        $orderBy = 'sn.is_pinned DESC, sn.' . $sort . ' ' . $order . ', sn.updated_at DESC';

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM sticky_notes sn WHERE ' . implode(' AND ', $where));
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare('SELECT sn.*, u.public_id AS owner_public_id, COALESCE(u.full_name, u.login, u.public_id) AS owner_name FROM sticky_notes sn LEFT JOIN users u ON u.id = sn.owner_user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy . ' LIMIT ' . $limit . ' OFFSET ' . $offset);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
        ];
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT sn.*, u.public_id AS owner_public_id, COALESCE(u.full_name, u.login, u.public_id) AS owner_name FROM sticky_notes sn LEFT JOIN users u ON u.id = sn.owner_user_id WHERE sn.public_id = :public_id AND sn.deleted_at IS NULL LIMIT 1');
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(array $payload): array
    {
        $publicId = $payload['public_id'] ?? $this->publicId();
        $now = gmdate('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('INSERT INTO sticky_notes (
            public_id, owner_user_id, context_type, context_public_id,
            title, body, color, background_color, visibility,
            is_pinned, sort_order,
            converted_to_entity_type, converted_to_entity_public_id, converted_at, converted_by_user_id,
            meta_json,
            row_version, archived_at, deleted_at, created_at, updated_at
        ) VALUES (
            :public_id, :owner_user_id, :context_type, :context_public_id,
            :title, :body, :color, :background_color, :visibility,
            :is_pinned, :sort_order,
            :converted_to_entity_type, :converted_to_entity_public_id, :converted_at, :converted_by_user_id,
            :meta_json,
            1, NULL, NULL, :created_at, :updated_at
        )');

        $stmt->execute([
            'public_id' => $publicId,
            'owner_user_id' => (int)($payload['owner_user_id'] ?? 0),
            'context_type' => (string)($payload['context_type'] ?? 'personal'),
            'context_public_id' => $payload['context_public_id'] ?? null,
            'title' => $payload['title'] ?? null,
            'body' => (string)($payload['body'] ?? ''),
            'color' => (string)($payload['color'] ?? 'yellow'),
            'background_color' => $payload['background_color'] ?? null,
            'visibility' => (string)($payload['visibility'] ?? 'private'),
            'is_pinned' => !empty($payload['is_pinned']) ? 1 : 0,
            'sort_order' => (int)($payload['sort_order'] ?? $this->nextSortOrder((int)($payload['owner_user_id'] ?? 0), (string)($payload['context_type'] ?? 'personal'), $payload['context_public_id'] ?? null)),
            'converted_to_entity_type' => $payload['converted_to_entity_type'] ?? null,
            'converted_to_entity_public_id' => $payload['converted_to_entity_public_id'] ?? null,
            'converted_at' => $payload['converted_at'] ?? null,
            'converted_by_user_id' => $payload['converted_by_user_id'] ?? null,
            'meta_json' => isset($payload['meta_json']) ? (is_string($payload['meta_json']) ? $payload['meta_json'] : json_encode($payload['meta_json'], JSON_UNESCAPED_UNICODE)) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        $setParts = [];
        $params = ['public_id' => $publicId];
        foreach ($set as $column => $value) {
            $setParts[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }
        $setParts[] = 'updated_at = :updated_at';
        $params['updated_at'] = gmdate('Y-m-d H:i:s');

        $sql = 'UPDATE sticky_notes SET ' . implode(', ', $setParts) . ' WHERE public_id = :public_id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sticky_notes SET archived_at = :archived_at, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['archived_at' => $archivedAt, 'updated_at' => gmdate('Y-m-d H:i:s'), 'public_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    public function unarchiveByPublicId(string $publicId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sticky_notes SET archived_at = NULL, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['updated_at' => gmdate('Y-m-d H:i:s'), 'public_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        $stmt = $this->pdo->prepare('UPDATE sticky_notes SET deleted_at = :deleted_at, updated_at = :updated_at WHERE public_id = :public_id');
        $stmt->execute(['deleted_at' => $deletedAt, 'updated_at' => gmdate('Y-m-d H:i:s'), 'public_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    public function reorder(array $items, int $actorUserId): void
    {
        $stmt = $this->pdo->prepare('UPDATE sticky_notes SET sort_order = :sort_order, updated_at = :updated_at WHERE public_id = :public_id AND owner_user_id = :owner_user_id');
        $now = gmdate('Y-m-d H:i:s');
        foreach ($items as $item) {
            $stmt->execute([
                'sort_order' => (int)($item['sort_order'] ?? 65535),
                'updated_at' => $now,
                'public_id' => (string)$item['public_id'],
                'owner_user_id' => $actorUserId,
            ]);
        }
    }

    public function markConverted(string $publicId, array $set): bool
    {
        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        return $this->updateByPublicId($publicId, $set);
    }

    public function nextSortOrder(int $ownerUserId, string $contextType, ?string $contextPublicId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM sticky_notes WHERE owner_user_id = :owner_user_id AND context_type = :context_type AND context_public_id ' . ($contextPublicId ? '= :context_public_id' : 'IS NULL') . ' AND deleted_at IS NULL');
        $params = ['owner_user_id' => $ownerUserId, 'context_type' => $contextType];
        if ($contextPublicId) {
            $params['context_public_id'] = $contextPublicId;
        }
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function taskByPublicId(string $taskPublicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, project_id, title FROM tasks WHERE public_id = :public_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['public_id' => $taskPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function projectByPublicId(string $projectPublicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, title FROM projects WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $projectPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function userByPublicId(string $userPublicId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, public_id, full_name, login FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute(['public_id' => $userPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function publicId(): string
    {
        return 'stn_' . bin2hex(random_bytes(16));
    }
}
