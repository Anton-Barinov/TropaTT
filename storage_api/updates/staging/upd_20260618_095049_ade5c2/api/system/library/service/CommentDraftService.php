<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Comment\CommentDraftRepository;

final class CommentDraftService
{
    public function __construct(
        private readonly CommentDraftRepository $drafts,
        private readonly TaskService $tasks
    ) {
    }

    public function get(string $taskPublicId, array $actor): array|string|null
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return 'TASK_NOT_FOUND';
        }

        return $this->drafts->getByTaskAndUser($taskPublicId, (int)($actor['id'] ?? 0));
    }

    public function save(string $taskPublicId, string $body, array $actor): array|string|null
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return 'TASK_NOT_FOUND';
        }

        return $this->drafts->upsert($taskPublicId, (int)($actor['id'] ?? 0), $body);
    }

    public function clear(string $taskPublicId, array $actor): bool|string
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return 'TASK_NOT_FOUND';
        }

        return $this->drafts->deleteByTaskAndUser($taskPublicId, (int)($actor['id'] ?? 0));
    }
}
