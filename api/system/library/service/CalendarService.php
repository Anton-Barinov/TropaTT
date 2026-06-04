<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Calendar\CalendarEventRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Reminder\ReminderRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\Ulid;

final class CalendarService
{
    public function __construct(
        private readonly CalendarEventRepository $events,
        private readonly TaskRepository $tasks,
        private readonly ProjectRepository $projects,
        private readonly ReminderRepository $reminders,
        private readonly JsonLogger $logger,
        private readonly ?NotificationService $notifications = null
    ) {
    }

    public function listEvents(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->events->listByUser(
            (int)$actor['id'],
            (bool)($actor['is_root'] ?? false),
            $filters
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

    public function createEvent(array $input, array $actor)
    {
        $projectId = null;
        if (!empty($input['project_public_id'])) {
            $project = $this->projects->findByPublicId((string)$input['project_public_id']);
            if (!$project || !$this->canAccessProject($project, $actor)) {
                return 'PROJECT_NOT_FOUND';
            }
            $projectId = (int)$project['id'];
        }

        $taskId = null;
        if (!empty($input['task_public_id'])) {
            $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
            if (!$task || !$this->canAccessTask($task, $actor)) {
                return 'TASK_NOT_FOUND';
            }
            $taskId = (int)$task['id'];
        }

        $publicId = Ulid::generate('evt');
        $startsAt = (string)$input['starts_at'];
        $endsAt = (string)($input['ends_at'] ?? $startsAt);
        $now = gmdate('Y-m-d H:i:s');

        $this->events->create([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'description' => trim((string)($input['description'] ?? '')),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'owner_user_id' => (int)$actor['id'],
            'project_id' => $projectId,
            'task_id' => $taskId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->logger->audit([
            'action' => 'calendar_event_created',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'calendar_event',
            'entity_public_id' => $publicId,
        ]);

        $createdEvent = $this->events->findByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false));
        if (is_array($createdEvent)) {
            $targetUserIds = $this->calendarEventStakeholderIds($createdEvent, (int)($actor['id'] ?? 0));
            if ($targetUserIds !== []) {
                $this->notifications?->notifyCalendarEventAssigned($createdEvent, $targetUserIds, $actor);
            }
        }

        return $createdEvent;
    }

    public function getEvent(string $publicId, array $actor): ?array
    {
        return $this->events->findByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false));
    }

