<?php
declare(strict_types=1);

namespace Api\Model\Estimate;

use PDO;

final class EstimateSetRepository
{
    private PDO $db;

    /** @var array<string, string> */
    private array $sortWhitelist = [
        'sort_order' => 'sort_order',
        'name' => 'name',
        'estimate_type' => 'estimate_type',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{items: array<int,array<string,mixed>>, total: int, page: int, limit: int}
     */
    public function list(array $filters, int $actorUserId, bool $isRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $where = ['es.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['scope_type'])) {
            $where[] = 'es.scope_type = :scope_type';
            $params['scope_type'] = (string)$filters['scope_type'];
        }

        if (!empty($filters['project_public_id'])) {
            $where[] = 'p.public_id = :project_public_id';
            $params['project_public_id'] = (string)$filters['project_public_id'];
        }

        if (!empty($filters['estimate_type'])) {
            $where[] = 'es.estimate_type = :estimate_type';
            $params['estimate_type'] = (string)$filters['estimate_type'];
        }

        if (isset($filters['active'])) {
            $where[] = 'es.is_active = :is_active';
            $params['is_active'] = (int)(bool)$filters['active'];
        }

        if (isset($filters['archived']) && !$filters['archived']) {
            $where[] = 'es.archived_at IS NULL';
        } elseif (!empty($filters['archived'])) {
            $where[] = 'es.archived_at IS NOT NULL';
        }

        if (!empty($filters['q'])) {
            $q = '%' . str_replace('%', '\\%', (string)$filters['q']) . '%';
            $where[] = '(es.name LIKE :q_name OR es.code LIKE :q_code)';
            $params['q_name'] = $q;
            $params['q_code'] = $q;
        }

        $sortCol = 'sort_order';
        $sortDir = 'ASC';

        if (!empty($filters['sort']) && isset($this->sortWhitelist[strtolower((string)$filters['sort'])])) {
            $sortCol = $this->sortWhitelist[strtolower((string)$filters['sort'])];
        }

        $order = strtolower((string)($filters['order'] ?? ''));
        if ($order === 'asc') {
            $sortDir = 'ASC';
        } elseif ($order === 'desc') {
            $sortDir = 'DESC';
        }

        $whereClause = implode(' AND ', $where);

        $fromClause = 'FROM estimate_sets es
            LEFT JOIN projects p ON p.id = es.project_id AND p.deleted_at IS NULL';

        $countStmt = $this->db->prepare("SELECT COUNT(*) {$fromClause} WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $selectSql = "SELECT es.*, p.public_id AS project_public_id, p.title AS project_title
            {$fromClause}
            WHERE {$whereClause}
            ORDER BY {$sortCol} {$sortDir}
            LIMIT :limit OFFSET :offset";

        $stmt = $this->db->prepare($selectSql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['project_id'] = $item['project_id'] !== null ? (int)$item['project_id'] : null;
            $item['created_by_user_id'] = (int)$item['created_by_user_id'];
            $item['updated_by_user_id'] = $item['updated_by_user_id'] !== null ? (int)$item['updated_by_user_id'] : null;
            $item['row_version'] = (int)$item['row_version'];
            $item['is_default'] = (int)$item['is_default'];
            $item['is_active'] = (int)$item['is_active'];
            $item['is_locked'] = (int)$item['is_locked'];
            $item['sort_order'] = (int)$item['sort_order'];
        }
        unset($item);

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ];
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT es.*, p.public_id AS project_public_id, p.title AS project_title
            FROM estimate_sets es
            LEFT JOIN projects p ON p.id = es.project_id AND p.deleted_at IS NULL
            WHERE es.public_id = :public_id"
        );
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int)$row['id'];
        $row['project_id'] = $row['project_id'] !== null ? (int)$row['project_id'] : null;
        $row['created_by_user_id'] = (int)$row['created_by_user_id'];
        $row['updated_by_user_id'] = $row['updated_by_user_id'] !== null ? (int)$row['updated_by_user_id'] : null;
        $row['row_version'] = (int)$row['row_version'];
        $row['is_default'] = (int)$row['is_default'];
        $row['is_active'] = (int)$row['is_active'];
        $row['is_locked'] = (int)$row['is_locked'];
        $row['sort_order'] = (int)$row['sort_order'];
        return $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM estimate_sets WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $payload): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO estimate_sets (
                public_id, scope_type, project_id, name, code, estimate_type,
                unit_label, currency_code, description,
                is_default, is_active, is_locked, active_key, sort_order,
                created_by_user_id, row_version, created_at, updated_at
            ) VALUES (
                :public_id, :scope_type, :project_id, :name, :code, :estimate_type,
                :unit_label, :currency_code, :description,
                :is_default, :is_active, :is_locked, :active_key, :sort_order,
                :created_by_user_id, 1, :created_at, :updated_at
            )"
        );

        $publicId = $payload['public_id'];
        $now = gmdate('Y-m-d H:i:s');

        $stmt->execute([
            'public_id' => $publicId,
            'scope_type' => $payload['scope_type'] ?? 'project',
            'project_id' => $payload['project_id'] ?? null,
            'name' => $payload['name'],
            'code' => $payload['code'],
            'estimate_type' => $payload['estimate_type'] ?? 'custom',
            'unit_label' => $payload['unit_label'] ?? null,
            'currency_code' => $payload['currency_code'] ?? null,
            'description' => $payload['description'] ?? null,
            'is_default' => (int)($payload['is_default'] ?? 0),
            'is_active' => (int)($payload['is_active'] ?? 1),
            'is_locked' => (int)($payload['is_locked'] ?? 0),
            'active_key' => $payload['active_key'] ?? null,
            'sort_order' => (int)($payload['sort_order'] ?? 65535),
            'created_by_user_id' => $payload['created_by_user_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByPublicId($publicId) ?? $payload;
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $fields = [];
        foreach ($set as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }
            $fields[] = "{$key} = :{$key}";
        }
        $fields[] = "updated_at = :updated_at";

        $sql = "UPDATE estimate_sets SET " . implode(', ', $fields) . " WHERE public_id = :public_id";
        $stmt = $this->db->prepare($sql);
        $params = $set;
        $params['public_id'] = $publicId;
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE estimate_sets SET archived_at = :archived_at, active_key = NULL, updated_at = :updated_at WHERE public_id = :public_id AND archived_at IS NULL"
        );
        $stmt->execute([
            'archived_at' => $archivedAt,
            'updated_at' => $archivedAt,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE estimate_sets SET deleted_at = :deleted_at, active_key = NULL, updated_at = :updated_at WHERE public_id = :public_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'deleted_at' => $deletedAt,
            'updated_at' => $deletedAt,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function projectIdByPublicId(string $projectPublicId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM projects WHERE public_id = :public_id");
        $stmt->execute(['public_id' => $projectPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function existsActiveKey(string $activeKey): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM estimate_sets WHERE active_key = :active_key LIMIT 1");
        $stmt->execute(['active_key' => $activeKey]);
        return (bool)$stmt->fetchColumn();
    }

    public function findDefaultForProject(int $projectId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM estimate_sets
            WHERE deleted_at IS NULL AND archived_at IS NULL AND is_active = 1
            AND (scope_type = 'global' OR (scope_type = 'project' AND project_id = :project_id))
            ORDER BY is_default DESC, sort_order ASC LIMIT 1"
        );
        $stmt->execute(['project_id' => $projectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
