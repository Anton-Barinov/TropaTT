<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Notification\NotificationRepository;
use Api\Model\Common\UserRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Language\LanguageManager;
use Api\System\Library\Language\TranslatableTrait;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class NotificationService
{
    use TranslatableTrait;

    public function __construct(
        private readonly NotificationRepository $notifications,
        private readonly UserRepository $users,
        private readonly JsonLogger $logger,
        private readonly ?TaskRepository $tasks = null,
        private readonly ?NotificationPushService $push = null,
        ?LanguageManager $lang = null
    ) {
        $this->lang = $lang ?? new LanguageManager(__DIR__ . '/../../language');
    }

    public function list(array $filters, array $actor): array
    {
        $targetUserId = $this->resolveTargetUserId($filters, $actor);
        [$items, $total, $page, $limit] = $this->notifications->listByUser($targetUserId, $filters);
        $items = array_map(fn(array $item): array => $this->normalizeItem($item), $items);

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

    public function create(array $input, array $actor)
    {
        $targetUserId = $this->resolveTargetUserId($input, $actor);
        $publicId = $this->createRecordForUser($targetUserId, $input, $actor);

        $this->logger->audit([
            'action' => 'notification_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'notification',
            'entity_public_id' => $publicId,
            'target_user_id' => $targetUserId,
        ]);

        $item = $this->notifications->findByPublicIdForUser($publicId, $targetUserId);
        return $item ? $this->normalizeItem($item) : ['public_id' => $publicId];
    }

    /** @param array<int,int> $userIds */
    public function notifyUsers(array $userIds, array $payload, ?int $skipUserId = null): int
    {
        $normalizedIds = array_values(array_unique(array_filter(
            array_map('intval', $userIds),
            static fn(int $userId): bool => $userId > 0
        )));

        if ($skipUserId !== null && $skipUserId > 0) {
            $normalizedIds = array_values(array_filter(
                $normalizedIds,
                static fn(int $userId): bool => $userId !== $skipUserId
            ));
        }

        if ($normalizedIds === []) {
            return 0;
        }

        $created = 0;
        foreach ($normalizedIds as $userId) {
            $this->createRecordForUser($userId, $payload);
            $created++;
        }

        if ($created > 0) {
            $this->logger->audit([
                'action' => 'notification_dispatched',
                'actor_public_id' => $payload['actor_public_id'] ?? null,
                'entity_type' => (string)($payload['entity_type'] ?? 'notification'),
                'entity_public_id' => (string)($payload['entity_public_id'] ?? '*'),
                'action_code' => (string)($payload['action_code'] ?? 'system_event'),
                'created_count' => $created,
                'category' => (string)($payload['category'] ?? 'system'),
            ]);
        }

        return $created;
    }

    /** @param array<string,mixed> $task */
    /** @param array<string,mixed> $actor */
    public function notifyTaskCreated(array $task, array $actor): int
    {
        $taskPublicId = (string)($task['public_id'] ?? '');
        if ($taskPublicId === '') {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $taskTitle = $this->taskTitle($task);
        $actorName = $this->actorName($actor);
        $taskLink = $this->taskLink($taskPublicId);
        $assigneeUserId = (int)($task['assignee_user_id'] ?? 0);
        $created = 0;

        if ($assigneeUserId > 0 && $assigneeUserId !== $actorUserId) {
            $created += $this->notifyUsers([$assigneeUserId], [
                'category' => 'assignments',
                'title' => $this->t('notification/messages.task_assigned_to_you'),
                'body' => $actorName . ' ' . $this->t('notification/messages.assigned_task_to_you') . ' "' . $taskTitle . '".',
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => 'task_assigned',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_title' => $taskTitle,
                    'project_public_id' => $task['project_public_id'] ?? null,
                    'project_title' => $task['project_title'] ?? null,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        $otherStakeholders = array_values(array_filter(
            $this->taskStakeholderUserIds($task),
            static fn(int $userId): bool => $userId !== $assigneeUserId
        ));

        if ($otherStakeholders !== []) {
            $created += $this->notifyUsers($otherStakeholders, [
                'category' => 'tasks',
                'title' => $this->t('notification/messages.task_created'),
                'body' => $actorName . ' ' . $this->t('notification/messages.created_task') . ' "' . $taskTitle . '".',
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => 'task_created',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_title' => $taskTitle,
                    'project_public_id' => $task['project_public_id'] ?? null,
                    'project_title' => $task['project_title'] ?? null,
                    'assignee_user_id' => $assigneeUserId > 0 ? $assigneeUserId : null,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        return $created;
    }

    /** @param array<string,mixed> $before */
    /** @param array<string,mixed> $after */
    /** @param array<string,mixed> $actor */
    public function notifyTaskStatusChanged(array $before, array $after, array $actor): int
    {
        $taskPublicId = (string)($after['public_id'] ?? $before['public_id'] ?? '');
        if ($taskPublicId === '') {
            return 0;
        }

        $oldStatus = (string)($before['status_code'] ?? '');
        $newStatus = (string)($after['status_code'] ?? '');
        if ($oldStatus === $newStatus) {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $taskTitle = $this->taskTitle($after !== [] ? $after : $before);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers($this->taskStakeholderUserIds($after !== [] ? $after : $before), [
            'category' => 'tasks',
            'title' => $this->t('notification/messages.task_status_changed'),
            'body' => $actorName . ' ' . $this->t('notification/messages.changed_task_status') . ' "' . $taskTitle . '" ' . $this->t('notification/messages.from') . ' "' . $this->statusLabel($oldStatus) . '" ' . $this->t('notification/messages.to') . ' "' . $this->statusLabel($newStatus) . '".',
            'entity_type' => 'task',
            'entity_public_id' => $taskPublicId,
            'action_code' => 'task_status_changed',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $this->taskLink($taskPublicId),
            'payload' => [
                'task_title' => $taskTitle,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'old_status_label' => $this->statusLabel($oldStatus),
                'new_status_label' => $this->statusLabel($newStatus),
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $before */
    /** @param array<string,mixed> $after */
    /** @param array<string,mixed> $actor */
    public function notifyTaskAssignmentChanged(array $before, array $after, array $actor): int
    {
        $taskPublicId = (string)($after['public_id'] ?? $before['public_id'] ?? '');
        if ($taskPublicId === '') {
            return 0;
        }

        $beforeAssigneeId = (int)($before['assignee_user_id'] ?? 0);
        $afterAssigneeId = (int)($after['assignee_user_id'] ?? 0);
        if ($beforeAssigneeId === $afterAssigneeId) {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);
        $taskTitle = $this->taskTitle($after !== [] ? $after : $before);
        $taskLink = $this->taskLink($taskPublicId);
        $created = 0;

        if ($afterAssigneeId > 0) {
            $created += $this->notifyUsers([$afterAssigneeId], [
                'category' => 'assignments',
                'title' => $beforeAssigneeId > 0 ? $this->t('notification/messages.task_reassigned_to_you') : $this->t('notification/messages.task_assigned_to_you'),
                'body' => $actorName . ' ' . $this->t('notification/messages.assigned_task_to_you') . ' "' . $taskTitle . '".',
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => $beforeAssigneeId > 0 ? 'task_reassigned' : 'task_assigned',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_title' => $taskTitle,
                    'previous_assignee_user_id' => $beforeAssigneeId > 0 ? $beforeAssigneeId : null,
                    'assignee_user_id' => $afterAssigneeId,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        if ($beforeAssigneeId > 0) {
            $created += $this->notifyUsers([$beforeAssigneeId], [
                'category' => 'assignments',
                'title' => $afterAssigneeId > 0 ? $this->t('notification/messages.unassigned_from_task') : $this->t('notification/messages.task_assignee_removed'),
                'body' => $actorName . ' ' . $this->t('notification.messages.removed_from_task') . ' "' . $taskTitle . '".',
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => $afterAssigneeId > 0 ? 'task_reassigned' : 'task_unassigned',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_title' => $taskTitle,
                    'previous_assignee_user_id' => $beforeAssigneeId,
                    'assignee_user_id' => $afterAssigneeId > 0 ? $afterAssigneeId : null,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        $watchers = array_values(array_filter(
            $this->taskStakeholderUserIds($after !== [] ? $after : $before),
            static fn(int $userId): bool => !in_array($userId, [$beforeAssigneeId, $afterAssigneeId], true)
        ));

        if ($watchers !== []) {
            $created += $this->notifyUsers($watchers, [
                'category' => 'tasks',
                'title' => $this->t('notification/messages.task_assignee_changed'),
                'body' => $actorName . ' ' . $this->t('notification/messages.changed_task_assignee') . ' "' . $taskTitle . '".',
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => $afterAssigneeId > 0 ? ($beforeAssigneeId > 0 ? 'task_reassigned' : 'task_assigned') : 'task_unassigned',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_title' => $taskTitle,
                    'previous_assignee_user_id' => $beforeAssigneeId > 0 ? $beforeAssigneeId : null,
                    'assignee_user_id' => $afterAssigneeId > 0 ? $afterAssigneeId : null,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        return $created;
    }

    /** @param array<string,mixed> $before */
    /** @param array<string,mixed> $after */
    /** @param array<string,mixed> $actor */
    public function notifyTaskDueChanged(array $before, array $after, array $actor): int
    {
        $taskPublicId = (string)($after['public_id'] ?? $before['public_id'] ?? '');
        if ($taskPublicId === '') {
            return 0;
        }

        $beforeDueAt = (string)($before['due_at'] ?? '');
        $afterDueAt = (string)($after['due_at'] ?? '');
        if ($beforeDueAt === $afterDueAt) {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);
        $taskTitle = $this->taskTitle($after !== [] ? $after : $before);

        return $this->notifyUsers($this->taskStakeholderUserIds($after !== [] ? $after : $before), [
            'category' => 'deadlines',
            'title' => $this->t('notification/messages.task_due_changed'),
            'body' => $actorName . ' ' . $this->t('notification/messages.changed_task_due') . ' "' . $taskTitle . '" ' . $this->t('notification/messages.from') . ' ' . $this->dateLabel($beforeDueAt) . ' ' . $this->t('notification/messages.to') . ' ' . $this->dateLabel($afterDueAt) . '.',
            'entity_type' => 'task',
            'entity_public_id' => $taskPublicId,
            'action_code' => 'task_due_changed',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $this->taskLink($taskPublicId),
            'payload' => [
                'task_title' => $taskTitle,
                'old_due_at' => $beforeDueAt !== '' ? $beforeDueAt : null,
                'new_due_at' => $afterDueAt !== '' ? $afterDueAt : null,
                'old_due_label' => $this->dateLabel($beforeDueAt),
                'new_due_label' => $this->dateLabel($afterDueAt),
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $task */
    /** @param array<string,mixed> $comment */
    /** @param array<string,mixed> $actor */
    /** @param array<int,int> $threadParticipantIds */
    public function notifyTaskCommentCreated(array $task, array $comment, array $actor, array $threadParticipantIds = []): int
    {
        $taskPublicId = (string)($task['public_id'] ?? '');
        if ($taskPublicId === '') {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);
        $taskTitle = $this->taskTitle($task);
        $taskLink = $this->taskLink($taskPublicId);
        $commentPublicId = (string)($comment['public_id'] ?? '');
        $snippet = $this->excerpt((string)($comment['body'] ?? ''));
        $created = 0;

        $threadParticipantIds = array_values(array_unique(array_filter(
            array_map('intval', $threadParticipantIds),
            static fn(int $userId): bool => $userId > 0
        )));

        if ($threadParticipantIds !== []) {
            $created += $this->notifyUsers($threadParticipantIds, [
                'category' => 'comments',
                'title' => $this->t('notification/messages.new_comment_reply'),
                'body' => $actorName . ' ' . $this->t('notification/messages.replied_in_task') . ' "' . $taskTitle . '": ' . $snippet,
                'entity_type' => 'comment',
                'entity_public_id' => $commentPublicId !== '' ? $commentPublicId : $taskPublicId,
                'action_code' => 'task_comment_reply',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_public_id' => $taskPublicId,
                    'task_title' => $taskTitle,
                    'comment_public_id' => $commentPublicId !== '' ? $commentPublicId : null,
                    'comment_excerpt' => $snippet,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        $stakeholders = array_values(array_filter(
            $this->taskStakeholderUserIds($task),
            static fn(int $userId): bool => !in_array($userId, $threadParticipantIds, true)
        ));

        if ($stakeholders !== []) {
            $created += $this->notifyUsers($stakeholders, [
                'category' => 'comments',
                'title' => $this->t('notification/messages.new_task_comment'),
                'body' => $actorName . ' ' . $this->t('notification/messages.added_comment_to_task') . ' "' . $taskTitle . '": ' . $snippet,
                'entity_type' => 'comment',
                'entity_public_id' => $commentPublicId !== '' ? $commentPublicId : $taskPublicId,
                'action_code' => 'task_comment_created',
                'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'actor_public_id' => $actor['public_id'] ?? null,
                'actor_name' => $actorName,
                'link' => $taskLink,
                'payload' => [
                    'task_public_id' => $taskPublicId,
                    'task_title' => $taskTitle,
                    'comment_public_id' => $commentPublicId !== '' ? $commentPublicId : null,
                    'comment_excerpt' => $snippet,
                ],
            ], $actorUserId > 0 ? $actorUserId : null);
        }

        return $created;
    }

    public function markRead(string $publicId, array $actor): ?array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $this->notifications->markRead($publicId, $userId, gmdate('Y-m-d H:i:s'));
        $item = $this->notifications->findByPublicIdForUser($publicId, $userId);
        return $item ? $this->normalizeItem($item) : null;
    }

    public function markUnread(string $publicId, array $actor): ?array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $this->notifications->markUnread($publicId, $userId);
        $item = $this->notifications->findByPublicIdForUser($publicId, $userId);
        return $item ? $this->normalizeItem($item) : null;
    }

    public function markAllRead(array $actor, ?string $category = null): int
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        $updated = $this->notifications->markAllRead($userId, $category, gmdate('Y-m-d H:i:s'));

        $this->logger->audit([
            'action' => 'notifications_mark_all_read',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'notification',
            'entity_public_id' => '*',
            'updated_count' => $updated,
            'category' => $category,
        ]);

        return $updated;
    }

    public function counters(array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return [
                'total' => 0,
                'unread' => 0,
                'by_category' => [],
            ];
        }

        return $this->notifications->countersByUser($userId);
    }

    /** @return array<int,array<string,mixed>> */
    public function streamItemsAfterId(int $userId, int $afterId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        $items = $this->notifications->listForUserAfterId($userId, $afterId, $limit);
        return array_map(fn(array $item): array => $this->normalizeItem($item), $items);
    }

    public function latestInternalIdByUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        return $this->notifications->latestInternalIdByUser($userId);
    }

    public function stateHashByUser(int $userId): string
    {
        if ($userId <= 0) {
            return 'empty:0:0';
        }

        return $this->notifications->stateHashByUser($userId);
    }

    /** @param array<string,mixed> $team */
    /** @param int[] $addedUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyTeamMembersAdded(array $team, array $addedUserIds, array $actor): int
    {
        $teamPublicId = trim((string)($team['public_id'] ?? ''));
        if ($teamPublicId === '' || $addedUserIds === []) {
            return 0;
        }

        $teamTitle = trim((string)($team['title'] ?? '')) ?: $teamPublicId;
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers($addedUserIds, [
            'category' => 'teams',
            'title' => $this->t('notification/messages.added_to_team'),
            'body' => $actorName . ' ' . $this->t('notification/messages.added_you_to_team') . ' "' . $teamTitle . '".',
            'entity_type' => 'team',
            'entity_public_id' => $teamPublicId,
            'action_code' => 'team_member_added',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=teams',
            'payload' => [
                'team_title' => $teamTitle,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $project */
    /** @param int[] $addedUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyProjectMembersAdded(array $project, array $addedUserIds, array $actor): int
    {
        $projectPublicId = trim((string)($project['public_id'] ?? ''));
        if ($projectPublicId === '' || $addedUserIds === []) {
            return 0;
        }

        $projectTitle = trim((string)($project['title'] ?? '')) ?: $projectPublicId;
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers($addedUserIds, [
            'category' => 'projects',
            'title' => $this->t('notification/messages.added_to_project'),
            'body' => $actorName . ' ' . $this->t('notification/messages.added_you_to_project') . ' "' . $projectTitle . '".',
            'entity_type' => 'project',
            'entity_public_id' => $projectPublicId,
            'action_code' => 'project_member_added',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=project-detail&project_public_id=' . rawurlencode($projectPublicId),
            'payload' => [
                'project_title' => $projectTitle,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $project */
    /** @param array<string,mixed> $actor */
    public function notifyProjectManagerAssigned(array $project, int $managerUserId, array $actor): int
    {
        $projectPublicId = trim((string)($project['public_id'] ?? ''));
        if ($projectPublicId === '' || $managerUserId <= 0) {
            return 0;
        }

        $projectTitle = trim((string)($project['title'] ?? '')) ?: $projectPublicId;
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers([$managerUserId], [
            'category' => 'projects',
            'title' => $this->t('notification/messages.assigned_project_manager'),
            'body' => $actorName . ' ' . $this->t('notification/messages.assigned_you_project_manager') . ' "' . $projectTitle . '".',
            'entity_type' => 'project',
            'entity_public_id' => $projectPublicId,
            'action_code' => 'project_manager_assigned',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=project-detail&project_public_id=' . rawurlencode($projectPublicId),
            'payload' => [
                'project_title' => $projectTitle,
                'manager_user_id' => $managerUserId,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $event */
    /** @param int[] $targetUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyCalendarEventAssigned(array $event, array $targetUserIds, array $actor): int
    {
        $eventPublicId = trim((string)($event['public_id'] ?? ''));
        if ($eventPublicId === '' || $targetUserIds === []) {
            return 0;
        }

        $eventTitle = trim((string)($event['title'] ?? '')) ?: $eventPublicId;
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers($targetUserIds, [
            'category' => 'calendar',
            'title' => $this->t('notification/messages.calendar_event_assigned'),
            'body' => $actorName . ' ' . $this->t('notification/messages.added_event_to_calendar') . ' "' . $eventTitle . '".',
            'entity_type' => 'calendar_event',
            'entity_public_id' => $eventPublicId,
            'action_code' => 'calendar_event_assigned',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=calendar',
            'payload' => [
                'event_title' => $eventTitle,
                'starts_at' => $event['starts_at'] ?? null,
                'ends_at' => $event['ends_at'] ?? null,
                'task_public_id' => $event['task_public_id'] ?? null,
                'project_public_id' => $event['project_public_id'] ?? null,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $event */
    /** @param int[] $targetUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyCalendarEventUpdated(array $event, array $targetUserIds, array $actor): int
    {
        $eventPublicId = trim((string)($event['public_id'] ?? ''));
        if ($eventPublicId === '' || $targetUserIds === []) {
            return 0;
        }

        $eventTitle = trim((string)($event['title'] ?? '')) ?: $eventPublicId;
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers($targetUserIds, [
            'category' => 'calendar',
            'title' => $this->t('notification/messages.calendar_event_updated'),
            'body' => $actorName . ' ' . $this->t('notification/messages.updated_event') . ' "' . $eventTitle . '".',
            'entity_type' => 'calendar_event',
            'entity_public_id' => $eventPublicId,
            'action_code' => 'calendar_event_updated',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=calendar',
            'payload' => [
                'event_title' => $eventTitle,
                'starts_at' => $event['starts_at'] ?? null,
                'ends_at' => $event['ends_at'] ?? null,
                'task_public_id' => $event['task_public_id'] ?? null,
                'project_public_id' => $event['project_public_id'] ?? null,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $event */
    /** @param int[] $targetUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyCalendarEventCancelled(array $event, array $targetUserIds, array $actor): int
    {
        $eventPublicId = trim((string)($event['public_id'] ?? ''));
        if ($eventPublicId === '' || $targetUserIds === []) {
            return 0;
        }

        $eventTitle = trim((string)($event['title'] ?? '')) ?: $eventPublicId;
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers($targetUserIds, [
            'category' => 'calendar',
            'title' => $this->t('notification/messages.calendar_event_cancelled'),
            'body' => $actorName . ' ' . $this->t('notification/messages.cancelled_event') . ' "' . $eventTitle . '".',
            'entity_type' => 'calendar_event',
            'entity_public_id' => $eventPublicId,
            'action_code' => 'calendar_event_cancelled',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=calendar',
            'payload' => [
                'event_title' => $eventTitle,
                'task_public_id' => $event['task_public_id'] ?? null,
                'project_public_id' => $event['project_public_id'] ?? null,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $mention */
    /** @param array<string,mixed> $actor */
    public function notifyMentionAdded(array $mention, array $actor): int
    {
        $mentionedUserId = (int)($mention['mentioned_user_id'] ?? 0);
        if ($mentionedUserId <= 0) {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        if ($actorUserId > 0 && $actorUserId === $mentionedUserId) {
            return 0;
        }

        $entityType = trim((string)($mention['entity_type'] ?? ''));
        $entityPublicId = trim((string)($mention['entity_public_id'] ?? ''));
        if ($entityType === '' || $entityPublicId === '') {
            return 0;
        }

        $actorName = $this->actorName($actor);

        return $this->notifyUsers([$mentionedUserId], [
            'category' => 'mentions',
            'title' => $this->t('notification/messages.mentioned_you'),
            'body' => $actorName . ' ' . $this->t('notification/messages.mentioned_you_in_discussion') . '.',
            'entity_type' => $entityType,
            'entity_public_id' => $entityPublicId,
            'action_code' => 'mention_added',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $this->mentionEntityLink($entityType, $entityPublicId),
            'payload' => [
                'mention_public_id' => $mention['public_id'] ?? null,
                'entity_type' => $entityType,
                'entity_public_id' => $entityPublicId,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $approval */
    /** @param int[] $reviewerUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyApprovalRequested(array $approval, array $reviewerUserIds, array $actor): int
    {
        $approvalPublicId = trim((string)($approval['public_id'] ?? ''));
        if ($approvalPublicId === '' || $reviewerUserIds === []) {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);
        $entityType = trim((string)($approval['entity_type'] ?? ''));
        $entityPublicId = trim((string)($approval['entity_public_id'] ?? ''));

        return $this->notifyUsers($reviewerUserIds, [
            'category' => 'approvals',
            'title' => $this->t('notification/messages.new_approval_request'),
            'body' => $actorName . ' ' . $this->t('notification/messages.requested_your_approval') . '.',
            'entity_type' => 'approval_request',
            'entity_public_id' => $approvalPublicId,
            'action_code' => 'approval_requested',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $this->approvalLink($entityType, $entityPublicId),
            'payload' => [
                'approval_public_id' => $approvalPublicId,
                'entity_type' => $entityType !== '' ? $entityType : null,
                'entity_public_id' => $entityPublicId !== '' ? $entityPublicId : null,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $approval */
    /** @param int[] $targetUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyApprovalStepDecided(array $approval, array $targetUserIds, string $decision, array $actor): int
    {
        $approvalPublicId = trim((string)($approval['public_id'] ?? ''));
        if ($approvalPublicId === '' || $targetUserIds === []) {
            return 0;
        }

        $normalizedDecision = $decision === 'rejected' ? 'rejected' : 'approved';
        $actionCode = $normalizedDecision === 'rejected' ? 'approval_step_rejected' : 'approval_step_approved';
        $title = $normalizedDecision === 'rejected' ? $this->t('notification/messages.approval_rejected_by_participant') : $this->t('notification/messages.approval_approved_by_participant');
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);
        $entityType = trim((string)($approval['entity_type'] ?? ''));
        $entityPublicId = trim((string)($approval['entity_public_id'] ?? ''));

        return $this->notifyUsers($targetUserIds, [
            'category' => 'approvals',
            'title' => $title,
            'body' => $actorName . ' ' . ($normalizedDecision === 'rejected' ? $this->t('notification/messages.rejected_approval_step') : $this->t('notification/messages.approved_approval_step')) . '.',
            'entity_type' => 'approval_request',
            'entity_public_id' => $approvalPublicId,
            'action_code' => $actionCode,
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $this->approvalLink($entityType, $entityPublicId),
            'payload' => [
                'approval_public_id' => $approvalPublicId,
                'decision' => $normalizedDecision,
                'entity_type' => $entityType !== '' ? $entityType : null,
                'entity_public_id' => $entityPublicId !== '' ? $entityPublicId : null,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $approval */
    /** @param int[] $targetUserIds */
    /** @param array<string,mixed> $actor */
    public function notifyApprovalFinalized(array $approval, array $targetUserIds, string $finalStatus, array $actor): int
    {
        $approvalPublicId = trim((string)($approval['public_id'] ?? ''));
        if ($approvalPublicId === '' || $targetUserIds === []) {
            return 0;
        }

        $status = $finalStatus === 'rejected' ? 'rejected' : 'approved';
        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);
        $entityType = trim((string)($approval['entity_type'] ?? ''));
        $entityPublicId = trim((string)($approval['entity_public_id'] ?? ''));

        return $this->notifyUsers($targetUserIds, [
            'category' => 'approvals',
            'title' => $status === 'rejected' ? $this->t('notification/messages.approval_rejected') : $this->t('notification/messages.approval_completed'),
            'body' => $status === 'rejected'
                ? $actorName . ' ' . $this->t('notification.messages.completed_approval_rejected') . '.'
                : $actorName . ' ' . $this->t('notification.messages.completed_approval_approved') . '.',
            'entity_type' => 'approval_request',
            'entity_public_id' => $approvalPublicId,
            'action_code' => 'approval_finalized',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => $this->approvalLink($entityType, $entityPublicId),
            'payload' => [
                'approval_public_id' => $approvalPublicId,
                'final_status' => $status,
                'entity_type' => $entityType !== '' ? $entityType : null,
                'entity_public_id' => $entityPublicId !== '' ? $entityPublicId : null,
            ],
        ], $actorUserId > 0 ? $actorUserId : null);
    }

    /** @param array<string,mixed> $reminder */
    public function notifyReminderDue(array $reminder, int $ownerUserId): int
    {
        $reminderPublicId = trim((string)($reminder['public_id'] ?? ''));
        if ($ownerUserId <= 0 || $reminderPublicId === '') {
            return 0;
        }

        if ($this->notifiedRecently($ownerUserId, 'reminder_due', 'reminder', $reminderPublicId, 3600)) {
            return 0;
        }

        $taskTitle = trim((string)($reminder['task_title'] ?? ''));
        $title = $this->t('notification/messages.reminder');
        $body = $taskTitle !== '' ? $this->t('notification/messages.reminder_fired_for_task') . ' "' . $taskTitle . '".' : $this->t('notification.messages.reminder_fired_scheduled') . '.';

        return $this->notifyUsers([$ownerUserId], [
            'category' => 'reminders',
            'title' => $title,
            'body' => $body,
            'entity_type' => 'reminder',
            'entity_public_id' => $reminderPublicId,
            'action_code' => 'reminder_due',
            'link' => 'index.php?route=my-day',
            'payload' => [
                'reminder_public_id' => $reminderPublicId,
                'remind_at' => $reminder['remind_at'] ?? null,
                'task_public_id' => $reminder['task_public_id'] ?? null,
                'task_title' => $taskTitle !== '' ? $taskTitle : null,
            ],
        ]);
    }

    /** @param array<string,mixed> $before */
    /** @param array<string,mixed> $after */
    public function notifyReminderRescheduled(array $before, array $after, int $ownerUserId, array $actor): int
    {
        $reminderPublicId = trim((string)($after['public_id'] ?? $before['public_id'] ?? ''));
        if ($ownerUserId <= 0 || $reminderPublicId === '') {
            return 0;
        }

        $beforeAt = trim((string)($before['remind_at'] ?? ''));
        $afterAt = trim((string)($after['remind_at'] ?? ''));
        if ($beforeAt === '' || $afterAt === '' || $beforeAt === $afterAt || strtotime($afterAt) <= strtotime($beforeAt)) {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers([$ownerUserId], [
            'category' => 'reminders',
            'title' => $this->t('notification/messages.reminder_rescheduled'),
            'body' => $actorName . ' ' . $this->t('notification/messages.rescheduled_reminder_to') . ' ' . $this->dateLabel($afterAt) . '.',
            'entity_type' => 'reminder',
            'entity_public_id' => $reminderPublicId,
            'action_code' => 'reminder_rescheduled',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=my-day',
            'payload' => [
                'reminder_public_id' => $reminderPublicId,
                'old_remind_at' => $beforeAt,
                'new_remind_at' => $afterAt,
                'task_public_id' => $after['task_public_id'] ?? $before['task_public_id'] ?? null,
            ],
        ]);
    }

    /** @param array<string,mixed> $reminder */
    public function notifyReminderCompleted(array $reminder, int $ownerUserId, array $actor): int
    {
        $reminderPublicId = trim((string)($reminder['public_id'] ?? ''));
        if ($ownerUserId <= 0 || $reminderPublicId === '') {
            return 0;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $actorName = $this->actorName($actor);

        return $this->notifyUsers([$ownerUserId], [
            'category' => 'reminders',
            'title' => $this->t('notification/messages.reminder_completed'),
            'body' => $actorName . ' ' . $this->t('notification.messages.marked_reminder_completed') . '.',
            'entity_type' => 'reminder',
            'entity_public_id' => $reminderPublicId,
            'action_code' => 'reminder_completed',
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actor['public_id'] ?? null,
            'actor_name' => $actorName,
            'link' => 'index.php?route=my-day',
            'payload' => [
                'reminder_public_id' => $reminderPublicId,
                'task_public_id' => $reminder['task_public_id'] ?? null,
            ],
        ]);
    }

    public function dispatchOverdueSignalsForUser(int $userId, array $actor = []): int
    {
        if ($this->tasks === null || $userId <= 0) {
            return 0;
        }

        $now = gmdate('Y-m-d H:i:s');
        $created = 0;

        $assignedOverdue = $this->tasks->listAssignedOverdueOpenTasks($userId, $now, 50);
        foreach ($assignedOverdue as $task) {
            $taskPublicId = trim((string)($task['public_id'] ?? ''));
            if ($taskPublicId === '') {
                continue;
            }
            if ($this->notifiedRecently($userId, 'task_overdue_assignee', 'task', $taskPublicId, 86400)) {
                continue;
            }

            $created += $this->notifyUsers([$userId], [
                'category' => 'deadlines',
                'title' => $this->t('notification/messages.task_overdue'),
                'body' => $this->t('notification/messages.task_overdue_body') . ' "' . $this->taskTitle($task) . '".',
                'entity_type' => 'task',
                'entity_public_id' => $taskPublicId,
                'action_code' => 'task_overdue_assignee',
                'link' => $this->taskLink($taskPublicId),
                'payload' => [
                    'task_title' => $this->taskTitle($task),
                    'due_at' => $task['due_at'] ?? null,
                    'project_public_id' => $task['project_public_id'] ?? null,
                    'project_title' => $task['project_title'] ?? null,
                ],
            ]);
        }

        $digest = $this->tasks->managerOverdueDigest($userId, $now, 5);
        $overdueTotal = (int)($digest['total'] ?? 0);
        if ($overdueTotal > 0) {
            $digestEntityPublicId = 'usr_' . $userId;
            if (!$this->notifiedRecently($userId, 'task_overdue_manager_digest', 'user', $digestEntityPublicId, 43200)) {
                $created += $this->notifyUsers([$userId], [
                    'category' => 'deadlines',
                    'title' => $this->t('notification/messages.overdue_tasks_digest'),
                    'body' => $this->t('notification/messages.overdue_tasks_digest_body', '{count}', strval($overdueTotal)),
                    'entity_type' => 'user',
                    'entity_public_id' => $digestEntityPublicId,
                    'action_code' => 'task_overdue_manager_digest',
                    'link' => 'index.php?route=tasks&kpi=overdue',
                    'payload' => [
                        'overdue_total' => $overdueTotal,
                        'projects' => $digest['items'] ?? [],
                    ],
                ]);
            }
        }

        return $created;
    }

    private function resolveTargetUserId(array $input, array $actor): int
    {
        $actorUserId = (int)($actor['id'] ?? 0);
        $requestedPublicId = trim((string)($input['user_public_id'] ?? ''));
        $isRoot = (bool)($actor['is_root'] ?? false);

        if ($requestedPublicId === '' || !$isRoot) {
            return $actorUserId;
        }

        $user = $this->users->findByPublicId($requestedPublicId);
        if (!$user) {
            return $actorUserId;
        }

        return (int)$user['id'];
    }

    private function createRecordForUser(int $userId, array $input, array $actor = []): string
    {
        $publicId = Ulid::generate('ntf');
        $payloadJson = null;
        if (array_key_exists('payload', $input) && $input['payload'] !== null) {
            $encoded = json_encode($input['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $payloadJson = $encoded !== false ? $encoded : null;
        }

        $actorUserId = (int)($input['actor_user_id'] ?? ($actor['id'] ?? 0));
        $actorPublicId = trim((string)($input['actor_public_id'] ?? ($actor['public_id'] ?? '')));
        $actorName = trim((string)($input['actor_name'] ?? $this->actorName($actor)));

        $this->notifications->create([
            'public_id' => $publicId,
            'user_id' => $userId,
            'category' => trim((string)($input['category'] ?? 'system')),
            'title' => trim((string)($input['title'] ?? $this->t('notification/messages.default_title'))),
            'body' => trim((string)($input['body'] ?? '')),
            'entity_type' => $this->nullableString($input['entity_type'] ?? null),
            'entity_public_id' => $this->nullableString($input['entity_public_id'] ?? null),
            'action_code' => $this->nullableString($input['action_code'] ?? null),
            'actor_user_id' => $actorUserId > 0 ? $actorUserId : null,
            'actor_public_id' => $actorPublicId !== '' ? $actorPublicId : null,
            'actor_name' => $actorName !== '' ? $actorName : null,
            'link' => $this->nullableString($input['link'] ?? null),
            'payload_json' => $payloadJson,
            'is_read' => 0,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'read_at' => null,
        ]);

        $this->push?->notifyUserNewNotification($userId, [
            'public_id' => $publicId,
            'action_code' => $input['action_code'] ?? null,
            'actor_public_id' => $actorPublicId !== '' ? $actorPublicId : null,
            'title' => trim((string)($input['title'] ?? '')),
            'body' => trim((string)($input['body'] ?? '')),
            'link' => $this->nullableString($input['link'] ?? null),
        ]);

        return $publicId;
    }

    /** @param array<string,mixed> $task */
    /** @return int[] */
    private function taskStakeholderUserIds(array $task): array
    {
        return array_values(array_unique(array_filter([
            (int)($task['creator_user_id'] ?? 0),
            (int)($task['assignee_user_id'] ?? 0),
            (int)($task['project_creator_user_id'] ?? 0),
            (int)($task['project_manager_user_id'] ?? 0),
            (int)($task['project_team_manager_user_id'] ?? 0),
        ], static fn(int $userId): bool => $userId > 0)));
    }

    /** @param array<string,mixed> $task */
    private function taskTitle(array $task): string
    {
        $title = trim((string)($task['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $publicId = trim((string)($task['public_id'] ?? ''));
        return $publicId !== '' ? $publicId : $this->t('notification/messages.default_task_title');
    }

    /** @param array<string,mixed> $actor */
    private function actorName(array $actor): string
    {
        $name = trim((string)($actor['full_name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        $login = trim((string)($actor['login'] ?? ''));
        if ($login !== '') {
            return $login;
        }

        return $this->t('notification/messages.system_actor');
    }

    private function taskLink(string $taskPublicId): string
    {
        return 'index.php?route=task-detail&task_public_id=' . rawurlencode($taskPublicId);
    }

    private function mentionEntityLink(string $entityType, string $entityPublicId): string
    {
        if ($entityType === 'task') {
            return $this->taskLink($entityPublicId);
        }

        if ($entityType === 'project') {
            return 'index.php?route=project-detail&project_public_id=' . rawurlencode($entityPublicId);
        }

        if ($entityType === 'comment') {
            return 'index.php?route=notifications';
        }

        return 'index.php?route=notifications';
    }

    private function approvalLink(string $entityType, string $entityPublicId): string
    {
        if ($entityType === 'task') {
            return $this->taskLink($entityPublicId);
        }
        if ($entityType === 'project') {
            return 'index.php?route=project-detail&project_public_id=' . rawurlencode($entityPublicId);
        }

        return 'index.php?route=notifications';
    }

    private function notifiedRecently(int $userId, string $actionCode, string $entityType, string $entityPublicId, int $windowSeconds): bool
    {
        if ($windowSeconds <= 0) {
            return false;
        }

        $since = gmdate('Y-m-d H:i:s', time() - $windowSeconds);
        return $this->notifications->hasActionForUserEntitySince($userId, $actionCode, $entityType, $entityPublicId, $since);
    }

    private function statusLabel(string $statusCode): string
    {
        $normalized = trim($statusCode);
        if ($normalized === '') {
            return $this->t('notification/messages.status_no_status');
        }

        $labels = [
            'new' => $this->t('notification/messages.status_new'),
            'in_progress' => $this->t('notification/messages.status_in_progress'),
            'blocked' => $this->t('notification/messages.status_blocked'),
            'done' => $this->t('notification/messages.status_done'),
            'completed' => $this->t('notification/messages.status_completed'),
            'archived' => $this->t('notification/messages.status_archived'),
        ];

        return $labels[$normalized] ?? $normalized;
    }

    private function dateLabel(?string $value): string
    {
        $normalized = trim((string)$value);
        if ($normalized === '') {
            return $this->t('notification/messages.no_due_date');
        }

        $timestamp = strtotime($normalized);
        if ($timestamp === false) {
            return $normalized;
        }

        return gmdate('Y-m-d H:i', $timestamp);
    }

    private function excerpt(string $value, int $limit = 140): string
    {
        $normalized = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        if ($normalized === '') {
            return $this->t('notification/messages.no_text');
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            return mb_strlen($normalized) > $limit
                ? rtrim(mb_substr($normalized, 0, $limit - 1)) . '…'
                : $normalized;
        }

        return strlen($normalized) > $limit
            ? rtrim(substr($normalized, 0, $limit - 1)) . '...'
            : $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $normalized = trim((string)$value);
        return $normalized !== '' ? $normalized : null;
    }

    /** @param array<string,mixed> $item */
    private function normalizeItem(array $item): array
    {
        if (array_key_exists('payload_json', $item)) {
            $decoded = null;
            if ($item['payload_json'] !== null && $item['payload_json'] !== '') {
                $candidate = json_decode((string)$item['payload_json'], true);
                if (is_array($candidate)) {
                    $decoded = $candidate;
                }
            }

            unset($item['payload_json']);
            $item['payload'] = $decoded;
        }

        return $item;
    }
}
