<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Comment\CommentRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Support\Ulid;

final class CommentService
{
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly TaskRepository $tasks,
        private readonly ?NotificationService $notifications = null,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?TaskActivityService $activity = null
    )
    {
    }

    public function listByTask(string $taskPublicId, array $filters): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));

        [$items, $total, $page, $limit] = $this->comments->listByTaskPublicId($taskPublicId, $page, $limit);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function createByTask(string $taskPublicId, array $input, int $authorUserId): bool
    {
        $commentPublicId = Ulid::generate('cmt');
        $existingParticipants = $this->comments->participantUserIdsByTaskPublicId($taskPublicId);

        $created = $this->comments->createByTaskPublicId($taskPublicId, [
            'public_id' => $commentPublicId,
            'author_user_id' => $authorUserId,
            'body' => trim((string)$input['body']),
            'visibility' => (string)($input['visibility'] ?? 'internal'),
            'created_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        if (!$created) {
            return false;
        }

        $task = $this->tasks->findByPublicId($taskPublicId);
        $comment = $this->comments->findByPublicId($commentPublicId);
        if ($task && $comment) {
            $this->notifications?->notifyTaskCommentCreated($task, $comment, [
                'id' => $authorUserId,
                'public_id' => $comment['author_public_id'] ?? null,
                'full_name' => $comment['author_name'] ?? null,
            ], $existingParticipants);

            $this->activity?->recordCommentAdded($task, $comment, [
                'id' => $authorUserId,
                'public_id' => $comment['author_public_id'] ?? null,
                'full_name' => $comment['author_name'] ?? null,
            ], ['source_type' => 'web']);
        }

        return true;
    }

    /** @return array<string,mixed>|null */
    public function update(string $commentPublicId, array $input, array $actor): ?array
    {
        $comment = $this->comments->findByPublicId($commentPublicId);
        if (!$comment || (string)($comment['deleted_at'] ?? '') !== '') {
            return null;
        }
        if (!$this->canManageComment($comment, $actor)) {
            return null;
        }

        $set = [];
        if (array_key_exists('body', $input)) {
            $set['body'] = trim((string)$input['body']);
        }
        if (array_key_exists('visibility', $input)) {
            $set['visibility'] = (string)$input['visibility'];
        }
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->comments->updateByPublicId($commentPublicId, $set);
        $this->semanticIndex?->removeEntityDocument('comment', $commentPublicId);

        $updatedComment = $this->comments->findByPublicId($commentPublicId);
        if ($updatedComment && isset($updatedComment['task_public_id'])) {
            $task = $this->tasks->findByPublicId((string)$updatedComment['task_public_id']);
            if ($task) {
                $this->activity?->recordCommentUpdated($task, $updatedComment, $actor);
            }
        }

        return $updatedComment;
    }

    public function delete(string $commentPublicId, array $actor): bool
    {
        $comment = $this->comments->findByPublicId($commentPublicId);
        if (!$comment || (string)($comment['deleted_at'] ?? '') !== '') {
            return false;
        }
        if (!$this->canManageComment($comment, $actor)) {
            return false;
        }

        $deleted = $this->comments->softDelete($commentPublicId, gmdate('Y-m-d H:i:s'));
        if ($deleted) {
            $this->semanticIndex?->removeEntityDocument('comment', $commentPublicId);

            $task = $this->tasks->findByPublicId((string)($comment['task_public_id'] ?? ''));
            if ($task) {
                $this->activity?->recordCommentDeleted($task, $comment, $actor);
            }
        }

        return $deleted;
    }

    /** @param array<string,mixed> $comment */
    /** @param array<string,mixed> $actor */
    private function canManageComment(array $comment, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }
        if ((int)($comment['author_user_id'] ?? 0) === $actorId) {
            return true;
        }

        $taskPublicId = (string)($comment['task_public_id'] ?? '');
        if ($taskPublicId === '') {
            return false;
        }

        $task = $this->tasks->findByPublicId($taskPublicId);
        if (!$task) {
            return false;
        }

        return (int)($task['creator_user_id'] ?? 0) === $actorId
            || (int)($task['assignee_user_id'] ?? 0) === $actorId
            || (int)($task['project_creator_user_id'] ?? 0) === $actorId
            || (int)($task['project_manager_user_id'] ?? 0) === $actorId
            || (int)($task['project_team_manager_user_id'] ?? 0) === $actorId
            || in_array($actorId, $this->decodeTeamMemberIds($task['project_team_member_user_ids'] ?? null), true);
    }

    /** @return int[] */
    private function decodeTeamMemberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }
}
