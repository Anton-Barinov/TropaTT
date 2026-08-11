<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Task\TaskRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\User\UserManagementRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Logger\JsonLogger;
use Api\System\Library\Support\TimeOverlapMath;
use Api\System\Library\Support\Ulid;

final class WorklogService
{
    public function __construct(
        private readonly WorklogRepository $worklogs,
        private readonly TaskRepository $tasks,
        private readonly UserManagementRepository $userManagement,
        private readonly TeamRepository $teamRepo,
        private readonly JsonLogger $logger
    ) {
    }

    /**
     * Get the list of user IDs that the actor can see worklogs for.
     * Includes the actor, all users created by them (recursively),
     * and all members of teams where the actor is the manager.
     * Root users get an empty array — no user-level filter is applied.
     */
    /** Resolve the internal numeric user ID from the actor array. */
    private function resolveActorId(array $actor): int
    {
        if (!empty($actor['id'])) {
            return (int)$actor['id'];
        }
        $publicId = (string)($actor['public_id'] ?? '');
        if ($publicId !== '') {
            $user = $this->worklogs->findUserByPublicId($publicId);
            if ($user && isset($user['id'])) {
                return (int)$user['id'];
            }
        }
        return 0;
    }

    private function getVisibleUserIds(array $actor): array
    {
        $actorId = $this->resolveActorId($actor);
        $isRoot = (bool)($actor['is_root'] ?? false);

        if ($isRoot) {
            return [];
        }

        // Sentinel: unresolvable non-root actor must not bypass the visibility filter.
        // Returning [-1] ensures whereIn() is applied but matches nothing.
        if ($actorId <= 0) {
            return [-1];
        }

        // Hierarchy: actor + all descendants (users created by them recursively)
        $hierarchyIds = $this->userManagement->descendantIds($actorId);

        // Teams: members of teams where the actor is the manager
        $teamMemberIds = $this->teamRepo->findMemberIdsByManager($actorId);

        // Merge, deduplicate, return
        return array_values(array_unique(array_merge($hierarchyIds, $teamMemberIds)));
    }

