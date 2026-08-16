<?php
declare(strict_types=1);

namespace Api\Model\Estimate;

use PDO;

final class TaskEstimateRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listByTaskId(int $taskId): array
    {
        $stmt = $this->db->prepare(
            "SELECT te.*,
                es.public_id AS estimate_set_public_id,
                es.name AS estimate_set_name,
                es.estimate_type,
                es.unit_label,
                es.currency_code AS set_currency_code,
                eo.public_id AS estimate_option_public_id,
                eo.label AS option_label,
                eo.color AS option_color,
                u.public_id AS assigned_by_user_public_id,
                u.login AS assigned_by_name
            FROM task_estimates te
            INNER JOIN estimate_sets es ON es.id = te.estimate_set_id
            LEFT JOIN estimate_options eo ON eo.id = te.estimate_option_id
            LEFT JOIN users u ON u.id = te.assigned_by_user_id
            WHERE te.task_id = :task_id AND te.deleted_at IS NULL
            ORDER BY es.sort_order ASC"
        );
        $stmt->execute(['task_id' => $taskId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['id'] = (int)$item['id'];
            $item['task_id'] = (int)$item['task_id'];
            $item['estimate_set_id'] = (int)$item['estimate_set_id'];
            $item['estimate_option_id'] = $item['estimate_option_id'] !== null ? (int)$item['estimate_option_id'] : null;
            $item['numeric_value'] = $item['numeric_value'] !== null ? (float)$item['numeric_value'] : null;
            $item['assigned_by_user_id'] = (int)$item['assigned_by_user_id'];
            $item['updated_by_user_id'] = $item['updated_by_user_id'] !== null ? (int)$item['updated_by_user_id'] : null;
            $item['row_version'] = (int)$item['row_version'];
        }
        unset($item);
        return $items;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listByTaskPublicId(string $taskPublicId): array
    {
        $stmt = $this->db->prepare("SELECT id FROM tasks WHERE public_id = :public_id AND deleted_at IS NULL");
        $stmt->execute(['public_id' => $taskPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [];
        }
        return $this->listByTaskId((int)$row['id']);
    }

    public function findByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT te.*,
                es.public_id AS estimate_set_public_id,
                es.name AS estimate_set_name,
                es.estimate_type,
                es.unit_label,
                eo.public_id AS estimate_option_public_id,
                eo.label AS option_label,
                u.public_id AS assigned_by_user_public_id,
                u.login AS assigned_by_name
            FROM task_estimates te
            INNER JOIN estimate_sets es ON es.id = te.estimate_set_id
            LEFT JOIN estimate_options eo ON eo.id = te.estimate_option_id
            LEFT JOIN users u ON u.id = te.assigned_by_user_id
            WHERE te.public_id = :public_id"
        );
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int)$row['id'];
        $row['task_id'] = (int)$row['task_id'];
        $row['estimate_set_id'] = (int)$row['estimate_set_id'];
        $row['estimate_option_id'] = $row['estimate_option_id'] !== null ? (int)$row['estimate_option_id'] : null;
        $row['numeric_value'] = $row['numeric_value'] !== null ? (float)$row['numeric_value'] : null;
        $row['assigned_by_user_id'] = (int)$row['assigned_by_user_id'];
        $row['updated_by_user_id'] = $row['updated_by_user_id'] !== null ? (int)$row['updated_by_user_id'] : null;
        $row['row_version'] = (int)$row['row_version'];
        return $row;
    }

    public function findActiveByTaskAndSet(int $taskId, int $estimateSetId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT te.*
            FROM task_estimates te
            WHERE te.task_id = :task_id AND te.estimate_set_id = :set_id AND te.deleted_at IS NULL
            LIMIT 1"
        );
        $stmt->execute(['task_id' => $taskId, 'set_id' => $estimateSetId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertTaskEstimate(array $payload): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $activeKey = "task:{$payload['task_id']}:set:{$payload['estimate_set_id']}";

        // Soft delete existing active estimate for this set
        $existing = $this->findActiveByTaskAndSet($payload['task_id'], $payload['estimate_set_id']);
        if ($existing) {
            $deleteStmt = $this->db->prepare(
                "UPDATE task_estimates SET deleted_at = :deleted_at, active_key = NULL, updated_at = :updated_at
                WHERE id = :id AND deleted_at IS NULL"
            );
            $deleteStmt->execute([
                'deleted_at' => $now,
                'updated_at' => $now,
                'id' => (int)$existing['id'],
            ]);
        }

        $publicId = $payload['public_id'] ?? ('tes_' . bin2hex(random_bytes(10)));

        $stmt = $this->db->prepare(
            "INSERT INTO task_estimates (
                public_id, task_id, task_public_id, estimate_set_id, estimate_option_id,
                numeric_value, text_value, currency_code, note,
                assigned_by_user_id, assigned_at, row_version, active_key,
                created_at, updated_at
            ) VALUES (
                :public_id, :task_id, :task_public_id, :estimate_set_id, :estimate_option_id,
                :numeric_value, :text_value, :currency_code, :note,
                :assigned_by_user_id, :assigned_at, 1, :active_key,
                :created_at, :updated_at
            )"
        );

        $stmt->execute([
            'public_id' => $publicId,
            'task_id' => $payload['task_id'],
            'task_public_id' => $payload['task_public_id'],
            'estimate_set_id' => $payload['estimate_set_id'],
            'estimate_option_id' => $payload['estimate_option_id'] ?? null,
            'numeric_value' => $payload['numeric_value'] ?? null,
            'text_value' => $payload['text_value'] ?? null,
            'currency_code' => $payload['currency_code'] ?? null,
            'note' => $payload['note'] ?? null,
            'assigned_by_user_id' => $payload['assigned_by_user_id'],
            'assigned_at' => $now,
            'active_key' => $activeKey,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByPublicId($publicId);
    }

    public function removeByPublicId(string $publicId, int $actorUserId, string $deletedAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE task_estimates SET deleted_at = :deleted_at, active_key = NULL, updated_by_user_id = :updated_by, updated_at = :updated_at
            WHERE public_id = :public_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'deleted_at' => $deletedAt,
            'updated_by' => $actorUserId,
            'updated_at' => $deletedAt,
            'public_id' => $publicId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function removeByTaskAndSet(int $taskId, int $estimateSetId, int $actorUserId, string $deletedAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE task_estimates SET deleted_at = :deleted_at, active_key = NULL, updated_by_user_id = :updated_by, updated_at = :updated_at
            WHERE task_id = :task_id AND estimate_set_id = :set_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'deleted_at' => $deletedAt,
            'updated_by' => $actorUserId,
            'updated_at' => $deletedAt,
            'task_id' => $taskId,
            'set_id' => $estimateSetId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM tasks WHERE public_id = :public_id AND deleted_at IS NULL");
        $stmt->execute(['public_id' => $taskPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function summaryByProjectId(int $projectId, array $filters = []): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                es.id AS set_id,
                es.public_id AS estimate_set_public_id,
                es.name AS estimate_set_name,
                es.estimate_type,
                es.unit_label,
                COUNT(DISTINCT t.id) AS tasks_total,
                COUNT(DISTINCT te.id) AS tasks_estimated,
                COUNT(DISTINCT t.id) - COUNT(DISTINCT te.id) AS tasks_unestimated,
                COALESCE(SUM(te.numeric_value), 0) AS total_value,
                COALESCE(SUM(CASE WHEN t.status_code IN ('done','closed','cancelled') THEN te.numeric_value ELSE 0 END), 0) AS completed_value,
                COALESCE(SUM(CASE WHEN t.status_code NOT IN ('done','closed','cancelled') THEN te.numeric_value ELSE 0 END), 0) AS open_value,
                COALESCE(AVG(te.numeric_value), 0) AS average_value
            FROM tasks t
            INNER JOIN estimate_sets es ON (es.scope_type = 'global' OR (es.scope_type = 'project' AND es.project_id = :project_id))
            LEFT JOIN task_estimates te ON te.task_id = t.id AND te.estimate_set_id = es.id AND te.deleted_at IS NULL
            WHERE t.project_id = :project_id2 AND t.deleted_at IS NULL
            GROUP BY es.id, es.public_id, es.name, es.estimate_type, es.unit_label
            ORDER BY es.sort_order ASC"
        );
        $stmt->execute(['project_id' => $projectId, 'project_id2' => $projectId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['tasks_total'] = (int)$item['tasks_total'];
            $item['tasks_estimated'] = (int)$item['tasks_estimated'];
            $item['tasks_unestimated'] = (int)$item['tasks_unestimated'];
            $item['total_value'] = (float)$item['total_value'];
            $item['completed_value'] = (float)$item['completed_value'];
            $item['open_value'] = (float)$item['open_value'];
            $item['average_value'] = (float)$item['average_value'];
        }
        unset($item);
        return $items;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function summaryByCycleId(int $cycleId, array $filters = []): array
    {
        // Check if cycle_tasks and work_cycles tables exist
        try {
            $stmt = $this->db->prepare(
                "SELECT
                    es.id AS set_id,
                    es.public_id AS estimate_set_public_id,
                    es.name AS estimate_set_name,
                    es.estimate_type,
                    es.unit_label,
                    COUNT(DISTINCT t.id) AS tasks_total,
                    COUNT(DISTINCT te.id) AS tasks_estimated,
                    COALESCE(SUM(te.numeric_value), 0) AS total_value,
                    COALESCE(SUM(CASE WHEN t.status_code IN ('done','closed','cancelled') THEN te.numeric_value ELSE 0 END), 0) AS completed_value,
                    COALESCE(SUM(CASE WHEN t.status_code NOT IN ('done','closed','cancelled') THEN te.numeric_value ELSE 0 END), 0) AS open_value
                FROM cycle_tasks ct
                INNER JOIN tasks t ON t.id = ct.task_id AND t.deleted_at IS NULL
                INNER JOIN estimate_sets es ON (es.scope_type = 'global' OR (es.scope_type = 'project' AND es.project_id = t.project_id))
                LEFT JOIN task_estimates te ON te.task_id = t.id AND te.estimate_set_id = es.id AND te.deleted_at IS NULL
                WHERE ct.cycle_id = :cycle_id AND ct.deleted_at IS NULL
                GROUP BY es.id, es.public_id, es.name, es.estimate_type, es.unit_label
                ORDER BY es.sort_order ASC"
            );
            $stmt->execute(['cycle_id' => $cycleId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($items as &$item) {
                $item['tasks_total'] = (int)$item['tasks_total'];
                $item['tasks_estimated'] = (int)$item['tasks_estimated'];
                $item['total_value'] = (float)$item['total_value'];
                $item['completed_value'] = (float)$item['completed_value'];
                $item['open_value'] = (float)$item['open_value'];
            }
            unset($item);
            return $items;
        } catch (\Throwable $e) {
            error_log('[TaskEstimateRepository::summaryByCycleId] ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Pick the most meaningful estimate set for a cycle (story points when
     * present, otherwise the first set that actually has estimates) and return
     * its totals. Mirrors WorkCycleService::estimatePointsForCycle() so the
     * daily snapshot cron persists the same unit the UI shows.
     *
     * @return array{set_id:int,total:float,completed:float,unit_label:string}|null
     */
    public function chosenPointsByCycleId(int $cycleId): ?array
    {
        $sets = $this->summaryByCycleId($cycleId);
        if ($sets === []) {
            return null;
        }

        $chosen = null;
        foreach ($sets as $set) {
            if ((int)($set['tasks_estimated'] ?? 0) <= 0) {
                continue;
            }
            if (($set['estimate_type'] ?? '') === 'story_points') {
                $chosen = $set;
                break;
            }
            if ($chosen === null) {
                $chosen = $set;
            }
        }

        if ($chosen === null) {
            return null;
        }

        $unit = trim((string)($chosen['unit_label'] ?? ''));
        if ($unit === '') {
            $unit = trim((string)($chosen['estimate_set_name'] ?? ''));
        }

        return [
            'set_id' => (int)($chosen['set_id'] ?? 0),
            'total' => (float)($chosen['total_value'] ?? 0),
            'completed' => (float)($chosen['completed_value'] ?? 0),
            'unit_label' => $unit,
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    public function summaryByModuleId(int $moduleId, array $filters = []): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT
                    es.id AS set_id,
                    es.public_id AS estimate_set_public_id,
                    es.name AS estimate_set_name,
                    es.estimate_type,
                    es.unit_label,
                    COUNT(DISTINCT t.id) AS tasks_total,
                    COUNT(DISTINCT te.id) AS tasks_estimated,
                    COALESCE(SUM(te.numeric_value), 0) AS total_value,
                    COALESCE(SUM(CASE WHEN t.status_code IN ('done','closed','cancelled') THEN te.numeric_value ELSE 0 END), 0) AS completed_value,
                    COALESCE(SUM(CASE WHEN t.status_code NOT IN ('done','closed','cancelled') THEN te.numeric_value ELSE 0 END), 0) AS open_value
                FROM project_module_tasks pmt
                INNER JOIN tasks t ON t.id = pmt.task_id AND t.deleted_at IS NULL
                INNER JOIN estimate_sets es ON (es.scope_type = 'global' OR (es.scope_type = 'project' AND es.project_id = t.project_id))
                LEFT JOIN task_estimates te ON te.task_id = t.id AND te.estimate_set_id = es.id AND te.deleted_at IS NULL
                WHERE pmt.module_id = :module_id AND pmt.deleted_at IS NULL
                GROUP BY es.id, es.public_id, es.name, es.estimate_type, es.unit_label
                ORDER BY es.sort_order ASC"
            );
            $stmt->execute(['module_id' => $moduleId]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($items as &$item) {
                $item['tasks_total'] = (int)$item['tasks_total'];
                $item['tasks_estimated'] = (int)$item['tasks_estimated'];
                $item['total_value'] = (float)$item['total_value'];
                $item['completed_value'] = (float)$item['completed_value'];
                $item['open_value'] = (float)$item['open_value'];
            }
            unset($item);
            return $items;
        } catch (\Throwable $e) {
            error_log('[TaskEstimateRepository::summaryByModuleId] ' . $e->getMessage());
            return [];
        }
    }
}
