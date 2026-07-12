<?php
declare(strict_types=1);

namespace Api\Model\Approval;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ApprovalRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listRequests(array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildRequestsListQuery($filters)->count();
        $items = $this->buildRequestsListQuery($filters)
            ->select([
                'ar.id',
                'ar.public_id',
                'ar.entity_type',
                'ar.entity_public_id',
                'ar.title',
                'ar.comment',
                'ar.requester_user_id',
                'ar.status',
                'ar.created_at',
                'ar.updated_at',
                'ru.public_id AS requester_public_id',
                'ru.login AS requester_login',
                'ru.full_name AS requester_full_name',
                '(SELECT COUNT(*) FROM approval_steps aps WHERE aps.request_id = ar.id) AS reviewers_total',
                "(SELECT COUNT(*) FROM approval_steps aps WHERE aps.request_id = ar.id AND aps.status = 'pending') AS pending_steps",
                "(SELECT COUNT(*) FROM approval_steps aps WHERE aps.request_id = ar.id AND aps.status = 'approved') AS approved_steps",
                "(SELECT COUNT(*) FROM approval_steps aps WHERE aps.request_id = ar.id AND aps.status = 'rejected') AS rejected_steps",
            ])
            ->orderBy('ar.updated_at', 'DESC')
            ->orderBy('ar.public_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildRequestsListQuery(array $filters): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('approval_requests ar')
            ->join('users ru', 'ru.id', '=', 'ar.requester_user_id');

        if (!empty($filters['status'])) {
            $query->where('ar.status', '=', (string)$filters['status']);
        }

        if (!empty($filters['entity_type'])) {
            $query->where('ar.entity_type', '=', (string)$filters['entity_type']);
        }

        if (!empty($filters['entity_public_id'])) {
            $query->where('ar.entity_public_id', '=', (string)$filters['entity_public_id']);
        }

        if (!empty($filters['requester_public_id'])) {
            $query->where('ru.public_id', '=', (string)$filters['requester_public_id']);
        }

        if (!empty($filters['reviewer_public_id'])) {
            $query->whereRaw(
                'EXISTS (SELECT 1
                         FROM approval_steps aps
                         INNER JOIN users rv ON rv.id = aps.reviewer_user_id
                         WHERE aps.request_id = ar.id AND rv.public_id = ?)',
                [(string)$filters['reviewer_public_id']]
            );
        }

        if (!empty($filters['involved_user_public_id'])) {
            $involved = (string)$filters['involved_user_public_id'];
            $query->whereRaw(
                '(ru.public_id = ?
                  OR EXISTS (SELECT 1
                             FROM approval_steps aps
                             INNER JOIN users rv ON rv.id = aps.reviewer_user_id
                             WHERE aps.request_id = ar.id AND rv.public_id = ?))',
                [$involved, $involved]
            );
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim((string)$filters['search']) . '%';
            $query->whereRaw(
                '(ar.entity_public_id LIKE ? OR ru.login LIKE ? OR ru.full_name LIKE ?)',
                [$search, $search, $search]
            );
        }

        return $query;
    }

    public function findRequestByPublicId(string $publicId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('approval_requests ar')
            ->join('users ru', 'ru.id', '=', 'ar.requester_user_id')
            ->select([
                'ar.id',
                'ar.public_id',
                'ar.entity_type',
                'ar.entity_public_id',
                'ar.title',
                'ar.comment',
                'ar.requester_user_id',
                'ar.status',
                'ar.created_at',
                'ar.updated_at',
                'ru.public_id AS requester_public_id',
                'ru.login AS requester_login',
                'ru.full_name AS requester_full_name',
            ])
            ->where('ar.public_id', '=', $publicId)
            ->first();

        return $row ?: null;
    }

    public function createRequest(array $payload): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('approval_requests')
            ->insertGetId($payload);
    }

    public function updateRequestById(int $requestId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('approval_requests')
            ->where('id', '=', $requestId)
            ->update($set) > 0;
    }

    public function createStep(array $payload): int
    {
        return (new QueryBuilder($this->pdo))
            ->from('approval_steps')
            ->insertGetId($payload);
    }

    public function findStepByRequestIdAndReviewerId(int $requestId, int $reviewerUserId): ?array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('approval_steps')
            ->select(['id', 'public_id', 'request_id', 'reviewer_user_id', 'status', 'comment', 'created_at', 'updated_at'])
            ->where('request_id', '=', $requestId)
            ->where('reviewer_user_id', '=', $reviewerUserId)
            ->first();

        return $row ?: null;
    }

    public function updateStepById(int $stepId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('approval_steps')
            ->where('id', '=', $stepId)
            ->update($set) > 0;
    }

    public function stepsByRequestId(int $requestId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('approval_steps aps')
            ->join('users rv', 'rv.id', '=', 'aps.reviewer_user_id')
            ->select([
                'aps.public_id',
                'aps.status',
                'aps.comment',
                'aps.created_at',
                'aps.updated_at',
                'rv.public_id AS reviewer_public_id',
                'rv.login AS reviewer_login',
                'rv.full_name AS reviewer_full_name',
            ])
            ->where('aps.request_id', '=', $requestId)
            ->orderBy('aps.created_at', 'ASC')
            ->orderBy('aps.public_id', 'ASC')
            ->get();
    }

    public function stepStatsByRequestId(int $requestId): array
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('approval_steps')
            ->select([
                'COUNT(*) AS total',
                "SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_steps",
                "SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_steps",
                "SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_steps",
            ])
            ->where('request_id', '=', $requestId)
            ->first() ?: [];

        return [
            'total' => (int)($row['total'] ?? 0),
            'pending_steps' => (int)($row['pending_steps'] ?? 0),
            'approved_steps' => (int)($row['approved_steps'] ?? 0),
            'rejected_steps' => (int)($row['rejected_steps'] ?? 0),
        ];
    }
}
