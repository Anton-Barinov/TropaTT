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
        $q = (new QueryBuilder($this->container->get('db.pdo')))
            ->from('rate_cards')
            ->select(['public_id', 'title', 'description', 'currency_code', 'is_default', 'is_archived', 'created_at', 'updated_at'])
            ->where('deleted_at', 'IS', null)
            ->orderBy('is_default', 'DESC')
            ->orderBy('title', 'ASC');
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
        return $this->success('RATE_CARD_UPDATED');
    }

    public function archive(array $params): \Api\System\Library\Http\JsonResponse
    {
        $pid = (string)$params['public_id'];
        $pdo = $this->container->get('db.pdo');
        $card = (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->where('deleted_at', 'IS', null)->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $assignments = (new QueryBuilder($pdo))->from('rate_card_assignments')
            ->where('rate_card_id', '=', (int)$card['id'])->where('deleted_at', 'IS', null)->count();
        if ($assignments > 0) {
            return $this->error('HAS_ASSIGNMENTS', $this->t('rate/messages.has_assignments'), 409);
        }

        (new QueryBuilder($pdo))->from('rate_cards')->where('public_id', '=', $pid)->update([
            'is_archived' => 1, 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->invalidateCache('worklog');
        return $this->success('RATE_CARD_ARCHIVED');
    }

    // ── Rate Card Lines ──

    public function listLines(array $params): \Api\System\Library\Http\JsonResponse
    {
        $card = (new QueryBuilder($this->container->get('db.pdo')))->from('rate_cards')
            ->where('public_id', '=', (string)$params['public_id'])->where('deleted_at', 'IS', null)->first();
        if (!$card) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $lines = (new QueryBuilder($this->container->get('db.pdo')))->from('rate_card_lines l')
            ->leftJoin('users u', 'u.id', '=', 'l.user_id')
            ->select(['l.public_id', 'l.user_id', 'u.login', 'u.full_name', 'l.role_code',
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
        $cost = $input['cost_rate'] ?? null;
        $bill = $input['bill_rate'] ?? null;
        $payout = $input['payout_rate'] ?? null;

        $errKey = $this->lineValidationError($input);
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
        $pdo = $this->container->get('db.pdo');
        $line = (new QueryBuilder($pdo))->from('rate_card_lines')->where('public_id', '=', $pid)->first();
        if (!$line) return $this->error('NOT_FOUND', $this->t('common/messages.not_found'), 404);

        $errKey = $this->lineValidationError(array_merge($line, $input));
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
        return $this->success('LINE_UPDATED');
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
        return $this->success('LINE_DELETED');
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
        return $this->success('ASSIGNMENT_DELETED');
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
     * Validate a rate-card line's final state (TZ 3.3).
     * Returns an i18n key (rate/messages.*) on failure, or null when valid.
     */
    private function lineValidationError(array $merged): ?string
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

        return null;
    }
}