<?php
declare(strict_types=1);

namespace Api\Model\Worklog;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

final class WorklogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function list(array $filters, array $visibleUserIds, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $visibleUserIds, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $visibleUserIds, $actorIsRoot)
            ->select([
                'w.public_id',
                'w.minutes_spent',
                'w.note',
                'w.logged_at',
                'w.started_at',
                'w.ended_at',
                'w.created_at',
                'w.activity_code',
                'w.cost_rate_snapshot',
                'w.bill_rate_snapshot',
                'w.payout_rate_snapshot',
                'w.currency_code',
                'w.cost_source_type',
                'w.cost_source_ref',
                'w.bill_source_type',
                'w.bill_source_ref',
                'w.payout_source_type',
                'w.payout_source_ref',
                'w.rate_resolved_at',
                'w.rate_ambiguous',
                'w.client_public_id',
                'w.project_public_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->orderBy('w.logged_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return [$items, $total, $page, $limit];
    }

    private function buildListQuery(array $filters, array $visibleUserIds, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->leftJoin('users u', 'u.id', '=', 'w.user_id')
            ->leftJoin('tasks t', 't.id', '=', 'w.task_id');

        if (!$actorIsRoot && $visibleUserIds !== []) {
            $query->whereIn('w.user_id', $visibleUserIds);
        }

        if (!empty($filters['user_public_id'])) {
            $query->where('u.public_id', '=', (string)$filters['user_public_id']);
        }

        if (!empty($filters['task_public_id'])) {
            $query->where('t.public_id', '=', (string)$filters['task_public_id']);
        }

        if (!empty($filters['from'])) {
            $query->where('w.logged_at', '>=', (string)$filters['from']);
        }

        if (!empty($filters['to'])) {
            $query->where('w.logged_at', '<=', (string)$filters['to']);
        }

        return $query;
    }

    public function create(array $payload): void
    {
        (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->insert($payload);
    }

    public function findByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->leftJoin('users u', 'u.id', '=', 'w.user_id')
            ->leftJoin('tasks t', 't.id', '=', 'w.task_id')
            ->select([
                'w.id',
                'w.public_id',
                'w.minutes_spent',
                'w.task_id',
                'w.note',
                'w.logged_at',
                'w.started_at',
                'w.ended_at',
                'w.created_at',
                'w.activity_code',
                'w.user_id',
                'w.cost_rate_snapshot',
                'w.bill_rate_snapshot',
                'w.payout_rate_snapshot',
                'w.currency_code',
                'w.cost_source_type',
                'w.cost_source_ref',
                'w.bill_source_type',
                'w.bill_source_ref',
                'w.payout_source_type',
                'w.payout_source_ref',
                'w.rate_resolved_at',
                'w.rate_ambiguous',
                'w.client_public_id',
                'w.project_public_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('w.public_id', '=', $publicId)
            ->first();
    }

    /**
     * Raw worklog entries of a user on a UTC day, used to evaluate the
     * time-tracking conditions of worklog_logged automation rules.
     *
     * @param int $userId worklog owner
     * @param int|null $taskId task scope filter: 0 = no filter (all tasks),
     *        null = only task-less entries, >0 = that task only
     * @param string $day UTC date in Y-m-d format
     * @return array<int,array<string,mixed>>
     */
    public function automationEntriesByDay(int $userId, ?int $taskId, string $day): array
    {
        $from = $day . ' 00:00:00';
        $to = gmdate('Y-m-d H:i:s', strtotime($from) + 86400);
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->select(['id', 'public_id', 'minutes_spent', 'started_at', 'ended_at', 'logged_at'])
            ->where('user_id', '=', $userId)
            ->where('logged_at', '>=', $from)
            ->where('logged_at', '<', $to);

        if ($taskId === null) {
            $qb->whereNull('task_id');
        } elseif ($taskId > 0) {
            $qb->where('task_id', '=', $taskId);
        }
        // $taskId === 0: no task filter — every worklog of the user counts.

        return $qb->get();
    }

    public function findUserByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'public_id', 'login', 'full_name'])
            ->where('public_id', '=', $publicId)
            ->first();
    }

    /** @return array<int, array{id: int, public_id: string, login: string, full_name: string}> */
    public function activeUsers(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id', 'public_id', 'login', 'full_name'])
            ->where('is_active', '=', 1)
            ->orderBy('full_name', 'ASC')
            ->get();
    }

    public function updateByPublicId(string $publicId, array $set): bool
    {
        if ($set === []) {
            return false;
        }

        return (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('public_id', '=', $publicId)
            ->update($set) > 0;
    }

    public function deleteByPublicId(string $publicId): bool
    {
        return (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->where('public_id', '=', $publicId)
            ->delete() > 0;
    }

    /**
     * Apply the common worklog scope (visibility, date range, user/project/
     * team filters) to a query builder that already joins `users u`.
     */
    private function applyCommonFilters(QueryBuilder $qb, array $filters, array $visibleUserIds, bool $actorIsRoot, ?string $teamPublicId): void
    {
        if (!$actorIsRoot && $visibleUserIds !== []) {
            $qb->whereIn('w.user_id', $visibleUserIds);
        }

        if (!empty($filters['from'])) {
            $qb->where('w.logged_at', '>=', (string)$filters['from']);
        }
        if (!empty($filters['to'])) {
            $qb->where('w.logged_at', '<=', (string)$filters['to']);
        }
        if (!empty($filters['user_public_id'])) {
            $qb->where('u.public_id', '=', (string)$filters['user_public_id']);
        }
        if (!empty($filters['client_public_id'])) {
            $qb->where('w.client_public_id', '=', (string)$filters['client_public_id']);
        }
        if (!empty($filters['activity_code'])) {
            $qb->where('w.activity_code', '=', (string)$filters['activity_code']);
        }
        if (!empty($filters['only_ambiguous'])) {
            $qb->where('w.rate_ambiguous', '=', 1);
        }
        if (!empty($filters['project_public_id'])) {
            $qb->join('tasks t', 't.id', '=', 'w.task_id');
            $project = (new QueryBuilder($this->pdo))
                ->from('projects')
                ->select(['id'])
                ->where('public_id', '=', (string)$filters['project_public_id'])
                ->first();
            if ($project) {
                $qb->where('t.project_id', '=', (int)$project['id']);
            }
        }
        if (!empty($teamPublicId)) {
            $team = (new QueryBuilder($this->pdo))
                ->from('teams')
                ->select(['member_user_ids'])
                ->where('public_id', '=', $teamPublicId)
                ->first();
            if ($team && isset($team['member_user_ids'])) {
                $raw = $team['member_user_ids'];
                $memberIds = [];
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        foreach ($decoded as $v) {
                            $iv = (int)$v;
                            if ($iv > 0) $memberIds[] = $iv;
                        }
                    }
                } elseif (is_array($raw)) {
                    foreach ($raw as $v) {
                        $iv = (int)$v;
                        if ($iv > 0) $memberIds[] = $iv;
                    }
                }
                if ($memberIds !== []) {
                    $memberIds = array_values(array_unique($memberIds));
                    $qb->whereIn('u.id', $memberIds);
                }
            }
        }
    }

    /**
     * Fetch the raw worklog rows of a period (with their exact timer intervals
     * when present) so the service can compute overlap-free unique time.
     *
     * @return array<int, array{user_public_id: string, day: string, minutes_spent: int, started_at: ?string, ended_at: ?string}>
     */
    public function rowsForPeriod(array $filters, array $visibleUserIds, bool $actorIsRoot, ?string $teamPublicId = null): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->select([
                'u.public_id AS user_public_id',
                'DATE(w.logged_at) AS day',
                'w.minutes_spent',
                'w.started_at',
                'w.ended_at',
            ]);
        $this->applyCommonFilters($qb, $filters, $visibleUserIds, $actorIsRoot, $teamPublicId);

        return $qb->get();
    }

    /**
     * Per-row data with snapshot rates and intervals for earnings computation (TZ 2.9).
     *
     * Unlike rowsForPeriod(), this returns individual worklog rows with their
     * snapshot rates so billable minutes can be distributed per-row and
     * amounts computed from the snapshot at the moment the worklog was created.
     *
     * @return array<int, array{public_id: string, user_public_id: string, user_login: string, user_full_name: string, day: string, minutes_spent: int, started_at: ?string, ended_at: ?string, cost_rate_snapshot: ?float, bill_rate_snapshot: ?float, payout_rate_snapshot: ?float, currency_code: ?string, cost_source_type: ?string, bill_source_type: ?string, payout_source_type: ?string, rate_ambiguous: int, rate_locked_at: ?string, activity_code: ?string}>
     */
    public function earningsRowsForPeriod(array $filters, array $visibleUserIds, bool $actorIsRoot, ?string $teamPublicId = null): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->leftJoin('counterparties cp', 'cp.public_id', '=', 'w.client_public_id')
            ->leftJoin('projects pr', 'pr.public_id', '=', 'w.project_public_id')
            ->select([
                'w.public_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                'DATE(w.logged_at) AS day',
                'w.minutes_spent',
                'w.started_at',
                'w.ended_at',
                'w.cost_rate_snapshot',
                'w.bill_rate_snapshot',
                'w.payout_rate_snapshot',
                'w.currency_code',
                'w.cost_source_type',
                'w.bill_source_type',
                'w.payout_source_type',
                'w.cost_source_ref',
                'w.bill_source_ref',
                'w.payout_source_ref',
                'w.rate_ambiguous',
                'w.rate_locked_at',
                'w.rate_resolved_at',
                'w.activity_code',
                'w.client_public_id',
                'w.project_public_id',
                'cp.title AS client_title',
                'pr.title AS project_title',
                'u.cost_rate',
                'u.bill_rate',
                'u.payout_rate',
            ]);
        $this->applyCommonFilters($qb, $filters, $visibleUserIds, $actorIsRoot, $teamPublicId);
        $qb->orderBy('w.logged_at', 'ASC');

        return $qb->get();
    }

    /**
     * Same as rowsForPeriod() but with the matrix filter shape (plain date
     * range, optional user/project public ids and team member id list).
     *
     * @param string[]|null $teamUserPublicIds
     * @return array<int, array{user_public_id: string, day: string, minutes_spent: int, started_at: ?string, ended_at: ?string}>
     */
    public function rowsForMatrixPeriod(string $dateFrom, string $dateTo, ?string $userPublicId, ?string $projectPublicId, ?array $teamUserPublicIds, array $visibleUserIds, bool $actorIsRoot): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->join('tasks t', 't.id', '=', 'w.task_id')
            ->select([
                'u.public_id AS user_public_id',
                'DATE(w.logged_at) AS day',
                'w.minutes_spent',
                'w.started_at',
                'w.ended_at',
            ])
            ->where('w.logged_at', '>=', $dateFrom)
            ->where('w.logged_at', '<', date('Y-m-d', strtotime($dateTo . ' +1 day')));

        if (!$actorIsRoot && $visibleUserIds !== []) {
            $qb->whereIn('w.user_id', $visibleUserIds);
        }
        if (!empty($userPublicId)) {
            $qb->where('u.public_id', '=', $userPublicId);
        }
        if (!empty($projectPublicId)) {
            $project = (new QueryBuilder($this->pdo))
                ->from('projects')
                ->select(['id'])
                ->where('public_id', '=', $projectPublicId)
                ->first();
            if ($project) {
                $qb->where('t.project_id', '=', (int)$project['id']);
            }
        }
        if (!empty($teamUserPublicIds)) {
            $qb->whereIn('u.public_id', $teamUserPublicIds);
        }

        return $qb->get();
    }

    /**
     * @return array<int, array{user_public_id: string, user_login: string, user_full_name: string, total_minutes: int, day: string}>
     */
    public function summaryByDay(array $filters, array $visibleUserIds, bool $actorIsRoot, ?string $teamPublicId = null): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->select([
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                'DATE(w.logged_at) AS day',
                'SUM(w.minutes_spent) AS total_minutes',
            ]);
        $this->applyCommonFilters($qb, $filters, $visibleUserIds, $actorIsRoot, $teamPublicId);
        $qb->groupBy(['u.id', 'u.public_id', 'u.login', 'u.full_name', 'DATE(w.logged_at)'])
            ->orderBy('day', 'DESC')
            ->orderBy('u.full_name', 'ASC');

        return $qb->get();
    }

    /**
     * @return array<int, array{user_public_id: string, user_login: string, user_full_name: string, total_minutes: int, cost_rate: ?float, bill_rate: ?float, cost_amount: float, bill_amount: float, day: string}>
     */
    public function earningsByDay(array $filters, array $visibleUserIds, bool $actorIsRoot, ?string $teamPublicId = null): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->select([
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                'DATE(w.logged_at) AS day',
                'SUM(w.minutes_spent) AS total_minutes',
                'u.cost_rate',
                'u.bill_rate',
                'ROUND(SUM(w.minutes_spent) / 60 * COALESCE(u.cost_rate, 0), 2) AS cost_amount',
                'ROUND(SUM(w.minutes_spent) / 60 * COALESCE(u.bill_rate, 0), 2) AS bill_amount',
            ]);
        $this->applyCommonFilters($qb, $filters, $visibleUserIds, $actorIsRoot, $teamPublicId);
        $qb->groupBy(['u.id', 'u.public_id', 'u.login', 'u.full_name', 'u.cost_rate', 'u.bill_rate', 'DATE(w.logged_at)'])
            ->orderBy('day', 'DESC')
            ->orderBy('u.full_name', 'ASC');

        return $qb->get();
    }

    /**
     * @return array{total_minutes: int, user_breakdown: array<int, array{user_public_id: string, user_login: string, user_full_name: string, total_minutes: int, cost_rate: ?float, bill_rate: ?float, payout_rate: ?float, cost_amount: float, bill_amount: float, payout_amount: float}>}
     */
    public function taskSummary(string $taskPublicId, array $visibleUserIds, bool $actorIsRoot): array
    {
        // Amounts are summed per-row from snapshot rates (TZ 5.1). Historical
        // rows without a snapshot (rate_resolved_at IS NULL) fall back to the
        // user's live global rate so pre-migration data still reports money.
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->join('tasks t', 't.id', '=', 'w.task_id')
            ->select([
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                'SUM(w.minutes_spent) AS total_minutes',
                'u.cost_rate',
                'u.bill_rate',
                'u.payout_rate',
                'ROUND(SUM(w.minutes_spent * COALESCE(w.cost_rate_snapshot, u.cost_rate, 0) / 60), 2) AS cost_amount',
                'ROUND(SUM(w.minutes_spent * COALESCE(w.bill_rate_snapshot, u.bill_rate, 0) / 60), 2) AS bill_amount',
                'ROUND(SUM(w.minutes_spent * COALESCE(w.payout_rate_snapshot, u.payout_rate, 0) / 60), 2) AS payout_amount',
            ])
            ->where('t.public_id', '=', $taskPublicId)
            ->groupBy(['u.id', 'u.public_id', 'u.login', 'u.full_name', 'u.cost_rate', 'u.bill_rate', 'u.payout_rate'])
            ->orderBy('total_minutes', 'DESC');

        if (!$actorIsRoot && $visibleUserIds !== []) {
            $qb->whereIn('w.user_id', $visibleUserIds);
        }

        $rows = $qb->get();

        $total = 0;
        foreach ($rows as $row) {
            $total += (int)$row['total_minutes'];
        }

        return [
            'total_minutes' => $total,
            'user_breakdown' => $rows,
        ];
    }

    /**
     * @return array<int, array{day: string, user_public_id: string, total_minutes: int}>
     */
    public function matrixForPeriod(string $dateFrom, string $dateTo, ?string $userPublicId, ?string $projectPublicId, ?array $teamUserPublicIds, array $visibleUserIds, bool $actorIsRoot): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->join('tasks t', 't.id', '=', 'w.task_id')
            ->select([
                'DATE(w.logged_at) AS day',
                'u.public_id AS user_public_id',
                'SUM(w.minutes_spent) AS total_minutes',
            ])
            ->where('w.logged_at', '>=', $dateFrom)
            ->where('w.logged_at', '<', date('Y-m-d', strtotime($dateTo . ' +1 day')))
            ->groupBy(['DATE(w.logged_at)', 'u.id', 'u.public_id'])
            ->orderBy('day', 'ASC')
            ->orderBy('u.full_name', 'ASC');

        if (!$actorIsRoot && $visibleUserIds !== []) {
            $qb->whereIn('w.user_id', $visibleUserIds);
        }
        if (!empty($userPublicId)) {
            $qb->where('u.public_id', '=', $userPublicId);
        }
        if (!empty($projectPublicId)) {
            $project = (new QueryBuilder($this->pdo))
                ->from('projects')
                ->select(['id'])
                ->where('public_id', '=', $projectPublicId)
                ->first();
            if ($project) {
                $qb->where('t.project_id', '=', (int)$project['id']);
            }
        }
        if (!empty($teamUserPublicIds)) {
            $qb->whereIn('u.public_id', $teamUserPublicIds);
        }

        return $qb->get();
    }

    /**
     * @return array<int, array{task_public_id: string, task_title: string, minutes_spent: int, note: ?string}>
     */
    public function detailByDayUser(string $day, string $userPublicId, ?string $projectPublicId, array $visibleUserIds, bool $actorIsRoot): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->join('users u', 'u.id', '=', 'w.user_id')
            ->join('tasks t', 't.id', '=', 'w.task_id')
            ->select([
                't.public_id AS task_public_id',
                't.title AS task_title',
                'w.minutes_spent',
                'w.note',
                'w.logged_at',
                'w.started_at',
                'w.ended_at',
            ])
            ->where('DATE(w.logged_at)', '=', $day)
            ->where('u.public_id', '=', $userPublicId)
            ->orderBy('t.title', 'ASC')
            ->orderBy('w.logged_at', 'ASC');

        if (!$actorIsRoot && $visibleUserIds !== []) {
            $qb->whereIn('w.user_id', $visibleUserIds);
        }
        if (!empty($projectPublicId)) {
            $project = (new QueryBuilder($this->pdo))
                ->from('projects')
                ->select(['id'])
                ->where('public_id', '=', $projectPublicId)
                ->first();
            if ($project) {
                $qb->where('t.project_id', '=', (int)$project['id']);
            }
        }

        return $qb->get();
    }

    /**
     * @param bool $actorIsRoot Root actors always see every team.
     * @param string[] $accessibleTeamPublicIds Accessible team public IDs. For
     *     non-root actors an empty list means "no teams" (fail-closed), NOT
     *     "all teams".
     * @return array<int, array{public_id: string, title: string}>
     */
    public function listTeams(bool $actorIsRoot, array $accessibleTeamPublicIds = []): array
    {
        // Root sees all teams. For non-root actors an empty accessible list
        // must mean "no teams", never "all teams" (fail-closed).
        if (!$actorIsRoot && $accessibleTeamPublicIds === []) {
            return [];
        }

        $qb = (new QueryBuilder($this->pdo))
            ->from('teams')
            ->select(['public_id', 'title']);
        if ($accessibleTeamPublicIds !== []) {
            $qb->whereIn('public_id', $accessibleTeamPublicIds);
        }
        return $qb->orderBy('title', 'ASC')->get();
    }

    /**
     * @param string[] $accessibleTeamPublicIds
     * @return array<int, array{public_id: string, title: string}>
     */
    public function listProjects(bool $actorIsRoot, int $actorUserId, array $accessibleTeamPublicIds = []): array
    {
        $qb = (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['public_id', 'title'])
            ->where('archived_at', 'IS', null);

        if (!$actorIsRoot) {
            $params = [$actorUserId, $actorUserId];
            $sql = '(created_by_user_id = ? OR manager_user_id = ?';
            if ($accessibleTeamPublicIds !== []) {
                $placeholders = implode(', ', array_fill(0, count($accessibleTeamPublicIds), '?'));
                $sql .= ' OR team_public_id IN (' . $placeholders . ')';
                $params = array_merge($params, $accessibleTeamPublicIds);
            }
            $qb->whereRaw($sql . ')', $params);
        }

        return $qb->orderBy('title', 'ASC')->get();
    }

    public function userInTeam(string $userPublicId, string $teamPublicId): bool
    {
        $user = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['id'])
            ->where('public_id', '=', $userPublicId)
            ->first();
        if (!$user) return false;
        $userId = (int)$user['id'];

        $row = (new QueryBuilder($this->pdo))
            ->from('teams')
            ->select(['id'])
            ->where('public_id', '=', $teamPublicId)
            // CAST(... AS JSON) is MySQL-only (MariaDB syntax error); bind the
            // JSON fragment literal instead - portable across MySQL and MariaDB.
            ->whereRaw('JSON_CONTAINS(member_user_ids, ?)', [json_encode($userId)])
            ->first();
        return $row !== null;
    }

    public function getPdo(): \PDO
    {
        return $this->pdo;
    }
}
