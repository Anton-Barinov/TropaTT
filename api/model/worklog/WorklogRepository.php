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

    public function list(array $filters, int $actorUserId, bool $actorIsRoot): array
    {
        $page = max(1, (int)($filters['page'] ?? 1));
        $limit = min(100, max(1, (int)($filters['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $total = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)->count();
        $items = $this->buildListQuery($filters, $actorUserId, $actorIsRoot)
            ->select([
                'w.public_id',
                'w.minutes_spent',
                'w.note',
                'w.logged_at',
                'w.created_at',
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

    private function buildListQuery(array $filters, int $actorUserId, bool $actorIsRoot): QueryBuilder
    {
        $query = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->leftJoin('users u', 'u.id', '=', 'w.user_id')
            ->leftJoin('tasks t', 't.id', '=', 'w.task_id');

        if (!$actorIsRoot) {
            $query->where('w.user_id', '=', $actorUserId);
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
                'w.public_id',
                'w.minutes_spent',
                'w.note',
                'w.logged_at',
                'w.created_at',
                'w.user_id',
                'u.public_id AS user_public_id',
                'u.login AS user_login',
                'u.full_name AS user_full_name',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('w.public_id', '=', $publicId)
            ->first();
    }

    public function findUserByPublicId(string $publicId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->where('public_id', '=', $publicId)
            ->first();
    }

    /** @return array<int, array{public_id: string, login: string, full_name: string}> */
    public function activeUsers(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['public_id', 'login', 'full_name'])
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
     * @return array<int, array{user_public_id: string, user_login: string, user_full_name: string, total_minutes: int, day: string}>
     */
    public function summaryByDay(array $filters, int $actorUserId, bool $actorIsRoot): array
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
            ])
            ->groupBy(['u.id', 'DATE(w.logged_at)'])
            ->orderBy('day', 'DESC')
            ->orderBy('u.full_name', 'ASC');

        if (!$actorIsRoot) {
            $qb->where('w.user_id', '=', $actorUserId);
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

        return $qb->get();
    }

    /**
     * @return array<int, array{user_public_id: string, user_login: string, user_full_name: string, total_minutes: int, cost_rate: ?float, bill_rate: ?float, cost_amount: float, bill_amount: float, day: string}>
     */
    public function earningsByDay(array $filters, int $actorUserId, bool $actorIsRoot): array
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
            ])
            ->groupBy(['u.id', 'DATE(w.logged_at)'])
            ->orderBy('day', 'DESC')
            ->orderBy('u.full_name', 'ASC');

        if (!$actorIsRoot) {
            $qb->where('w.user_id', '=', $actorUserId);
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

        return $qb->get();
    }

    /**
     * @return array{total_minutes: int, user_breakdown: array<int, array{user_public_id: string, user_login: string, user_full_name: string, total_minutes: int, cost_rate: ?float, bill_rate: ?float}>}
     */
    public function taskSummary(string $taskPublicId): array
    {
        $breakdown = (new QueryBuilder($this->pdo))
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
            ])
            ->where('t.public_id', '=', $taskPublicId)
            ->groupBy(['u.id'])
            ->orderBy('total_minutes', 'DESC')
            ->get();

        $total = 0;
        foreach ($breakdown as $row) {
            $total += (int)$row['total_minutes'];
        }

        return [
            'total_minutes' => $total,
            'user_breakdown' => $breakdown,
        ];
    }

    /**
     * @return array<int, array{day: string, user_public_id: string, total_minutes: int}>
     */
    public function matrixForPeriod(string $dateFrom, string $dateTo, ?string $userPublicId, ?string $teamPublicId, ?string $projectPublicId, int $actorUserId, bool $actorIsRoot): array
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
            ->groupBy(['DATE(w.logged_at)', 'u.id'])
            ->orderBy('day', 'ASC')
            ->orderBy('u.full_name', 'ASC');

        if (!$actorIsRoot) {
            $qb->where('w.user_id', '=', $actorUserId);
        }
        if (!empty($userPublicId)) {
            $qb->where('u.public_id', '=', $userPublicId);
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
                    if (is_array($decoded)) $memberIds = $decoded;
                } elseif (is_array($raw)) {
                    $memberIds = $raw;
                }
                if ($memberIds !== []) {
                    $memberIds = array_values(array_unique(array_filter(array_map('intval', $memberIds), static fn(int $v): bool => $v > 0)));
                    if ($memberIds !== []) {
                        $qb->whereIn('u.id', $memberIds);
                    }
                }
            }
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
     * @return array<int, array{task_public_id: string, task_title: string, minutes_spent: int, note: ?string}>
     */
    public function detailByDayUser(string $day, string $userPublicId, ?string $projectPublicId, int $actorUserId, bool $actorIsRoot): array
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
            ])
            ->where('DATE(w.logged_at)', '=', $day)
            ->where('u.public_id', '=', $userPublicId)
            ->orderBy('t.title', 'ASC')
            ->orderBy('w.logged_at', 'ASC');

        if (!$actorIsRoot) {
            $qb->where('w.user_id', '=', $actorUserId);
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

    /** @return array<int, array{public_id: string, title: string}> */
    public function listTeams(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('teams')
            ->select(['public_id', 'title'])
            ->orderBy('title', 'ASC')
            ->get();
    }

    /** @return array<int, array{public_id: string, title: string}> */
    public function listProjects(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('projects')
            ->select(['public_id', 'title'])
            ->where('archived_at', 'IS', null)
            ->orderBy('title', 'ASC')
            ->get();
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
            ->whereRaw('JSON_CONTAINS(member_user_ids, CAST(? AS JSON))', [(string)$userId])
            ->first();
        return $row !== null;
    }
}
