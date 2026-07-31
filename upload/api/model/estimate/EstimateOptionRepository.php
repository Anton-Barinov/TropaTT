<?php
declare(strict_types=1);

namespace Api\Model\Estimate;

use PDO;

final class EstimateOptionRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<int,array<string,mixed>>
     */
    public function listBySetId(int $estimateSetId, array $filters = []): array
    {
        $where = ['eo.deleted_at IS NULL AND eo.estimate_set_id = :set_id'];
        $params = ['set_id' => $estimateSetId];

        if (isset($filters['active'])) {
            $where[] = 'eo.is_active = :is_active';
            $params['is_active'] = (int)(bool)$filters['active'];
        }

        $whereClause = implode(' AND ', $where);

        $stmt = $this->db->prepare(
            "SELECT eo.*
            FROM estimate_options eo
            WHERE {$whereClause}
            ORDER BY eo.sort_order ASC"
        );
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['estimate_set_id'] = (int)$item['estimate_set_id'];
            $item['numeric_value'] = $item['numeric_value'] !== null ? (float)$item['numeric_value'] : null;
            $item['is_default'] = (int)$item['is_default'];
            $item['is_active'] = (int)$item['is_active'];
            $item['sort_order'] = (int)$item['sort_order'];
            $item['created_by_user_id'] = (int)$item['created_by_user_id'];
            $item['updated_by_user_id'] = $item['updated_by_user_id'] !== null ? (int)$item['updated_by_user_id'] : null;
            $item['row_version'] = (int)$item['row_version'];
        }
        unset($item);
        return $items;
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT eo.*, es.public_id AS estimate_set_public_id, es.name AS estimate_set_name, es.estimate_type, es.unit_label, es.currency_code
            FROM estimate_options eo
            INNER JOIN estimate_sets es ON es.id = eo.estimate_set_id
            WHERE eo.public_id = :public_id"
        );
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int)$row['id'];
        $row['estimate_set_id'] = (int)$row['estimate_set_id'];
        $row['numeric_value'] = $row['numeric_value'] !== null ? (float)$row['numeric_value'] : null;
        $row['is_default'] = (int)$row['is_default'];
        $row['is_active'] = (int)$row['is_active'];
        $row['sort_order'] = (int)$row['sort_order'];
        $row['created_by_user_id'] = (int)$row['created_by_user_id'];
        $row['updated_by_user_id'] = $row['updated_by_user_id'] !== null ? (int)$row['updated_by_user_id'] : null;
        $row['row_version'] = (int)$row['row_version'];
        return $row;
    }

    public function create(array $payload): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO estimate_options (
                public_id, estimate_set_id, label, code, numeric_value, color,
                description, is_default, is_active, active_key, sort_order,
                created_by_user_id, row_version, created_at, updated_at
            ) VALUES (
                :public_id, :estimate_set_id, :label, :code, :numeric_value, :color,
                :description, :is_default, :is_active, :active_key, :sort_order,
                :created_by_user_id, 1, :created_at, :updated_at
            )"
        );

        $publicId = $payload['public_id'];
        $now = gmdate('Y-m-d H:i:s');

        $stmt->execute([
            'public_id' => $publicId,
            'estimate_set_id' => $payload['estimate_set_id'],
            'label' => $payload['label'],
            'code' => $payload['code'],
            'numeric_value' => $payload['numeric_value'] ?? null,
            'color' => $payload['color'] ?? null,
            'description' => $payload['description'] ?? null,
            'is_default' => (int)($payload['is_default'] ?? 0),
            'is_active' => (int)($payload['is_active'] ?? 1),
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

        $sql = "UPDATE estimate_options SET " . implode(', ', $fields) . " WHERE public_id = :public_id";
        $stmt = $this->db->prepare($sql);
        $params = $set;
        $params['public_id'] = $publicId;
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function archiveByPublicId(string $publicId, string $archivedAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE estimate_options SET archived_at = :archived_at, active_key = NULL, updated_at = :updated_at WHERE public_id = :public_id AND archived_at IS NULL"
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
            "UPDATE estimate_options SET deleted_at = :deleted_at, active_key = NULL, updated_at = :updated_at WHERE public_id = :public_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'deleted_at' => $deletedAt,
            'updated_at' => $deletedAt,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function existsActiveKey(string $activeKey): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM estimate_options WHERE active_key = :active_key LIMIT 1");
        $stmt->execute(['active_key' => $activeKey]);
        return (bool)$stmt->fetchColumn();
    }
}
