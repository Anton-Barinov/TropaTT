<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Reminder\ReminderRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class ReminderService
{
    public function __construct(
        private readonly ReminderRepository $reminders,
        private readonly TaskRepository $tasks,
        private readonly JsonLogger $logger,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $userId = (int)($actor['id'] ?? 0);
        [$items, $total, $page, $limit] = $this->reminders->listByUser($userId, $filters);

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

    public function get(string $publicId, array $actor): ?array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        return $this->reminders->findByPublicIdForUser($publicId, $userId);
    }

    public function create(array $input, array $actor)
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return 'UNAUTHORIZED';
        }

        $taskId = null;
        if (!empty($input['task_public_id'])) {
            $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
            if (!$task) {
                return 'TASK_NOT_FOUND';
            }
            $taskId = (int)$task['id'];
        }

        $publicId = Ulid::generate('rmn');
        $this->reminders->create([
            'public_id' => $publicId,
            'user_id' => $userId,
            'task_id' => $taskId,
            'remind_at' => (string)$input['remind_at'],
            'status' => (string)($input['status'] ?? 'new'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->logger->audit([
            'action' => 'reminder_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'reminder',
            'entity_public_id' => $publicId,
            'task_id' => $taskId,
        ]);

        $created = $this->reminders->findByPublicIdForUser($publicId, $userId) ?: ['public_id' => $publicId];
        $remindAt = trim((string)($created['remind_at'] ?? ''));
        if ($remindAt !== '' && strtotime($remindAt) <= time()) {
            $this->notifications?->notifyReminderDue(is_array($created) ? $created : ['public_id' => $publicId], $userId);
        }

        return $created;
    }

    public function update(string $publicId, array $input, array $actor)
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $existing = $this->reminders->findByPublicIdForUser($publicId, $userId);
        if (!$existing) {
            return null;
        }

        $set = [];
        if (array_key_exists('status', $input)) {
            $set['status'] = (string)$input['status'];
        }
        if (array_key_exists('remind_at', $input)) {
            $set['remind_at'] = (string)$input['remind_at'];
        }
        if (array_key_exists('task_public_id', $input)) {
            if ($input['task_public_id'] === null || $input['task_public_id'] === '') {
                $set['task_id'] = null;
            } else {
                $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
                if (!$task) {
                    return 'TASK_NOT_FOUND';
                }
                $set['task_id'] = (int)$task['id'];
            }
        }

        if ($set !== []) {
            $this->reminders->updateByPublicIdForUser($publicId, $userId, $set);
        }

        $this->logger->audit([
            'action' => 'reminder_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'reminder',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        $updated = $this->reminders->findByPublicIdForUser($publicId, $userId);
        if (is_array($updated)) {
            $beforeStatus = strtolower(trim((string)($existing['status'] ?? '')));
            $afterStatus = strtolower(trim((string)($updated['status'] ?? '')));
            $this->notifications?->notifyReminderRescheduled($existing, $updated, $userId, $actor);
            if ($afterStatus === 'done' || $afterStatus === 'completed') {
                if ($beforeStatus !== $afterStatus) {
                    $this->notifications?->notifyReminderCompleted($updated, $userId, $actor);
                }
            }
        }

        return $updated;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        $ok = $this->reminders->deleteByPublicIdForUser($publicId, $userId);
        if ($ok) {
            $this->logger->audit([
                'action' => 'reminder_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'reminder',
                'entity_public_id' => $publicId,
            ]);
        }

        return $ok;
    }

    public function pendingDueCount(array $actor, string $until): int
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return 0;
        }

        return $this->reminders->countPendingDueUntil($userId, $until);
    }

    public function dispatchDueNotificationsForUser(array $actor, ?string $until = null): int
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0 || $this->notifications === null) {
            return 0;
        }

        $cutoff = $until !== null && trim($until) !== '' ? (string)$until : gmdate('Y-m-d H:i:s');
        $items = $this->reminders->listDueActiveByUser($userId, $cutoff, 200);
        $created = 0;
        foreach ($items as $item) {
            $created += $this->notifications->notifyReminderDue($item, $userId);
        }

        return $created;
    }
}