    public function updateEvent(string $publicId, array $input, array $actor)
    {
        $existing = $this->events->findByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false));
        if (!$existing) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('description', $input)) {
            $set['description'] = trim((string)$input['description']);
        }
        if (array_key_exists('starts_at', $input)) {
            $set['starts_at'] = (string)$input['starts_at'];
        }
        if (array_key_exists('ends_at', $input)) {
            $set['ends_at'] = (string)$input['ends_at'];
        }
        if (array_key_exists('project_public_id', $input)) {
            if ($input['project_public_id'] === null || $input['project_public_id'] === '') {
                $set['project_id'] = null;
            } else {
                $project = $this->projects->findByPublicId((string)$input['project_public_id']);
                if (!$project || !$this->canAccessProject($project, $actor)) {
                    return 'PROJECT_NOT_FOUND';
                }
                $set['project_id'] = (int)$project['id'];
            }
        }
        if (array_key_exists('task_public_id', $input)) {
            if ($input['task_public_id'] === null || $input['task_public_id'] === '') {
                $set['task_id'] = null;
            } else {
                $task = $this->tasks->findByPublicId((string)$input['task_public_id']);
                if (!$task || !$this->canAccessTask($task, $actor)) {
                    return 'TASK_NOT_FOUND';
                }
                $set['task_id'] = (int)$task['id'];
            }
        }

        if ($set !== []) {
            $set['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->events->updateByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false), $set);
        }

        $this->logger->audit([
            'action' => 'calendar_event_updated',
            'actor_public_id' => $actor['public_id'] ?? null,
            'entity_type' => 'calendar_event',
            'entity_public_id' => $publicId,
            'changes' => $set,
        ]);

        $updatedEvent = $this->events->findByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false));
        if (is_array($updatedEvent)) {
            $targetUserIds = $this->calendarEventStakeholderIds($updatedEvent, (int)($actor['id'] ?? 0));
            if ($targetUserIds !== []) {
                $this->notifications?->notifyCalendarEventUpdated($updatedEvent, $targetUserIds, $actor);
            }
        }

        return $updatedEvent;
    }

    public function deleteEvent(string $publicId, array $actor): bool
    {
        $existing = $this->events->findByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false));
        $ok = $this->events->deleteByPublicId($publicId, (int)$actor['id'], (bool)($actor['is_root'] ?? false));
        if ($ok) {
            $this->logger->audit([
                'action' => 'calendar_event_deleted',
                'actor_public_id' => $actor['public_id'] ?? null,
                'entity_type' => 'calendar_event',
                'entity_public_id' => $publicId,
            ]);

            if (is_array($existing)) {
                $targetUserIds = $this->calendarEventStakeholderIds($existing, (int)($actor['id'] ?? 0));
                if ($targetUserIds !== []) {
                    $this->notifications?->notifyCalendarEventCancelled($existing, $targetUserIds, $actor);
                }
            }
        }

        return $ok;
    }

    public function myDay(array $actor, ?string $date = null): array
    {
        $dt = $this->normalizeDate($date);
        $start = $dt . ' 00:00:00';
        $end = $dt . ' 23:59:59';

        return $this->buildAgenda($actor, $start, $end, 'day');
    }

    public function myWeek(array $actor, ?string $date = null): array
    {
        $base = new \DateTimeImmutable($this->normalizeDate($date) . ' 00:00:00');
        $weekStart = $base->modify('monday this week');
        $weekEnd = $weekStart->modify('+6 day')->setTime(23, 59, 59);

        return $this->buildAgenda(
            $actor,
            $weekStart->format('Y-m-d H:i:s'),
            $weekEnd->format('Y-m-d H:i:s'),
            'week'
        );
    }

    public function myMonth(array $actor, ?string $date = null): array
    {
        $base = new \DateTimeImmutable($this->normalizeDate($date) . ' 00:00:00');
        $monthStart = $base->modify('first day of this month')->setTime(0, 0, 0);
        $monthEnd = $base->modify('last day of this month')->setTime(23, 59, 59);

        return $this->buildAgenda(
            $actor,
            $monthStart->format('Y-m-d H:i:s'),
            $monthEnd->format('Y-m-d H:i:s'),
            'month'
        );
    }

    private function buildAgenda(array $actor, string $startAt, string $endAt, string $period): array
    {
        $userId = (int)$actor['id'];
        $isRoot = (bool)($actor['is_root'] ?? false);

        $events = $this->events->listInRange($userId, $isRoot, $startAt, $endAt);
        $tasks = $this->events->listTasksDueInRange($userId, $isRoot, $startAt, $endAt);
        $reminders = $this->reminders->listInRange($userId, $startAt, $endAt);

        return [
            'period' => $period,
            'range' => [
                'from' => $startAt,
                'to' => $endAt,
            ],
            'events' => $events,
            'tasks_due' => $tasks,
            'reminders' => $reminders,
            'summary' => [
                'events_count' => count($events),
                'tasks_due_count' => count($tasks),
                'reminders_count' => count($reminders),
            ],
        ];
    }

    private function normalizeDate(?string $date): string
    {
        $raw = trim((string)$date);
        if ($raw === '') {
            return date('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            return $raw;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return date('Y-m-d');
        }

        return date('Y-m-d', $ts);
    }

    private function canAccessProject(array $project, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        return (int)($project['created_by_user_id'] ?? 0) === $actorId
            || (int)($project['manager_user_id'] ?? 0) === $actorId
            || (int)($project['team_manager_user_id'] ?? 0) === $actorId
            || in_array($actorId, $this->decodeTeamMemberIds($project['team_member_user_ids'] ?? null), true);
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

    /** @param array<string,mixed> $event */
    /** @return int[] */
    private function calendarEventStakeholderIds(array $event, int $actorUserId): array
    {
        $userIds = [];
        $ownerUserId = (int)($event['owner_user_id'] ?? 0);
        if ($ownerUserId > 0) {
            $userIds[] = $ownerUserId;
        }

        $taskPublicId = trim((string)($event['task_public_id'] ?? ''));
        if ($taskPublicId !== '') {
            $task = $this->tasks->findByPublicId($taskPublicId);
            if ($task) {
                $userIds[] = (int)($task['creator_user_id'] ?? 0);
                $userIds[] = (int)($task['assignee_user_id'] ?? 0);
                $userIds[] = (int)($task['project_creator_user_id'] ?? 0);
                $userIds[] = (int)($task['project_manager_user_id'] ?? 0);
                $userIds[] = (int)($task['project_team_manager_user_id'] ?? 0);
                $userIds = array_merge($userIds, $this->decodeTeamMemberIds($task['project_team_member_user_ids'] ?? null));
            }
        }

        $projectPublicId = trim((string)($event['project_public_id'] ?? ''));
        if ($projectPublicId !== '') {
            $project = $this->projects->findByPublicId($projectPublicId);
            if ($project) {
                $userIds[] = (int)($project['created_by_user_id'] ?? 0);
                $userIds[] = (int)($project['manager_user_id'] ?? 0);
                $userIds[] = (int)($project['team_manager_user_id'] ?? 0);
                $userIds = array_merge($userIds, $this->decodeTeamMemberIds($project['team_member_user_ids'] ?? null));
            }
        }

        $normalized = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $userId): bool => $userId > 0)));
        if ($actorUserId > 0) {
            $normalized = array_values(array_filter($normalized, static fn(int $userId): bool => $userId !== $actorUserId));
        }

        return $normalized;
    }
}
