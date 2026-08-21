<?php
declare(strict_types=1);

namespace Api\Controller\Rate;

use Api\Controller\Common\BaseController;
use Api\System\Library\Security\FinancialFieldPolicy;

/**
 * Self-scoped "My Earnings" endpoint (TZ 7.1-7.2).
 * The ONLY new route accessible to external executors.
 * Always scoped to the authenticated actor — no user_public_id parameter accepted.
 */
final class MyEarningsController extends BaseController
{
    /**
     * GET /api/v1/me/earnings — actor's own payout data.
     */
    public function earnings(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        // TZ 7.1 / 8.1: internal users need view_own_payout (or view_cost, which
        // implies own payout per FinancialFieldPolicy); external executors are
        // admitted by the route gate and scoped strictly to their own rows.
        if (!$this->canViewOwnPayout($auth['user'])) {
            return $this->error('FORBIDDEN', $this->t('common/messages.forbidden'), 403);
        }

        $input = $this->request()->allInput();
        $from = (string)($input['date_from'] ?? gmdate('Y-m-01'));
        $to   = (string)($input['date_to']   ?? gmdate('Y-m-t'));

        /** @var \Api\System\Library\Service\EarningsService $svc */
        $svc = $this->container->get('service.earnings');
        $result = $svc->myEarnings($auth['user'], $from, $to);

        $policy = new FinancialFieldPolicy();
        $result['items'] = $policy->filterRows($result['items'], $auth['user'], 'me.earnings');

        return $this->success('ME_EARNINGS', $this->t('earnings/messages.my_earnings'), $result);
    }

    /**
     * GET /api/v1/me/earnings/available — boolean indicator (TZ 7.2).
     */
    public function available(): \Api\System\Library\Http\JsonResponse
    {
        $auth = $this->user();
        if (!$auth) {
            return $this->error('UNAUTHORIZED', $this->t('common/messages.unauthorized'), 401);
        }

        /** @var \Api\System\Library\Service\EarningsService $svc */
        $svc = $this->container->get('service.earnings');
        $available = $svc->hasPayoutData($auth['user']);

        return $this->success('ME_EARNINGS_AVAILABLE', '', ['available' => $available]);
    }

    /**
     * Whether the actor may see their own payout data (TZ 7.1 / 8.1).
     */
    private function canViewOwnPayout(array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }
        $codes = (array)($actor['permission_codes'] ?? []);
        return in_array('*', $codes, true)
            || in_array('finance.rate.view_own_payout', $codes, true)
            || in_array('finance.rate.view_cost', $codes, true);
    }
}