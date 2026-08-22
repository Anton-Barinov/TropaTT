<?php
declare(strict_types=1);

namespace Api\Controller\Rate;

use Api\Controller\Common\BaseController;
use Api\System\Library\Database\Builder\QueryBuilder;
use Api\System\Library\Security\FinancialFieldPolicy;
use Api\System\Library\Support\Ulid;

/**
 * CRUD for rate cards, lines, and assignments (TZ 7).
 * Requires finance.ratecard.manage.
 */
final class RateCardController extends BaseController
{
    // ── Rate Cards ──

    public function list(): \Api\System\Library\Http\JsonResponse
    {
        $pdo = $this->container->get('db.pdo');
        $q = (new QueryBuilder($pdo))
            ->from('rate_cards c')
            ->select([
                'c.public_id', 'c.title', 'c.description', 'c.currency_code',
                'c.is_default', 'c.is_archived', 'c.created_at', 'c.updated_at',
                '(SELECT COUNT(*) FROM rate_card_lines l WHERE l.rate_card_id = c.id AND l.deleted_at IS NULL) AS line_count',
                '(SELECT COUNT(*) FROM rate_card_assignments a WHERE a.rate_card_id = c.id AND a.deleted_at IS NULL) AS assignment_count',
            ])
            ->where('c.deleted_at', 'IS', null)
            ->orderBy('c.is_default', 'DESC')
            ->orderBy('c.title', 'ASC');
        return $this->success('RATE_CARD_LIST', '', ['items' => $q->get()]);
    }

    public function get(array $params): \Api\System\Library\Http\JsonResponse
    {
        $card = (new QueryBuilder($this->container->get('db.pdo')))
            ->from('rate_cards')
            ->where('public_id', '=', (string)$params['public_id'])
            ->where('deleted_at', 'IS', null)
            ->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        return $this->success('RATE_CARD', '', ['card' => $card]);
    }