    public function list(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $isRoot = (bool)($actor['is_root'] ?? false);
        [$items, $total, $page, $limit] = $this->worklogs->list(
            $filters,
            $visibleUserIds,
            $isRoot
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
        $userId = $this->resolveActorId($actor);
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
            'started_at' => $this->parseIntervalTime($input['started_at'] ?? null),
            'ended_at' => $this->parseIntervalTime($input['ended_at'] ?? null),
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
        if (array_key_exists('started_at', $input) || array_key_exists('ended_at', $input)) {
            $set['started_at'] = $this->parseIntervalTime($input['started_at'] ?? null);
            $set['ended_at'] = $this->parseIntervalTime($input['ended_at'] ?? null);
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

        $visibleUserIds = $this->getVisibleUserIds($actor);
        return in_array((int)($worklog['user_id'] ?? 0), $visibleUserIds, true);
    }

    private function canAccessTask(array $task, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = $this->resolveActorId($actor);
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

    /**
     * Parse an ISO-8601 / MySQL timestamp into a normalized UTC string
     * ('Y-m-d H:i:s'). Returns null for empty values.
     */
    private function parseIntervalTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime((string)$value);
        if ($ts === false) {
            return null;
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }

    /** Convert a stored UTC 'Y-m-d H:i:s' value to Unix seconds. */
    private function toEpoch(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/[zZ]$/', $raw) || preg_match('/[+-]\d{2}:?\d{2}$/', $raw)) {
            $ts = strtotime($raw);
        } else {
            $ts = strtotime($raw . ' UTC');
        }

        return $ts === false ? null : $ts;
    }

    /**
     * Aggregate raw worklog rows into per user+day recorded/unique/overlap
     * minutes. Entries without a usable interval (legacy timers and manual
     * entries) cannot be de-duplicated and simply count as both recorded and
     * unique.
     *
     * @param array<int, array> $rows rows with user_public_id, day, minutes_spent, started_at, ended_at
     * @return array<string, array{recorded: int, unique: int, overlap: int, has_intervals: bool}> keyed by "user_public_id|day"
     */
    private function aggregateIntervals(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $row) {
            $key = (string)($row['user_public_id'] ?? '') . '|' . (string)($row['day'] ?? '');
            $minutes = max(0, (int)($row['minutes_spent'] ?? 0));
            $start = $this->toEpoch($row['started_at'] ?? null);
            $end = $this->toEpoch($row['ended_at'] ?? null);
            if (!isset($byKey[$key])) {
                $byKey[$key] = ['recorded' => 0, 'unique' => 0, 'overlap' => 0, 'has_intervals' => false, 'intervals' => []];
            }
            $byKey[$key]['recorded'] += $minutes;
            if ($start !== null && $end !== null && $end > $start) {
                $byKey[$key]['has_intervals'] = true;
                $byKey[$key]['intervals'][] = ['start' => $start, 'end' => $end];
            } else {
                $byKey[$key]['unique'] += $minutes;
            }
        }

        $result = [];
        foreach ($byKey as $key => $data) {
            $analysis = $data['intervals'] !== []
                ? TimeOverlapMath::analyze($data['intervals'])
                : ['union_seconds' => 0, 'overlap_seconds' => 0];
            $result[$key] = [
                'recorded' => $data['recorded'],
                'unique' => $data['unique'] + (int)round($analysis['union_seconds'] / 60),
                'overlap' => (int)round($analysis['overlap_seconds'] / 60),
                'has_intervals' => $data['has_intervals'],
            ];
        }

        return $result;
    }

    /**
     * Merge the overlap-aware aggregates into SQL aggregate rows. Rows without
     * any interval data keep unique == recorded (legacy behaviour preserved).
     */
    private function mergeIntervalAggregation(array $sqlRows, array $aggregates): array
    {
        foreach ($sqlRows as &$row) {
            $key = (string)($row['user_public_id'] ?? '') . '|' . (string)($row['day'] ?? '');
            $row['recorded_minutes'] = (int)($row['total_minutes'] ?? 0);
            $over = $aggregates[$key] ?? null;
            if ($over !== null) {
                $row['unique_minutes'] = $over['unique'];
                $row['overlap_minutes'] = $over['overlap'];
                $row['has_intervals'] = $over['has_intervals'];
            } else {
                $row['unique_minutes'] = (int)($row['total_minutes'] ?? 0);
                $row['overlap_minutes'] = 0;
                $row['has_intervals'] = false;
            }
        }
        unset($row);

        return $sqlRows;
    }

    public function summary(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $rows = $this->worklogs->summaryByDay($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null);
        $aggregates = $this->aggregateIntervals(
            $this->worklogs->rowsForPeriod($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null)
        );

        return ['items' => $this->mergeIntervalAggregation($rows, $aggregates)];
    }

    public function earnings(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $teamPublicId = (string)($filters['team_public_id'] ?? '');
        $rows = $this->worklogs->earningsByDay($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null);
        $aggregates = $this->aggregateIntervals(
            $this->worklogs->rowsForPeriod($filters, $visibleUserIds, $actorIsRoot, $teamPublicId ?: null)
        );
        $rows = $this->mergeIntervalAggregation($rows, $aggregates);

        // Earnings are computed from the overlap-free unique time so parallel
        // timers never pay twice for the same wall-clock interval.
        foreach ($rows as &$row) {
            $uniqueMinutes = (int)($row['unique_minutes'] ?? 0);
            $row['cost_amount'] = round($uniqueMinutes / 60 * (float)($row['cost_rate'] ?? 0), 2);
            $row['bill_amount'] = round($uniqueMinutes / 60 * (float)($row['bill_rate'] ?? 0), 2);
        }
        unset($row);

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
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        return $this->worklogs->taskSummary($taskPublicId, $visibleUserIds, $actorIsRoot);
    }

    public function matrix(array $filters, array $actor): array
    {
        $visibleUserIds = $this->getVisibleUserIds($actor);
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

        $rows = $this->worklogs->matrixForPeriod($from, $to, $userPublicId ?: null, $projectPublicId ?: null, $teamUserPublicIds, $visibleUserIds, $actorIsRoot);

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

        // Overlap-aware unique time: replace each cell with the union of the
        // user's exact timer intervals for that day. Entries without an
        // interval (legacy timers, manual entries) keep their recorded minutes
        // — they cannot be de-duplicated.
        $aggregates = $this->aggregateIntervals($this->worklogs->rowsForMatrixPeriod(
            $from,
            $to,
            $userPublicId ?: null,
            $projectPublicId ?: null,
            $teamUserPublicIds,
            $visibleUserIds,
            $actorIsRoot
        ));
        foreach ($matrix as $day => $dayData) {
            foreach ($dayData as $uid => $mins) {
                $key = $uid . '|' . $day;
                if (isset($aggregates[$key])) {
                    $matrix[$day][$uid] = $aggregates[$key]['unique'];
                }
            }
        }
        $dayTotals = [];
        foreach ($matrix as $day => $dayData) {
            $total = 0;
            foreach ($dayData as $mins) {
                $total += $mins;
            }
            $dayTotals[$day] = $total;
        }

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
                    'id' => (int)($user['id'] ?? 0),
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
                    'id' => (int)$u['id'],
                    'public_id' => $pid,
                    'login' => $u['login'],
                    'full_name' => $u['full_name'],
                ];
            }
        }

        // Visibility: non-root users should only see users from their visible set
        if (!$actorIsRoot && $visibleUserIds !== []) {
            $users = array_values(array_filter($users, static fn(array $u): bool =>
                in_array($u['id'], $visibleUserIds, true)
            ));
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

        // Scope the team/project option lists to the actor's access so the
        // matrix response never exposes org-wide names to non-root users.
        $actorId = $this->resolveActorId($actor);
        $accessibleTeamPublicIds = $actorIsRoot
            ? []
            : $this->teamRepo->listAccessiblePublicIdsForUser($actorId);
        $teams = $this->worklogs->listTeams($actorIsRoot, $accessibleTeamPublicIds);
        $projects = $this->worklogs->listProjects($actorIsRoot, $actorId, $accessibleTeamPublicIds);

        // Filter teams based on active context (user/project selections)
        if (!empty($userPublicId) || !empty($projectPublicId) || $userSetKeys !== []) {
            $filteredTeams = [];
            $userIdsForTeams = $userSetKeys;
            if (!empty($userPublicId)) {
                $userIdsForTeams = [$userPublicId];
            }
            // Get teams that contain at least one active user
            foreach ($teams as $team) {
                $teamRow = (new QueryBuilder($this->worklogs->getPdo()))
                    ->from('teams')
                    ->select(['member_user_ids'])
                    ->where('public_id', '=', $team['public_id'])
                    ->first();
                if ($teamRow && !empty($teamRow['member_user_ids'])) {
                    $raw = $teamRow['member_user_ids'];
                    $memberIds = [];
                    if (is_string($raw) && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $memberIds = $decoded;
                        }
                    } elseif (is_array($raw)) {
                        $memberIds = $raw;
                    }
                    $memberPubIds = [];
                    if ($memberIds !== []) {
                        $idList = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn(int $v): bool => $v > 0)));
                        if ($idList !== []) {
                            $pubRows = (new QueryBuilder($this->worklogs->getPdo()))
                                ->from('users')
                                ->select(['public_id'])
                                ->whereIn('id', $idList)
                                ->get();
                            $memberPubIds = array_map(static fn(array $r): string => (string)$r['public_id'], $pubRows);
                        }
                    }
                    $found = false;
                    foreach ($userIdsForTeams as $uid) {
                        if (in_array($uid, $memberPubIds, true)) {
                            $found = true;
                            break;
                        }
                    }
                    if ($found) $filteredTeams[] = $team;
                }
            }
            $teams = $filteredTeams;
        }

        // Filter projects based on active context
        if (!empty($userPublicId) || !empty($teamPublicId) || $userSetKeys !== []) {
            $projectIdsInMatrix = [];
            if ($rows !== []) {
                // Get unique projects from the worklog data
                $projectQb = (new QueryBuilder($this->worklogs->getPdo()))
                    ->from('work_logs w')
                    ->join('tasks t', 't.id', '=', 'w.task_id')
                    ->select(['DISTINCT p.public_id', 'p.title'])
                    ->join('projects p', 'p.id', '=', 't.project_id');
                if (!empty($filters['from'])) $projectQb->where('w.logged_at', '>=', (string)$filters['from']);
                if (!empty($filters['to'])) $projectQb->where('w.logged_at', '<=', (string)$filters['to']);
                if (!empty($userPublicId)) {
                    $projectQb->join('users u', 'u.id', '=', 'w.user_id')->where('u.public_id', '=', $userPublicId);
                }
                if (!empty($teamPublicId)) {
                    $memberPubIds = $teamUserPublicIds ?? [];
                    if ($memberPubIds) $projectQb->join('users u2', 'u2.id', '=', 'w.user_id')->whereIn('u2.public_id', $memberPubIds);
                }
                // Object-level authorization: never re-derive projects from
                // worklogs of users the actor cannot see. The minutes rows are
                // already visibility-filtered; apply the same scope here.
                if (!$actorIsRoot && $visibleUserIds !== []) {
                    $projectQb->join('users u_vis', 'u_vis.id', '=', 'w.user_id')->whereIn('u_vis.id', $visibleUserIds);
                }
                $projectQb->orderBy('p.title', 'ASC');
                $projects = $projectQb->get();
            } else {
                $projects = [];
            }
        }

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
        $visibleUserIds = $this->getVisibleUserIds($actor);
        $actorIsRoot = (bool)($actor['is_root'] ?? false);
        $rows = $this->worklogs->detailByDayUser($day, $userPublicId, $projectPublicId, $visibleUserIds, $actorIsRoot);

        $recorded = 0;
        $legacy = 0;
        $intervals = [];
        $entryTitles = [];
        foreach ($rows as $index => $row) {
            $minutes = max(0, (int)$row['minutes_spent']);
            $recorded += $minutes;
            $start = $this->toEpoch($row['started_at'] ?? null);
            $end = $this->toEpoch($row['ended_at'] ?? null);
            if ($start !== null && $end !== null && $end > $start) {
                $entryTitles[(string)$index] = (string)($row['task_title'] ?? '');
                $intervals[] = ['key' => (string)$index, 'start' => $start, 'end' => $end];
            } else {
                $legacy += $minutes;
            }
        }

        $analysis = $intervals !== []
            ? TimeOverlapMath::analyze($intervals)
            : ['union_seconds' => 0, 'overlap_seconds' => 0, 'segments' => []];
        $unique = $legacy + (int)round($analysis['union_seconds'] / 60);
        $overlap = (int)round($analysis['overlap_seconds'] / 60);

        $segments = [];
        foreach ($analysis['segments'] as $segment) {
            // Only genuinely overlapping slices are reported (count > 1).
            if (($segment['count'] ?? 0) <= 1) {
                continue;
            }
            $tasks = [];
            foreach ($segment['entries'] ?? [] as $entryIndex) {
                $title = $entryTitles[$entryIndex] ?? '';
                if ($title !== '' && !in_array($title, $tasks, true)) {
                    $tasks[] = $title;
                }
            }
            $segments[] = [
                'from' => gmdate('Y-m-d H:i:s', (int)$segment['from']),
                'to' => gmdate('Y-m-d H:i:s', (int)$segment['to']),
                'seconds' => (int)$segment['seconds'],
                'count' => (int)$segment['count'],
                'tasks' => $tasks,
            ];
        }

        return [
            'items' => $rows,
            'total_minutes' => $recorded,
            'recorded_minutes' => $recorded,
            'unique_minutes' => $unique,
            'overlap_minutes' => $overlap,
            'has_intervals' => $intervals !== [],
            'segments' => $segments,
            'day' => $day,
            'user_public_id' => $userPublicId,
        ];
    }
}
