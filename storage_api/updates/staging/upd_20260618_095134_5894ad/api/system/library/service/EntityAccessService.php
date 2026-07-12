<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Comment\CommentRepository;

final class EntityAccessService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly ProjectService $projects,
        private readonly CommentRepository $comments
    ) {
    }

    public function canAccess(string $entityType, string $entityPublicId, array $actor): bool
    {
        $entityType = strtolower(trim($entityType));
        if ($entityType === '' || $entityPublicId === '') {
            return false;
        }

        if ($entityType === 'task') {
            return $this->tasks->get($entityPublicId, $actor) !== null;
        }

        if ($entityType === 'project') {
            return $this->projects->get($entityPublicId, $actor) !== null;
        }

        if ($entityType === 'comment') {
            $comment = $this->comments->findByPublicId($entityPublicId);
            if (!$comment || (string)($comment['deleted_at'] ?? '') !== '') {
                return false;
            }

            $taskPublicId = (string)($comment['task_public_id'] ?? '');
            if ($taskPublicId === '') {
                return false;
            }

            return $this->tasks->get($taskPublicId, $actor) !== null;
        }

        return false;
    }
}