    public function create(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') return $this->error('VALIDATION', $this->t('common/messages.validation_error'), 422);

        $pdo = $this->container->get('db.pdo');
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('rcd');

        $pdo->beginTransaction();
        try {
            if (!empty($input['is_default'])) {
                $this->clearDefaultCard($pdo);
            }
            (new QueryBuilder($pdo))->from('rate_cards')->insert([
                'public_id' => $publicId,
                'title' => $title,
                'description' => $input['description'] ?? null,
                'currency_code' => $input['currency_code'] ?? null,
                'is_default' => (int)(!empty($input['is_default'])),
                'created_by_user_id' => $this->actorId(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return $this->error('CREATE_FAILED', $e->getMessage(), 500);
        }

        $this->invalidateCache('worklog');
        return $this->success('RATE_CARD_CREATED', '', ['public_id' => $publicId], 201);
    }

    public function update(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pid = (string)$params['public_id'];
        $input = $this->request()->allInput();
        $pdo = $this->container->get('db.pdo');

        $card = (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->where('deleted_at', 'IS', null)->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        if (array_key_exists('title', $input)) $set['title'] = trim((string)$input['title']);
        if (array_key_exists('description', $input)) $set['description'] = $input['description'];
        if (array_key_exists('currency_code', $input)) $set['currency_code'] = $input['currency_code'];
        if (array_key_exists('is_default', $input)) {
            $pdo->beginTransaction();
            if (!empty($input['is_default'])) $this->clearDefaultCard($pdo);
            $set['is_default'] = (int)(!empty($input['is_default']));
            (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->update($set);
            $pdo->commit();
        } else {
            (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->update($set);
        }

        $this->invalidateCache('worklog');
        return $this->success('RATE_CARD_UPDATED', '');
    }

    public function archive(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pid = (string)$params['public_id'];
        $pdo = $this->container->get('db.pdo');
        $card = (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->where('deleted_at', 'IS', null)->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $now = gmdate('Y-m-d H:i:s');

        // Cascade soft-delete active assignments before archiving
        (new QueryBuilder($pdo))->from('rate_card_assignments')
            ->where('rate_card_id', '=', (int)$card['id'])->where('deleted_at', 'IS', null)
            ->update(['deleted_at' => $now, 'updated_at' => $now]);

        (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->update([
            'is_archived' => 1, 'updated_at' => $now,
        ]);
        $this->invalidateCache('worklog');
        return $this->success('RATE_CARD_ARCHIVED', '');
    }

    // ── Rate Card Lines ──

    public function listLines(array $params): \Api\System\Library\Http\JsonResponse
    {
        $card = (new QueryBuilder($this->container->get('db.pdo')))->from('rate_cards')
            ->where('public_id', '=', (string)$params['public_id'])->where('deleted_at', 'IS', null)->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $lines = (new QueryBuilder($this->container->get('db.pdo')))->from('rate_card_lines l')
            ->leftJoin('users u', 'u.id', '=', 'l.user_id')
            ->select(['l.public_id', 'l.user_id', 'u.public_id AS user_public_id', 'u.login', 'u.full_name', 'l.role_code',
                'l.activity_code', 'l.cost_rate', 'l.bill_rate', 'l.payout_rate',
                'l.currency_code', 'l.effective_from', 'l.effective_to', 'l.note'])
            ->where('l.rate_card_id', '=', (int)$card['id'])
            ->where('l.deleted_at', 'IS', null)
            ->orderBy('l.effective_from', 'DESC')
            ->get();
        return $this->success('LINES', '', ['items' => $lines]);
    }

    public function createLine(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pdo = $this->container->get('db.pdo');
        $card = (new QueryBuilder($pdo))->from('rate_cards')
            ->where('public_id', '=', (string)$params['public_id'])->where('deleted_at', 'IS', null)->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $input = $this->request()->allInput();
        $input = $this->normalizeLineUserRef($input);
        $cost = $input['cost_rate'] ?? null;
        $bill = $input['bill_rate'] ?? null;
        $payout = $input['payout_rate'] ?? null;

        $errKey = $this->lineValidationError($input, (int)$card['id']);
        if ($errKey !== null) {
            return $this->error('VALIDATION', $this->t('rate/messages.' . $errKey), 422);
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('rcl');
        (new QueryBuilder($pdo))->from('rate_card_lines')->insert([
            'public_id' => $publicId,
            'rate_card_id' => (int)$card['id'],
            'user_id' => $input['user_id'] ?? null,
            'role_code' => $input['role_code'] ?? null,
            'activity_code' => $input['activity_code'] ?? null,
            'cost_rate' => $cost !== null ? (float)$cost : null,
            'bill_rate' => $bill !== null ? (float)$bill : null,
            'payout_rate' => $payout !== null ? (float)$payout : null,
            'currency_code' => $input['currency_code'] ?? null,
            'effective_from' => $input['effective_from'] ?? gmdate('Y-m-d'),
            'effective_to' => $input['effective_to'] ?? null,
            'note' => $input['note'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->invalidateCache('worklog');
        return $this->success('LINE_CREATED', '', ['public_id' => $publicId], 201);
    }

    public function updateLine(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pid = (string)$params['public_id'];
        $input = $this->request()->allInput();
        $input = $this->normalizeLineUserRef($input);
        $pdo = $this->container->get('db.pdo');
        $line = (new QueryBuilder($pdo))->from('rate_card_lines')->where('public_id', '=', $pid)->first();
        if (!$line) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $errKey = $this->lineValidationError(array_merge($line, $input), (int)$line['rate_card_id'], $pid);
        if ($errKey !== null) {
            return $this->error('VALIDATION', $this->t('rate/messages.' . $errKey), 422);
        }

        $set = ['updated_at' => gmdate('Y-m-d H:i:s')];
        foreach (['user_id','role_code','activity_code','cost_rate','bill_rate','payout_rate',
            'currency_code','effective_from','effective_to','note'] as $f) {
            if (array_key_exists($f, $input)) $set[$f] = $input[$f];
        }
        (new QueryBuilder($pdo))->from('rate_card_lines')->where('public_id', '=', $pid)->update($set);
        $this->invalidateCache('worklog');
        return $this->success('LINE_UPDATED', '');
    }

    public function deleteLine(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pid = (string)$params['public_id'];
        $pdo = $this->container->get('db.pdo');
        (new QueryBuilder($pdo))->from('rate_card_lines')->where('public_id', '=', $pid)->update([
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->invalidateCache('worklog');
        return $this->success('LINE_DELETED', '');
    }

    // ── Assignments ──

    public function listAssignments(): \Api\System\Library\Http\JsonResponse
    {
        $items = (new QueryBuilder($this->container->get('db.pdo')))->from('rate_card_assignments a')
            ->leftJoin('rate_cards c', 'c.id', '=', 'a.rate_card_id')
            ->select(['a.public_id', 'c.public_id AS card_public_id', 'c.title AS card_title',
                'a.scope_type', 'a.scope_ref', 'a.priority', 'a.effective_from', 'a.effective_to'])
            ->where('a.deleted_at', 'IS', null)
            ->orderBy('c.title', 'ASC')
            ->get();
        return $this->success('ASSIGNMENTS', '', ['items' => $items]);
    }

    public function createAssignment(): \Api\System\Library\Http\JsonResponse
    {
        $input = $this->request()->allInput();
        $pdo = $this->container->get('db.pdo');
        $card = (new QueryBuilder($pdo))->from('rate_cards')
            ->where('public_id', '=', (string)($input['rate_card_public_id'] ?? ''))->first();
        if (!$card) return $this->error('CARD_NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $scope = (string)($input['scope_type'] ?? '');
        if (!in_array($scope, ['counterparty', 'project'], true)) {
            return $this->error('VALIDATION', $this->t('rate/messages.invalid_scope'), 422);
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('rca');
        (new QueryBuilder($pdo))->from('rate_card_assignments')->insert([
            'public_id' => $publicId,
            'rate_card_id' => (int)$card['id'],
            'scope_type' => $scope,
            'scope_ref' => (string)($input['scope_ref'] ?? ''),
            'priority' => (int)($input['priority'] ?? 100),
            'effective_from' => $input['effective_from'] ?? gmdate('Y-m-d'),
            'effective_to' => $input['effective_to'] ?? null,
            'created_by_user_id' => $this->actorId(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->invalidateCache('worklog');
        return $this->success('ASSIGNMENT_CREATED', '', ['public_id' => $publicId], 201);
    }

    public function deleteAssignment(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pid = (string)$params['public_id'];
        $pdo = $this->container->get('db.pdo');
        (new QueryBuilder($pdo))->from('rate_card_assignments')->where('public_id', '=', $pid)->update([
            'deleted_at' => gmdate('Y-m-d H:i:s'),
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->invalidateCache('worklog');
        return $this->success('ASSIGNMENT_DELETED', '');
    }

    // ── Helpers ──

    private function clearDefaultCard(\PDO $pdo): void
    {
        (new QueryBuilder($pdo))->from('rate_cards')->where('is_default', '=', 1)->update(['is_default' => 0]);
    }

    private function actorId(): ?int
    {
        $u = $this->user();
        return $u ? ((int)($u['user']['id'] ?? 0) ?: null) : null;
    }

    /**
     * The UI only knows users by public_id, while rate_card_lines.user_id is the
     * internal integer id (TZ 3.3). Resolve user_public_id → user_id so the
     * frontend never has to expose or guess internal ids. An explicit user_id is
     * kept for API backwards compatibility.
     */
    private function normalizeLineUserRef(array $input): array
    {
        if (array_key_exists('user_public_id', $input)) {
            $pubId = trim((string)$input['user_public_id']);
            unset($input['user_public_id']);
            if ($pubId === '') {
                $input['user_id'] = null;
            } else {
                $u = (new QueryBuilder($this->container->get('db.pdo')))
                    ->from('users')
                    ->select(['id'])
                    ->where('public_id', '=', $pubId)
                    ->first();
                $input['user_id'] = $u ? (int)$u['id'] : null;
            }
        }
        return $input;
    }

    /**
     * Validate a rate-card line's final state (TZ 3.3).
     * Returns an i18n key (rate/messages.*) on failure, or null when valid.
     * @param int $cardId rate_cards.id for duplicate check
     * @param string|null $excludeLineId skip this line (for updates)
     */
    private function lineValidationError(array $merged, int $cardId = 0, ?string $excludeLineId = null): ?string
    {
        // At least one of the three rates must be set
        $cost = $merged['cost_rate'] ?? null;
        $bill = $merged['bill_rate'] ?? null;
        $payout = $merged['payout_rate'] ?? null;
        if ($cost === null && $bill === null && $payout === null) {
            return 'at_least_one_rate';
        }

        // Rates must be non-negative
        foreach (['cost_rate', 'bill_rate', 'payout_rate'] as $f) {
            if (array_key_exists($f, $merged) && $merged[$f] !== null && $merged[$f] !== '' && (float)$merged[$f] < 0) {
                return 'negative_rate';
            }
        }

        // role_code must reference an existing role
        if (!empty($merged['role_code'])) {
            /** @var \Api\Model\Role\RoleRepository $roles */
            $roles = $this->container->get('repository.role');
            if ($roles->findByCode((string)$merged['role_code']) === null) {
                return 'invalid_role';
            }
        }

        // activity_code must exist in the work-type dictionary (Phase 6)
        if (!empty($merged['activity_code'])) {
            /** @var \Api\System\Library\Service\ActivityCodeService $activities */
            $activities = $this->container->get('service.activity_code');
            if (!$activities->exists((string)$merged['activity_code'])) {
                return 'invalid_activity_code';
            }
        }

        // effective_to must not precede effective_from (default = today)
        $from = !empty($merged['effective_from']) ? (string)$merged['effective_from'] : gmdate('Y-m-d');
        $to = $merged['effective_to'] ?? null;
        if ($to !== null && $to !== '' && strtotime((string)$to) < strtotime($from)) {
            return 'invalid_date_range';
        }

        // Duplicate check: same user + role + activity in the same card
        if ($cardId > 0) {
            $pdo = $this->container->get('db.pdo');
            $userId = $merged['user_id'] ?? null;
            $roleCode = $merged['role_code'] ?? null;
            $activityCode = $merged['activity_code'] ?? null;
            $qb = (new QueryBuilder($pdo))->from('rate_card_lines')
                ->where('rate_card_id', '=', $cardId)
                ->where('deleted_at', 'IS', null);
            if ($userId === null || $userId === '' || $userId === 0) {
                $qb->where('user_id', 'IS', null);
            } else {
                $qb->where('user_id', '=', (int)$userId);
            }
            if (empty($roleCode)) {
                $qb->where('role_code', 'IS', null);
            } else {
                $qb->where('role_code', '=', (string)$roleCode);
            }
            if (empty($activityCode)) {
                $qb->where('activity_code', 'IS', null);
            } else {
                $qb->where('activity_code', '=', (string)$activityCode);
            }
            if ($excludeLineId !== null) {
                $qb->where('public_id', '!=', $excludeLineId);
            }
            if ($qb->first()) {
                return 'duplicate_line';
            }
        }

        return null;
    }
}