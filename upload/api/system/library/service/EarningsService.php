<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Rate\RateCardRepository;
use Api\Model\Worklog\WorklogRepository;
use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

/**
 * Self-scoped earnings queries (TZ 7.1-7.2).
 * Separate from WorklogService because these are strictly actor-own queries
 * and run against snapshots with FinancialFieldPolicy gating externally.
 */
final class EarningsService
{
    public function __construct(
        private readonly WorklogRepository $worklogs,
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Get the actor's own earnings for a date range.
     *
     * Returns per-day summaries: recorded/unique minutes, payout rate,
     * payout amount, locked indicator, project/task breakdown.
     *
     * @param array $actor  ['id' => int, 'public_id' => string, 'is_root' => bool, ...]
     * @return array{items: array, has_any_payout: bool, period_locked: bool}
     */
    public function myEarnings(array $actor, string $from, string $to): array
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return ['items' => [], 'has_any_payout' => false, 'period_locked' => false];
        }

        // Per-day aggregate with snapshot rates (TZ 7.1)
        $rows = (new QueryBuilder($this->pdo))
            ->from('work_logs w')
            ->leftJoin('tasks t', 't.id', '=', 'w.task_id')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                'DATE(w.logged_at) AS day',
                'SUM(w.minutes_spent) AS recorded_minutes',
                'AVG(w.payout_rate_snapshot) AS payout_rate',
                'ROUND(SUM(w.minutes_spent * COALESCE(w.payout_rate_snapshot, 0) / 60), 2) AS payout_amount',
                'w.currency_code',
                'MAX(w.rate_locked_at) AS rate_locked_at',
                'p.public_id AS project_public_id',
                'p.title AS project_title',
                't.public_id AS task_public_id',
                't.title AS task_title',
            ])
            ->where('w.user_id', '=', $userId)
            ->where('w.logged_at', '>=', $from)
            ->where('w.logged_at', '<=', $to . ' 23:59:59')
            ->groupBy(['DATE(w.logged_at)', 'w.currency_code', 't.id', 'p.id'])
            ->orderBy('day', 'DESC')
            ->orderBy('p.title', 'ASC')
            ->get();

        $hasAny = false;
        $periodLocked = false;
        $actorPublicId = (string)($actor['public_id'] ?? '');
        $items = [];
        foreach ($rows as $row) {
            // Self-scoped: every row belongs to the actor. Tag it so the
            // FinancialFieldPolicy recognizes it as "own" (TZ 6.2 ownership).
            $row['user_public_id'] = $actorPublicId;
            $items[] = $row;
            if (($row['payout_rate'] ?? null) !== null) {
                $hasAny = true;
            }
            if (($row['rate_locked_at'] ?? null) !== null) {
                $periodLocked = true;
            }
        }

        return [
            'items' => $items,
            'has_any_payout' => $hasAny,
            'period_locked' => $periodLocked,
        ];
    }

    /**
     * Lightweight availability check (TZ 7.2).
     * Returns true if the actor has at least one worklog with
     * a non-null payout_rate_snapshot or the user has a global payout_rate.
     */
    public function hasPayoutData(array $actor): bool
    {
        $userId = (int)($actor['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }

        // Check work_logs first (indexed by idx_work_logs_payout)
        $hasLog = (new QueryBuilder($this->pdo))
            ->from('work_logs')
            ->select(['id'])
            ->where('user_id', '=', $userId)
            ->where('payout_rate_snapshot', 'IS NOT', null)
            ->limit(1)
            ->first();

        if ($hasLog) {
            return true;
        }

        // Check user-level rate
        $user = (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['payout_rate'])
            ->where('id', '=', $userId)
            ->first();

        return ($user['payout_rate'] ?? null) !== null;
    }
}