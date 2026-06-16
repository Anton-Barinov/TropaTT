<?php
declare(strict_types=1);

namespace Api\Model\Intake;

use PDO;

final class IntakeItemRepository
{
    private PDO $db;

    /** @var array<string, string> */
    private array $sortWhitelist = [
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'due_at' => 'due_at',
        'snoozed_until' => 'snoozed_until',
        'priority_code' => 'priority_code',
        'status' => 'status',
        'title' => 'title',
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

        $where = ['ii.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'ii.status = :status';
            $params['status'] = (string)$filters['status'];
        }

        if (!empty($filters['project_public_id'])) {
            $where[] = 'p.public_id = :project_public_id';
            $params['project_public_id'] = (string)$filters['project_public_id'];
        }

        if (!empty($filters['client_public_id'])) {
            $where[] = 'cl.public_id = :client_public_id';
            $params['client_public_id'] = (string)$filters['client_public_id'];
        }

        if (!empty($filters['contact_public_id'])) {
            $where[] = 'co.public_id = :contact_public_id';
            $params['contact_public_id'] = (string)$filters['contact_public_id'];
        }

        if (!empty($filters['assignee_user_id'])) {
            $where[] = 'ii.assignee_user_id = :assignee_user_id';
            $params['assignee_user_id'] = (int)$filters['assignee_user_id'];
        }

        if (!empty($filters['creator_user_id'])) {
            $where[] = 'ii.creator_user_id = :creator_user_id';
            $params['creator_user_id'] = (int)$filters['creator_user_id'];
        }

        if (!empty($filters['source_type'])) {
            $where[] = 'ii.source_type = :source_type';
            $params['source_type'] = (string)$filters['source_type'];
        }

        if (!empty($filters['priority_code'])) {
            $where[] = 'ii.priority_code = :priority_code';
            $params['priority_code'] = (string)$filters['priority_code'];
        }

        if (!empty($filters['created_from'])) {
            $where[] = 'ii.created_at >= :created_from';
            $params['created_from'] = (string)$filters['created_from'];
        }

        if (!empty($filters['created_to'])) {
            $where[] = 'ii.created_at <= :created_to';
            $params['created_to'] = (string)$filters['created_to'];
        }

        if (!empty($filters['due_from'])) {
            $where[] = 'ii.due_at >= :due_from';
            $params['due_from'] = (string)$filters['due_from'];
        }

        if (!empty($filters['due_to'])) {
            $where[] = 'ii.due_at <= :due_to';
            $params['due_to'] = (string)$filters['due_to'];
        }

        $snoozedMode = (string)($filters['snoozed_mode'] ?? '');
        if ($snoozedMode === 'active') {
            $where[] = '(ii.snoozed_until IS NULL OR ii.snoozed_until <= :snoozed_now)';
            $params['snoozed_now'] = gmdate('Y-m-d H:i:s');
        } elseif ($snoozedMode === 'future') {
            $where[] = '(ii.snoozed_until IS NOT NULL AND ii.snoozed_until > :snoozed_future)';
            $params['snoozed_future'] = gmdate('Y-m-d H:i:s');
        }

        if (!empty($filters['q'])) {
            $q = '%' . str_replace('%', '\\%', (string)$filters['q']) . '%';
            $where[] = '(ii.title LIKE :q_title OR ii.description LIKE :q_desc OR ii.source_ref LIKE :q_ref OR ii.source_email LIKE :q_email OR ii.external_id LIKE :q_ext)';
            $params['q_title'] = $q;
            $params['q_desc'] = $q;
            $params['q_ref'] = $q;
            $params['q_email'] = $q;
            $params['q_ext'] = $q;
        }

        $sortCol = 'created_at';
        $sortDir = 'DESC';

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

        $fromClause = 'FROM intake_items ii
            LEFT JOIN projects p ON p.id = ii.project_id
            LEFT JOIN counterparties cl ON cl.id = ii.client_id
            LEFT JOIN contacts co ON co.id = ii.contact_id
            LEFT JOIN users assignee ON assignee.id = ii.assignee_user_id
            LEFT JOIN users creator ON creator.id = ii.creator_user_id
            LEFT JOIN tasks accepted_task ON accepted_task.id = ii.accepted_task_id AND accepted_task.deleted_at IS NULL
            LEFT JOIN intake_items dii ON dii.id = ii.duplicate_intake_item_id AND dii.deleted_at IS NULL
            LEFT JOIN tasks dt ON dt.id = ii.duplicate_task_id AND dt.deleted_at IS NULL';

        $countStmt = $this->db->prepare("SELECT COUNT(*) {$fromClause} WHERE {$whereClause}");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $selectSql = "SELECT ii.id, ii.public_id,
            ii.project_id, p.public_id AS project_public_id, p.title AS project_title,
            ii.client_id, cl.public_id AS client_public_id, cl.title AS client_name,
            ii.contact_id, co.public_id AS contact_public_id, co.full_name AS contact_name,
            ii.title, ii.description,
            ii.status, ii.priority_code,
            ii.source_type, ii.source_ref, ii.source_email, ii.external_source, ii.external_id, ii.extra_json,
            ii.due_at, ii.snoozed_until,
            ii.assignee_user_id, assignee.login AS assignee_name,
            ii.creator_user_id, creator.login AS creator_name,
            accepted_task.public_id AS accepted_task_public_id,
            dii.public_id AS duplicate_intake_item_public_id,
            dt.public_id AS duplicate_task_public_id,
            ii.resolution_note, ii.resolved_by_user_id, ii.resolved_at,
            ii.row_version,
            ii.created_at, ii.updated_at
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
            $item['client_id'] = $item['client_id'] !== null ? (int)$item['client_id'] : null;
            $item['contact_id'] = $item['contact_id'] !== null ? (int)$item['contact_id'] : null;
            $item['assignee_user_id'] = $item['assignee_user_id'] !== null ? (int)$item['assignee_user_id'] : null;
            $item['creator_user_id'] = (int)$item['creator_user_id'];
            $item['resolved_by_user_id'] = $item['resolved_by_user_id'] !== null ? (int)$item['resolved_by_user_id'] : null;
            $item['row_version'] = (int)$item['row_version'];
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
            "SELECT ii.*,
                p.public_id AS project_public_id, p.title AS project_title,
                cl.public_id AS client_public_id, cl.title AS client_name,
                co.public_id AS contact_public_id, co.full_name AS contact_name,
                assignee.login AS assignee_name,
                creator.login AS creator_name,
                accepted_task.public_id AS accepted_task_public_id,
                dii.public_id AS duplicate_intake_item_public_id,
                dt.public_id AS duplicate_task_public_id
            FROM intake_items ii
            LEFT JOIN projects p ON p.id = ii.project_id
            LEFT JOIN counterparties cl ON cl.id = ii.client_id
            LEFT JOIN contacts co ON co.id = ii.contact_id
            LEFT JOIN users assignee ON assignee.id = ii.assignee_user_id
            LEFT JOIN users creator ON creator.id = ii.creator_user_id
            LEFT JOIN tasks accepted_task ON accepted_task.id = ii.accepted_task_id AND accepted_task.deleted_at IS NULL
            LEFT JOIN intake_items dii ON dii.id = ii.duplicate_intake_item_id AND dii.deleted_at IS NULL
            LEFT JOIN tasks dt ON dt.id = ii.duplicate_task_id AND dt.deleted_at IS NULL
            WHERE ii.public_id = :public_id"
        );
        $stmt->execute(['public_id' => $publicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['id'] = (int)$row['id'];
        $row['project_id'] = $row['project_id'] !== null ? (int)$row['project_id'] : null;
        $row['client_id'] = $row['client_id'] !== null ? (int)$row['client_id'] : null;
        $row['contact_id'] = $row['contact_id'] !== null ? (int)$row['contact_id'] : null;
        $row['assignee_user_id'] = $row['assignee_user_id'] !== null ? (int)$row['assignee_user_id'] : null;
        $row['creator_user_id'] = (int)$row['creator_user_id'];
        $row['resolved_by_user_id'] = $row['resolved_by_user_id'] !== null ? (int)$row['resolved_by_user_id'] : null;
        $row['accepted_task_id'] = $row['accepted_task_id'] !== null ? (int)$row['accepted_task_id'] : null;
        $row['duplicate_intake_item_id'] = $row['duplicate_intake_item_id'] !== null ? (int)$row['duplicate_intake_item_id'] : null;
        $row['duplicate_task_id'] = $row['duplicate_task_id'] !== null ? (int)$row['duplicate_task_id'] : null;
        $row['row_version'] = (int)$row['row_version'];
        return $row;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM intake_items WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO intake_items (
                public_id, project_id, client_id, contact_id,
                title, description,
                status, priority_code,
                source_type, source_ref, source_email, external_source, external_id, extra_json,
                due_at, snoozed_until,
                assignee_user_id, creator_user_id,
                row_version, created_at, updated_at
            ) VALUES (
                :public_id, :project_id, :client_id, :contact_id,
                :title, :description,
                :status, :priority_code,
                :source_type, :source_ref, :source_email, :external_source, :external_id, :extra_json,
                :due_at, :snoozed_until,
                :assignee_user_id, :creator_user_id,
                1, :created_at, :updated_at
            )"
        );

        $stmt->execute([
            'public_id' => $data['public_id'],
            'project_id' => $data['project_id'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'contact_id' => $data['contact_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'priority_code' => $data['priority_code'] ?? null,
            'source_type' => $data['source_type'] ?? 'manual',
            'source_ref' => $data['source_ref'] ?? null,
            'source_email' => $data['source_email'] ?? null,
            'external_source' => $data['external_source'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'extra_json' => $data['extra_json'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'snoozed_until' => $data['snoozed_until'] ?? null,
            'assignee_user_id' => $data['assignee_user_id'] ?? null,
            'creator_user_id' => $data['creator_user_id'],
            'created_at' => $data['created_at'] ?? gmdate('Y-m-d H:i:s'),
            'updated_at' => $data['updated_at'] ?? gmdate('Y-m-d H:i:s'),
        ]);

        return $this->findByPublicId($data['public_id']) ?? $data;
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

        $sql = "UPDATE intake_items SET " . implode(', ', $fields) . " WHERE public_id = :public_id";
        $stmt = $this->db->prepare($sql);
        $params = $set;
        $params['public_id'] = $publicId;
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    public function softDeleteByPublicId(string $publicId, string $deletedAt): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE intake_items SET deleted_at = :deleted_at, updated_at = :updated_at WHERE public_id = :public_id AND deleted_at IS NULL"
        );
        $stmt->execute([
            'deleted_at' => $deletedAt,
            'updated_at' => $deletedAt,
            'public_id' => $publicId,
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

    public function projectIdByPublicId(string $projectPublicId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM projects WHERE public_id = :public_id");
        $stmt->execute(['public_id' => $projectPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function clientIdByPublicId(string $clientPublicId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM counterparties WHERE public_id = :public_id");
        $stmt->execute(['public_id' => $clientPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function contactIdByPublicId(string $contactPublicId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM contacts WHERE public_id = :public_id");
        $stmt->execute(['public_id' => $contactPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }

    public function userIdByPublicId(string $userPublicId): ?int
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE public_id = :public_id");
        $stmt->execute(['public_id' => $userPublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (int)$row['id'] : null;
    }
}
