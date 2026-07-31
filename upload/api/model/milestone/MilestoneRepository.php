<?php
declare(strict_types=1);

namespace Api\Model\Milestone;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class MilestoneRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByProjectPublicId(string $projectPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('milestones m')
            ->join('projects p', 'p.id', '=', 'm.project_id')
            ->select([
                'm.public_id',
                'm.title',
                'm.due_at',
                'm.status',
                'm.created_at',
                'm.updated_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->where('p.public_id', '=', $projectPublicId)
            ->orderByRaw('m.due_at IS NULL ASC')
            ->orderBy('m.due_at', 'ASC')
            ->orderBy('m.created_at', 'ASC')
            ->get();
    }

    public function listByProjectPublicIds(array $projectPublicIds): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('milestones m')
            ->join('projects p', 'p.id', '=', 'm.project_id')
            ->select([
                'm.public_id',
                'm.title',
                'm.due_at',
                'm.status',
                'm.created_at',
                'm.updated_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->whereIn('p.public_id', $projectPublicIds)
            ->orderByRaw('m.due_at IS NULL ASC')
            ->orderBy('m.due_at', 'ASC')
            ->orderBy('m.created_at', 'ASC')
            ->get();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('milestones m')
            ->join('projects p', 'p.id', '=', 'm.project_id')
            ->select([
                'm.*',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
            ])
            ->where('m.public_id', '=', $publicId)
            ->first();
    }

    public function create(string $projectPublicId, string $title, ?string $dueAt, string $status): array
    {
        $projectId = $this->projectIdByPublicId($projectPublicId);
        if ($projectId === null) {
            return [];
        }

        $publicId = Ulid::generate('mls');
        $now = gmdate('Y-m-d H:i:s');

        (new QueryBuilder($this->pdo))
            ->from('milestones')
            ->insert([
            'public_id' => $publicId,
            'project_id' => $projectId,
            'title' => $title,
            'due_at' => $dueAt,
            'status' => $status,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        return (new QueryBuilder($this->pdo))
            ->from('milestones')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('milestones')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    private function projectIdByPublicId(string $projectPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['id'])
            ->where('public_id', '=', $projectPublicId)
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }
}
