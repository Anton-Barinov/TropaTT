<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

/**
 * Unified financial field filtering policy.
 *
 * Every output path that carries financial data (REST, MCP, export, webhooks,
 * AI context) MUST pass through this policy. Direct disclosure of a financial
 * field from a repository to a response, bypassing this policy, is forbidden.
 *
 * Disclosure model (TZ 6.2):
 *
 *   Field families (by rate kind):
 *     cost    — cost_rate_snapshot / cost_rate, cost_amount, cost_source_*
 *     bill    — bill_rate_snapshot / bill_rate, bill_amount, bill_source_*,
 *               margin, margin_amount
 *     payout  — payout_rate_snapshot / payout_rate, payout_amount, payout_source_*
 *     config  — rate card / line / assignment identifiers
 *
 *   A family is disclosed to a given row only when BOTH hold:
 *     (a) the actor carries the required permission, and
 *     (b) ownership / external-role rules permit it.
 *
 *   Anti-inference rule: rate and its computed amount live in the SAME family
 *   and are stripped together — otherwise the rate is recoverable from
 *   amount ÷ hours.
 *
 * External users (TZ 6.5):
 *   - observer  → NO financial fields at all.
 *   - executor  → only the `payout` family for their OWN rows. `cost` is NEVER
 *     given to an external user (exposes internal markup), even if the
 *     permission was granted. `bill`, `config` and others' data are never
 *     given to any external user.
 *
 * Cache safety (TZ 6.9, decision recorded in AGENT_HISTORY.md):
 *   The policy filters POST-cache in the controller layer. The cache key
 *   includes cacheUserId(), so different actors with different financial
 *   permissions always get different cache entries. The unfiltered result
 *   is never returned before the policy strips it. This approach keeps cache
 *   hit rates high (same actor → same key) while never leaking fields.
 *
 * Scoping note: this policy assumes the CALLER has already scoped rows to the
 * actor's visibility (WorklogService::getVisibleUserIds()). It does not itself
 * perform RLS — it only strips fields.
 */
final class FinancialFieldPolicy
{
    /**
     * Field → disclosure family map.
     *
     * Every financial field added to any API response MUST be listed here.
     * Adding a field without adding it to this map is a direct data leak.
     *
     * @var array<string, string>
     */
    private const FIELD_FAMILY = [
        // payout family
        'payout_rate_snapshot'   => 'payout',
        'payout_rate'            => 'payout',
        'payout_amount'          => 'payout',
        'payout_source_type'     => 'payout',
        'payout_source_ref'      => 'payout',
        'payout'                 => 'payout',

        // cost family
        'cost_rate_snapshot'     => 'cost',
        'cost_rate'              => 'cost',
        'cost_amount'            => 'cost',
        'cost_source_type'       => 'cost',
        'cost_source_ref'        => 'cost',
        'cost'                   => 'cost',

        // bill (commercial) family
        'bill_rate_snapshot'     => 'bill',
        'bill_rate'              => 'bill',
        'bill_amount'            => 'bill',
        'bill_source_type'       => 'bill',
        'bill_source_ref'        => 'bill',
        'bill'                   => 'bill',
        'margin'                 => 'bill',
        'margin_amount'          => 'bill',

        // config family
        'rate_card_id'           => 'config',
        'rate_card_line_id'      => 'config',
        'rate_card_public_id'    => 'config',
        'rate_card_assignment_id' => 'config',
    ];

    /**
     * Filter an array of rows, stripping financial fields the actor must not see.
     *
     * @param array<int, array<string, mixed>> $rows
     * @param array<string, mixed> $actor  Keys: public_id, is_root, is_external, external_role, permissions[]
     * @param string $context  Identifies the caller (e.g. 'worklog.earnings', 'me.earnings', 'mcp.crmWorklogEarnings')
     * @return array<int, array<string, mixed>>
     */
    public function filterRows(array $rows, array $actor, string $context): array
    {
        return array_map(
            fn(array $row): array => $this->filterRow($row, $actor, $context),
            $rows
        );
    }

    /**
     * Filter a single row.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $actor
     * @param string $context
     * @return array<string, mixed>
     */
    public function filterRow(array $row, array $actor, string $context): array
    {
        $allowed = $this->allowedFamilies($row, $actor);

        foreach (self::FIELD_FAMILY as $field => $family) {
            if (!in_array($family, $allowed, true)) {
                unset($row[$field]);
            }
        }

        return $row;
    }

    /**
     * Determine which field families are disclosed for the given row + actor.
     *
     * @param array<string, mixed> $row
     * @param array<string, mixed> $actor
     * @return string[]
     */
    private function allowedFamilies(array $row, array $actor): array
    {
        $isRoot = (bool)($actor['is_root'] ?? false);
        if ($isRoot) {
            return ['payout', 'cost', 'bill', 'config'];
        }

        $isExternal = (bool)($actor['is_external'] ?? false);
        $externalRole = (string)($actor['external_role'] ?? '');
        $actorPublicId = (string)($actor['public_id'] ?? '');
        $rowUserPublicId = (string)($row['user_public_id'] ?? '');
        $isOwn = ($rowUserPublicId !== '' && $rowUserPublicId === $actorPublicId);

        // --- External users: unconditional blocks (TZ 6.5) ---

        if ($isExternal) {
            // observer: NO financial fields at all
            if ($externalRole === 'observer') {
                return [];
            }

            // executor: only own payout; cost/bill/config/others never
            if ($externalRole === 'executor') {
                if (!$isOwn) {
                    return [];
                }
                return $this->hasPerm($actor, 'finance.rate.view_own_payout')
                    ? ['payout']
                    : [];
            }

            // unknown external role: fail-closed
            return [];
        }

        // --- Internal users: permission + ownership gating (TZ 6.2) ---

        $allowed = [];

        // cost family: own rows need view_own_cost; others' rows need view_cost
        if ($isOwn) {
            if ($this->hasPerm($actor, 'finance.rate.view_own_cost')
                || $this->hasPerm($actor, 'finance.rate.view_cost')) {
                $allowed[] = 'cost';
            }
        } else {
            if ($this->hasPerm($actor, 'finance.rate.view_cost')) {
                $allowed[] = 'cost';
            }
        }

        // payout family: own rows need view_own_payout; others' rows need view_cost
        if ($isOwn) {
            if ($this->hasPerm($actor, 'finance.rate.view_own_payout')
                || $this->hasPerm($actor, 'finance.rate.view_cost')) {
                $allowed[] = 'payout';
            }
        } else {
            if ($this->hasPerm($actor, 'finance.rate.view_cost')) {
                $allowed[] = 'payout';
            }
        }

        // bill (commercial) family: always view_bill
        if ($this->hasPerm($actor, 'finance.rate.view_bill')) {
            $allowed[] = 'bill';
        }

        // config family: ratecard.manage
        if ($this->hasPerm($actor, 'finance.ratecard.manage')) {
            $allowed[] = 'config';
        }

        return array_values(array_unique($allowed));
    }

    /**
     * Check whether the actor has a specific permission.
     *
     * @param array<string, mixed> $actor
     * @param string $code
     * @return bool
     */
    private function hasPerm(array $actor, string $code): bool
    {
        $codes = (array)($actor['permission_codes'] ?? []);
        // Root has ['*'] — matches everything
        return in_array('*', $codes, true) || in_array($code, $codes, true);
    }
}