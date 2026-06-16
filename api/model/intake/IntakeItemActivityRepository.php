<?php
declare(strict_types=1);

namespace Api\Model\Intake;

use PDO;

final class IntakeItemActivityRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function create(array $data): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO intake_item_activities (
                public_id, intake_item_id, actor_user_id,
                event_type, field_name, old_value, new_value, comment,
                created_at
            ) VALUES (
                :public_id, :intake_item_id, :actor_user_id,
                :event_type, :field_name, :old_value, :new_value, :comment,
                :created_at
            )"
        );

        $stmt->execute([
            'public_id' => $data['public_id'],
            'intake_item_id' => $data['intake_item_id'],
            'actor_user_id' => $data['actor_user_id'] ?? null,
            'event_type' => $data['event_type'],
            'field_name' => $data['field_name'] ?? null,
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'comment' => $data['comment'] ?? null,
            'created_at' => $data['created_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);

        $id = (int)$this->db->lastInsertId();
        return $this->findById($id) ?? $data;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listByIntakeItemId(int $intakeItemId): array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.login AS actor_name
            FROM intake_item_activities a
            LEFT JOIN users u ON u.id = a.actor_user_id
            WHERE a.intake_item_id = :intake_item_id
            ORDER BY a.created_at ASC"
        );
        $stmt->execute(['intake_item_id' => $intakeItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT a.*, u.login AS actor_name
            FROM intake_item_activities a
            LEFT JOIN users u ON u.id = a.actor_user_id
            WHERE a.id = :id"
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
