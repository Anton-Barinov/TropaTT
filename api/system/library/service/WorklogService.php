<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class WorklogService
{
    public function __construct(
        private readonly WorklogRepository $worklogs,
        private readonly TaskRepository $tasks,
        private readonly JsonLogger $logger
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->worklogs->list(
            $filters,
            (int)$actor['id'],
            (bool)($actor['is_root'] ?? false)
        );

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
        $taskId = null;
        if (!empty($input['task_public_id'])) {
            $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
            if (!$task) {
                return 'TASK_NOT_FOUND';
            }
            if (!$this->canAccessTask($task, $actor)) {
                return 'FORBIDDEN';
            }
            $taskId = (int)$task['id'];
        }

        $publicId = Ulid::generate('wlg');
        $now = gmdate('Y-m-d H:i:s');
        $userId = (int)$actor['id'];
        if (!empty($input['user_public_id']) && (bool)($actor['is_root'] ?? false)) {
            $targetUser = $this->worklogs->findUserByPublicId((string)$input['user_public_id']);
            if ($targetUser) {
                $userId = (int)$targetUser['id'];
            }
        }
        $this->worklogs->create([
            'public_id' => $publicId,
            'user_id' => $userId,
            'task_id' => $taskId,
            'minutes_spent' => (int)$input['minutes_spent'],
            'note' => trim((string)($input['note'] ?? '')),
            'logged_at' => (string)($input['logged_at'] ?? $now),
            'created_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'worklog_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'worklog',
            'entity_public_id' => $publicId,
        ]);

        return $this->worklogs->findByPublicId($publicId);
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->worklogs->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        if (!$this->canAccessWorklog($item, $actor)) {
            return null;
        }

        return $item;
    }

    public function update(string $publicId, array $input, array $actor)
    {
        $existing = $this->worklogs->findByPublicId($publicId);
        if (!$existing) {
            return null;
        }

        if (!$this->canAccessWorklog($existing, $actor)) {
            return 'FORBIDDEN';
        }

        $set = [];
        if (array_key_exists('minutes_spent', $input)) {
            $set['minutes_spent'] = (int)$input['minutes_spent'];
        }
        if (array_key_exists('note', $input)) {
            $set['note'] = trim((string)$input['note']);
        }
        if (array_key_exists('logged_at', $input)) {
            $set['logged_at'] = (string)$input['logged_at'];
        }
        if (array_key_exists('task_public_id', $input)) {
            if ($input['task_public_id'] === null || $input['task_public_id'] === '') {
                $set['task_id'] = null;
            } else {
                $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
                if (!$task) {
                    return 'TASK_NOT_FOUND';
                }
                if (!$this->canAccessTask($task, $actor)) {
                    return 'FORBIDDEN';
                }
                $set['task_id'] = (int)$task['id'];
            }
        }

        if ($set !== []) {
            $this->worklogs->updateByPublicId($publicId, $set);
        }

        $this->logger->audit([
            'action' => 'worklog_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'worklog',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        return $this->worklogs->findByPublicId($publicId);
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $existing = $this->worklogs->findByPublicId($publicId);
        if (!$existing) {
            return false;
        }
        if (!$this->canAccessWorklog($existing, $actor)) {
            return 'FORBIDDEN';
        }

        $ok = $this->worklogs->deleteByPublicId($publicId);
        if ($ok) {
            $this->logger->audit([
                'action' => 'worklog_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'worklog',
                'entity_public_id' => $publicId,
            ]);
        }

        return $ok;
    }

    private function canAccessWorklog(array $worklog, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return (int)($worklog['user_id'] ?? 0) === (int)($actor['id'] ?? 0);
    }

    private function canAccessTask(array $task, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
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

    public function summary(array $filters, array $actor): array
    {
        $actorId = (int)$actor['id'];
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $rows = $this->worklogs->summaryByDay($filters, $actorId, $actorIsRoot);
        return ['items' => $rows];
    }

    public function earnings(array $filters, array $actor): array
    {
        $actorId = (int)$actor['id'];
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $rows = $this->worklogs->earningsByDay($filters, $actorId, $actorIsRoot);
        return ['items' => $rows];
    }

    public function taskSummaryByUser(string $taskPublicId, array $actor): ?array
    {
        $task = $this->tasks->findByPublicId($taskPublicId);
        if (!$task) {
            return null;
        }
        if (!$this->canAccessTask($task, $actor)) {
            return null;
        }
        return $this->worklogs->taskSummary($taskPublicId);
    }
}
