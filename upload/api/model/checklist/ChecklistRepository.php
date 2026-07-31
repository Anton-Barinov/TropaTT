<?php
declare(strict_types=1);

namespace Api\Model\Checklist;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class ChecklistRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function listByTaskPublicId(string $taskPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('checklists c')
            ->join('tasks t', 't.id', '=', 'c.task_id')
            ->select([
                'c.public_id',
                'c.task_id',
                'c.title',
                'c.created_at',
                'c.updated_at',
            ])
            ->where('t.public_id', '=', $taskPublicId)
            ->orderBy('c.created_at', 'ASC')
            ->get();
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('checklists c')
            ->join('tasks t', 't.id', '=', 'c.task_id')
            ->select(['c.*', 't.public_id AS task_public_id'])
            ->where('c.public_id', '=', $publicId)
            ->first();
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('checklists')
            ->insert($payload);
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('checklists')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('checklists')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function taskIdByPublicId(string $taskPublicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('tasks')
            ->select(['id'])
            ->where('public_id', '=', $taskPublicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }

    public function listItemsByChecklistPublicId(string $checklistPublicId): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('checklist_items i')
            ->join('checklists c', 'c.id', '=', 'i.checklist_id')
            ->select([
                'i.public_id',
                'i.checklist_id',
                'i.title',
                'i.is_done',
                'i.sort_order',
                'i.created_at',
                'i.updated_at',
            ])
            ->where('c.public_id', '=', $checklistPublicId)
            ->orderBy('i.sort_order', 'ASC')
            ->orderBy('i.created_at', 'ASC')
            ->get();
    }

    public function findItemByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('checklist_items i')
            ->join('checklists c', 'c.id', '=', 'i.checklist_id')
            ->join('tasks t', 't.id', '=', 'c.task_id')
            ->select(['i.*', 'c.public_id AS checklist_public_id', 't.public_id AS task_public_id'])
            ->where('i.public_id', '=', $publicId)
            ->first();
    }

    public function createItem(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('checklist_items')
            ->insert($payload);
    }

    public function updateItemByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('checklist_items')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteItemByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('checklist_items')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    public function checklistIdByPublicId(string $publicId): ?int
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('checklists')
            ->select(['id'])
            ->where('public_id', '=', $publicId)
            ->first();
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }
}
