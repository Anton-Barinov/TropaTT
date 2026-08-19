<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Comment\CommentRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Security\HtmlSanitizer;
use Api\System\Library\Support\Ulid;

final class CommentService
{
    public function __construct(
        private readonly CommentRepository $comments,
        private readonly TaskRepository $tasks,
        private readonly ?NotificationService $notifications = null,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?TaskActivityService $activity = null,
        private readonly ?HtmlSanitizer $htmlSanitizer = null
    )
    {
    }

    /**
     * @param array<string,mixed> $actor Caller. An external (client portal) user only ever
     *        receives client-facing comments: they may legitimately see the task, but the
     *        internal discussion on it is not theirs to read.
     */
    public function listByTask(string $taskPublicId, array $filters, array $actor = []): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));

        $clientVisibleOnly = !empty((int)($actor['is_external'] ?? 0));

        [$items, $total, $page, $limit] = $this->comments->listByTaskPublicId(
            $taskPublicId,
            $page,
            $limit,
            $clientVisibleOnly
        );
        $items = array_map(fn(array $item): array => $this->sanitizeComment($item), $items);

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

    /**
     * @param array<string,mixed> $actor Caller. A comment written by an external (client
     *        portal) user is always client-visible: letting a guest post an "internal"
     *        comment would hide their own message from them on the next read.
     */
    public function createByTask(string $taskPublicId, array $input, int $authorUserId, array $actor = []): ?array
    {
        unset($input['author_user_id'], $input['created_at']);

        if (!empty((int)($actor['is_external'] ?? 0))) {
            $input['visibility'] = 'client';
        }

        return $this->createByTaskInternal($taskPublicId, $input, $authorUserId, false);
    }

    /** Used only by trusted migration adapters; never exposed by the public API controllers. */
    public function createByTaskImported(string $taskPublicId, array $input, int $authorUserId): ?array
    {
        return $this->createByTaskInternal($taskPublicId, $input, $authorUserId, true);
    }

    private function createByTaskInternal(string $taskPublicId, array $input, int $authorUserId, bool $allowHistorical): ?array
    {
        $body = $this->sanitizeBody((string)($input['body'] ?? ''));
        if ($body === '') {
            return null;
        }

        $commentPublicId = Ulid::generate('cmt');
        $existingParticipants = $this->comments->participantUserIdsByTaskPublicId($taskPublicId);
        $createdAt = gmdate('Y-m-d H:i:s');
        if ($allowHistorical && !empty($input['created_at'])) {
            $parsedCreatedAt = strtotime((string)$input['created_at']);
            if ($parsedCreatedAt !== false) $createdAt = gmdate('Y-m-d H:i:s', $parsedCreatedAt);
        }
        $effectiveAuthorId = $allowHistorical ? (int)($input['author_user_id'] ?? $authorUserId) : $authorUserId;
        if ($effectiveAuthorId <= 0) $effectiveAuthorId = $authorUserId;

        $created = $this->comments->createByTaskPublicId($taskPublicId, [
            'public_id' => $commentPublicId,
            'author_user_id' => $effectiveAuthorId,
            'body' => $body,
            'visibility' => (string)($input['visibility'] ?? 'internal'),
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        if (!$created) {
            return null;
        }

        $task = $this->tasks->findByPublicId($taskPublicId);
        $comment = $this->comments->findByPublicId($commentPublicId);
        if ($task && $comment) {
            $this->notifications?->notifyTaskCommentCreated($task, $comment, [
                'id' => $effectiveAuthorId,
                'public_id' => $comment['author_public_id'] ?? null,
                'full_name' => $comment['author_name'] ?? null,
            ], $existingParticipants);

            $this->activity?->recordCommentAdded($task, $comment, [
                'id' => $effectiveAuthorId,
                'public_id' => $comment['author_public_id'] ?? null,
                'full_name' => $comment['author_name'] ?? null,
            ], ['source_type' => 'web']);
        }

        return $comment ? $this->sanitizeComment($comment) : null;
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
            $body = $this->sanitizeBody((string)$input['body']);
            if ($body === '' || mb_strlen($body) > 8000) {
                return null;
            }
            $set['body'] = $body;
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

        return $updatedComment ? $this->sanitizeComment($updatedComment) : null;
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
    private function sanitizeComment(array $comment): array
    {
        $comment['body'] = $this->sanitizeBody((string)($comment['body'] ?? ''));
        return $comment;
    }

    private function sanitizeBody(string $body): string
    {
        $sanitized = ($this->htmlSanitizer ?? new HtmlSanitizer())->sanitize($body);
        return mb_strlen($sanitized) <= 8000 ? $sanitized : '';
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
