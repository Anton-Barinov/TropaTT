<?php
declare(strict_types=1);

namespace Api\Model\Rate;

use Api\System\Library\Database\Builder\QueryBuilder;
use PDO;

/**
 * Read-model repository for rate cards, lines and assignments.
 *
 * All full-text methods (list, create, update, delete) live in RateCardService
 * when needed. This repository provides the resolution-oriented queries:
 * assignment lookups and line lookups for the rate resolution algorithm.
 */
final class RateCardRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * Get rate card assignment IDs for a given scope on a given date.
     *
     * @return array<int, array{rate_card_id: int, public_id: string, priority: int}>
     */
    public function activeAssignments(string $scopeType, string $scopeRef, string $date): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('rate_card_assignments')
            ->select(['rate_card_id', 'public_id', 'priority'])
            ->where('scope_type', '=', $scopeType)
            ->where('scope_ref', '=', $scopeRef)
            ->where('deleted_at', 'IS', null)
            ->where('effective_from', '<=', $date)
            ->whereRaw('(effective_to IS NULL OR effective_to >= ?)', [$date])
            ->orderBy('priority', 'ASC')
            ->get();
    }

    /**
     * Get the active default rate card (non-archived, non-deleted).
     */
    public function defaultCard(): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('rate_cards')
            ->select(['id', 'public_id', 'currency_code'])
            ->where('is_default', '=', 1)
            ->where('is_archived', '=', 0)
            ->where('deleted_at', 'IS', null)
            ->first();
    }

    /**
     * Get a rate card by its integer ID.
     */
    public function findCardById(int $id): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('rate_cards')
            ->select(['id', 'public_id', 'currency_code', 'is_archived', 'is_default'])
            ->where('id', '=', $id)
            ->where('deleted_at', 'IS', null)
            ->first();
    }

    /**
     * Get candidate lines for a set of rate cards, filtered by user, activity
     * and roles, active on the given date.
     *
     * @param int[] $cardIds
     * @param int $userId
     * @param string|null $activityCode
     * @param string[] $roleCodes
     * @param string $date  'Y-m-d'
     * @return array<int, array>
     */
    public function candidateLines(
        array $cardIds,
        int $userId,
        ?string $activityCode,
        array $roleCodes,
        string $date
    ): array {
        if ($cardIds === []) {
            return [];
        }

        $qb = (new QueryBuilder($this->pdo))
            ->from('rate_card_lines l')
            ->select([
                'l.id', 'l.public_id', 'l.rate_card_id',
                'l.user_id', 'l.role_code', 'l.activity_code',
                'l.cost_rate', 'l.bill_rate', 'l.payout_rate',
                'l.currency_code', 'l.effective_from',
            ])
            ->where('l.deleted_at', 'IS', null)
            ->where('l.effective_from', '<=', $date)
            ->whereRaw('(l.effective_to IS NULL OR l.effective_to >= ?)', [$date])
            ->whereIn('l.rate_card_id', $cardIds);

        // user_id filter
        $qb->whereRaw('(l.user_id IS NULL OR l.user_id = ?)', [$userId]);

        // activity_code filter
        if ($activityCode !== null && $activityCode !== '') {
            $qb->whereRaw('(l.activity_code IS NULL OR l.activity_code = ?)', [$activityCode]);
        } else {
            $qb->where('l.activity_code', 'IS', null);
        }

        // role_code filter
        if ($roleCodes !== []) {
            $qb->whereRaw(
                sprintf(
                    '(l.role_code IS NULL OR l.role_code IN (%s))',
                    implode(',', array_fill(0, count($roleCodes), '?'))
                ),
                $roleCodes
            );
        } else {
            $qb->where('l.role_code', 'IS', null);
        }

        return $qb->get();
    }

    /**
     * Get user-level rates for resolution fallback.
     */
    public function userRates(int $userId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('users')
            ->select(['cost_rate', 'bill_rate', 'payout_rate'])
            ->where('id', '=', $userId)
            ->where('deleted_at', 'IS', null)
            ->first();
    }

    /**
     * Get task with project/client context for rate resolution.
     */
    public function taskContext(int $taskId): ?array
    {
        return (new QueryBuilder($this->pdo))
            ->from('tasks t')
            ->leftJoin('projects p', 'p.id', '=', 't.project_id')
            ->select([
                't.id AS task_id',
                't.activity_code',
                't.client_public_id AS task_client_public_id',
                't.project_id',
                't.override_cost_rate',
                't.override_bill_rate',
                't.override_payout_rate',
                'p.public_id AS project_public_id',
                'p.client_public_id AS project_client_public_id',
            ])
            ->where('t.id', '=', $taskId)
            ->where('t.deleted_at', 'IS', null)
            ->first();
    }

    /**
     * Get role codes for a user.
     *
     * @return string[]
     */
    public function userRoleCodes(int $userId): array
    {
        $rows = (new QueryBuilder($this->pdo))
            ->from('user_roles ur')
            ->join('roles r', 'r.id', '=', 'ur.role_id')
            ->select(['r.code'])
            ->where('ur.user_id', '=', $userId)
            ->get();

        return array_map(static fn(array $r): string => (string)$r['code'], $rows);
    }

    /**
     * Get all active rate card assignments for batch resolution.
     *
     * Suitable for preloading in recalculate operations to avoid N+1.
     *
     * @return array<int, array{scope_type: string, scope_ref: string, rate_card_id: int, priority: int}>
     */
    public function allActiveAssignments(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('rate_card_assignments')
            ->select(['scope_type', 'scope_ref', 'rate_card_id', 'priority'])
            ->where('deleted_at', 'IS', null)
            ->orderBy('priority', 'ASC')
            ->get();
    }

    /**
     * Get all active rate card lines for batch resolution.
     *
     * @return array<int, array>
     */
    public function allActiveLines(): array
    {
        return (new QueryBuilder($this->pdo))
            ->from('rate_card_lines')
            ->select([
                'id', 'rate_card_id', 'user_id', 'role_code', 'activity_code',
                'cost_rate', 'bill_rate', 'payout_rate', 'currency_code',
                'effective_from',
            ])
            ->where('deleted_at', 'IS', null)
            ->get();
    }

    /**
     * Get the finance.default_currency setting value.
     */
    public function defaultCurrency(): ?string
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('settings')
            ->select(['value'])
            ->where('scope', '=', 'system')
            ->where('name', '=', 'finance.default_currency')
            ->first();

        if (!$row || ($row['value'] ?? '') === '' || $row['value'] === null) {
            return null;
        }

        $decoded = json_decode((string)$row['value'], true);
        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }
        return null;
    }

    /**
     * Get the finance.cost_from_payout_markup_percent setting value.
     */
    public function costFromPayoutMarkupPercent(): ?float
    {
        $row = (new QueryBuilder($this->pdo))
            ->from('settings')
            ->select(['value'])
            ->where('scope', '=', 'system')
            ->where('name', '=', 'finance.cost_from_payout_markup_percent')
            ->first();

        if (!$row || ($row['value'] ?? '') === '' || $row['value'] === null) {
            return null;
        }

        $decoded = json_decode((string)$row['value'], true);
        if (is_numeric($decoded)) {
            return (float)$decoded;
        }
        return null;
    }
}