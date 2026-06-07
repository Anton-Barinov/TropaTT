<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Database\Builder\QueryBuilder;
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
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $rows = $this->worklogs->summaryByDay($filters, $actorId, $actorIsRoot, $teamPublicId ?: null);
        return ['items' => $rows];
    }

    public function earnings(array $filters, array $actor): array
    {
        $actorId = (int)$actor['id'];
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $rows = $this->worklogs->earningsByDay($filters, $actorId, $actorIsRoot, $teamPublicId ?: null);
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

    public function matrix(array $filters, array $actor): array
    {
        $actorId = (int)$actor['id'];
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $from = (string)($filters['from'] ?? '');
        $to = (string)($filters['to'] ?? '');
        $userPublicId = (string)($filters['user_public_id'] ?? '');
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $projectPublicId = (string)($filters['project_public_id'] ?? '');

        // Resolve team members to public IDs before the matrix query
        $teamUserPublicIds = null;
        if (!empty($teamPublicId)) {
            $team = (new QueryBuilder($this->worklogs->getPdo()))
                ->from('teams')
                ->select(['member_user_ids'])
                ->where('public_id', '=', $teamPublicId)
                ->first();
            if ($team && isset($team['member_user_ids'])) {
                $raw = $team['member_user_ids'];
                $ids = [];
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $v) {
                            $iv = (int)$v;
                            if ($iv > 0) $ids[] = $iv;
                        }
                    }
                } elseif (is_array($raw)) {
                    foreach ($raw as $v) {
                        $iv = (int)$v;
                        if ($iv > 0) $ids[] = $iv;
                    }
                }
                if ($ids !== []) {
                    $ids = array_values(array_unique($ids));
                    $pubRows = (new QueryBuilder($this->worklogs->getPdo()))
                        ->from('users')
                        ->select(['public_id'])
                        ->whereIn('id', $ids)
                        ->get();
                    $teamUserPublicIds = array_map(static fn(array $row): string => (string)$row['public_id'], $pubRows);
                }
            }
        }

        $rows = $this->worklogs->matrixForPeriod($from, $to, $userPublicId ?: null, $projectPublicId ?: null, $teamUserPublicIds, $actorId, $actorIsRoot);

        // Build matrix: [day][user_public_id] = total_minutes
        $matrix = [];
        $userSet = [];
        $dayTotals = [];
        foreach ($rows as $row) {
            $day = $row['day'];
            $uid = $row['user_public_id'];
            $mins = (int)$row['total_minutes'];
            if (!isset($matrix[$day])) $matrix[$day] = [];
            $matrix[$day][$uid] = $mins;
            $userSet[$uid] = true;
            $dayTotals[$day] = ($dayTotals[$day] ?? 0) + $mins;
        }
        $userSetKeys = array_keys($userSet);

        // Generate date range
        $dates = [];
        if ($from && $to) {
            $start = new \DateTime($from);
            $end = new \DateTime($to);
            $interval = new \DateInterval('P1D');
            $period = new \DatePeriod($start, $interval, $end->modify('+1 day'));
            foreach ($period as $dt) {
                $dates[] = $dt->format('Y-m-d');
            }
            $end->modify('-1 day');
        }

        // Determine user list
        $users = [];
        if ($userPublicId) {
            $user = $this->worklogs->findUserByPublicId($userPublicId);
            if ($user) {
                $users[] = [
                    'public_id' => $user['public_id'],
                    'login' => $user['login'],
                    'full_name' => $user['full_name'],
                ];
            }
        } else {
            $allUsers = $this->worklogs->activeUsers();
            foreach ($allUsers as $u) {
                $pid = $u['public_id'];
                // Filter by team if needed
                if ($teamUserPublicIds !== null) {
                    if (!in_array($pid, $teamUserPublicIds)) {
                        continue;
                    }
                }
                // If project is selected, only include users with time in that project
                if ($projectPublicId && !in_array($pid, $userSetKeys)) {
                    continue;
                }
                $users[] = [
                    'public_id' => $pid,
                    'login' => $u['login'],
                    'full_name' => $u['full_name'],
                ];
            }
        }

        $userTotals = [];
        foreach ($users as $u) {
            $userTotals[$u['public_id']] = 0;
        }
        foreach ($matrix as $day => $dayData) {
            foreach ($dayData as $uid => $mins) {
                if (isset($userTotals[$uid])) {
                    $userTotals[$uid] += $mins;
                }
            }
        }

        $teams = $this->worklogs->listTeams();
        $projects = $this->worklogs->listProjects();

        return [
            'dates' => $dates,
            'users' => $users,
            'matrix' => $matrix,
            'day_totals' => $dayTotals,
            'user_totals' => $userTotals,
            'teams' => $teams,
            'projects' => $projects,
        ];
    }

    public function detail(string $day, string $userPublicId, ?string $projectPublicId, array $actor): array
    {
        $actorId = (int)$actor['id'];
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $rows = $this->worklogs->detailByDayUser($day, $userPublicId, $projectPublicId, $actorId, $actorIsRoot);

        $totalMinutes = 0;
        foreach ($rows as $row) {
            $totalMinutes += (int)$row['minutes_spent'];
        }

        return [
            'items' => $rows,
            'total_minutes' => $totalMinutes,
            'day' => $day,
            'user_public_id' => $userPublicId,
        ];
    }
}
