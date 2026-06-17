<?php
declare(strict_types=1);

namespace Api\Model\Dependency;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class DependencyRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters): array
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('task_dependencies d')
            ->join('tasks tf', 'tf.id', '=', 'd.task_id')
            ->join('tasks td', 'td.id', '=', 'd.depends_on_task_id')
            ->leftJoin('projects pf', 'pf.id', '=', 'tf.project_id')
            ->select([
                'd.public_id',
                'd.dependency_type',
                'd.created_at',
                'tf.public_id AS task_public_id',
                'tf.title AS task_title',
                'tf.task_key AS task_key',
                'tf.status_code AS task_status_code',
                'td.public_id AS depends_on_task_public_id',
                'td.title AS depends_on_task_title',
                'td.task_key AS depends_on_task_key',
                'td.status_code AS depends_on_task_status_code',
                'pf.public_id AS project_public_id',
            ]);

        if (!empty($filters['project_public_id'])) {
            $query->where('pf.public_id', '=', (string)$filters['project_public_id']);
        }

        if (!empty($filters['project_public_ids'])) {
            $ids = is_array($filters['project_public_ids'])
                ? $filters['project_public_ids']
                : explode(',', (string)$filters['project_public_ids']);
            $ids = array_values(array_filter(array_map('trim', $ids)));
            if ($ids !== []) {
                $query->whereIn('pf.public_id', $ids);
            }
        }

        if (!empty($filters['task_public_id'])) {
            $taskPublicId = (string)$filters['task_public_id'];
            $direction = !empty($filters['direction']) ? $filters['direction'] : 'both';

            if ($direction === 'outgoing') {
                $query->where('tf.public_id', '=', $taskPublicId);
            } elseif ($direction === 'incoming') {
                $query->where('td.public_id', '=', $taskPublicId);
            } else {
                $query->whereRaw(
                    '(tf.public_id = ? OR td.public_id = ?)',
                    [$taskPublicId, $taskPublicId]
                );
            }
        }

        return $query
            ->orderBy('d.created_at', 'ASC')
            ->get();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_dependencies d')
            ->join('tasks tf', 'tf.id', '=', 'd.task_id')
            ->join('tasks td', 'td.id', '=', 'd.depends_on_task_id')
            ->leftJoin('projects pf', 'pf.id', '=', 'tf.project_id')
            ->select([
                'd.*',
                'tf.public_id AS task_public_id',
                'td.public_id AS depends_on_task_public_id',
                'pf.public_id AS project_public_id',
            ])
            ->where('d.public_id', '=', $publicId)
            ->first();
    }

    public function create(string $taskPublicId, string $dependsOnTaskPublicId, string $dependencyType): array|string
    {
        $taskId = $this->taskIdByPublicId($taskPublicId);
        $dependsOnTaskId = $this->taskIdByPublicId($dependsOnTaskPublicId);

        if ($taskId === null || $dependsOnTaskId === null) {
            return 'TASK_NOT_FOUND';
        }

        if ($taskId === $dependsOnTaskId) {
            return 'DEPENDENCY_SELF_FORBIDDEN';
        }

        $existing = $this->findByTaskPair($taskId, $dependsOnTaskId);
        if ($existing) {
            return $this->findByPublicId((string)$existing['public_id']) ?? [];
        }

        $publicId = Ulid::generate('dep');

        (new QueryBuilder($this->pdo))
            ->from('task_dependencies')
            ->insert([
            'public_id' => $publicId,
            'task_id' => $taskId,
            'depends_on_task_id' => $dependsOnTaskId,
            'dependency_type' => $dependencyType,
            'created_at' => gmdate('Y-m-d H:i:s'),
            ]);

        return $this->findByPublicId($publicId) ?? [];
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_dependencies')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    private function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('public_id', '=', $taskPublicId)
            ->whereNull('deleted_at')
            ->first();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    private function findByTaskPair(int $taskId, int $dependsOnTaskId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('task_dependencies')
            ->select(['public_id'])
            ->where('task_id', '=', $taskId)
            ->where('depends_on_task_id', '=', $dependsOnTaskId)
            ->first();
    }
}
