<?php
declare(strict_types=1);

namespace Api\Model\Comment;

use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Support\Ulid;
use PDO;

final class CommentDraftRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function getByTaskAndUser(string $taskPublicId, int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('comment_drafts cd')
            ->join('tasks t', 't.id', '=', 'cd.task_id')
            ->join('users u', 'u.id', '=', 'cd.user_id')
            ->select([
                'cd.public_id',
                'cd.body',
                'cd.created_at',
                'cd.updated_at',
                't.public_id AS task_public_id',
                'u.public_id AS user_public_id',
            ])
            ->where('t.public_id', '=', $taskPublicId)
            ->where('cd.user_id', '=', $userId)
            ->first();
    }

    public function upsert(string $taskPublicId, int $userId, string $body): ?array
    {
        $taskId = $this->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return null;
        }

        $existing = $this->getByTaskAndUser($taskPublicId, $userId);
        if ($existing) {
            (new QueryBuilder($this->pdo))
                ->from('comment_drafts')
                ->where('public_id', '=', (string)$existing['public_id'])
                ->update([
                    'body' => $body,
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ]);

            return $this->getByTaskAndUser($taskPublicId, $userId);
        }

        $now = gmdate('Y-m-d H:i:s');
        (new QueryBuilder($this->pdo))
            ->from('comment_drafts')
            ->insert([
            'public_id' => Ulid::generate('drf'),
            'user_id' => $userId,
            'task_id' => $taskId,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->getByTaskAndUser($taskPublicId, $userId);
    }

    public function deleteByTaskAndUser(string $taskPublicId, int $userId): bool
    {
        $taskId = $this->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('comment_drafts')
            ->where('task_id', '=', $taskId)
            ->where('user_id', '=', $userId)
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
        $id = $row['id'] ?? false;

        return $id !== false ? (int)$id : null;
    }
}
