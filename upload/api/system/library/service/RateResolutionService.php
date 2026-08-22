<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Rate\RateCardRepository;

/**
 * Rate resolution service (TZ section 4).
 *
 * Resolves cost, bill, and payout rates for a worklog entry by walking the
 * priority chain: task override → project card → counterparty card →
 * default card → user global rate → cost-from-payout derivation → none.
 *
 * Generalized across RATE_KINDS — logic is written once for 'cost', 'bill',
 * and 'payout' rather than copy-pasted.
 *
 * Security invariant (TZ 4.6): cost MAY be derived from payout (when the
 * finance.cost_from_payout_markup_percent setting is non-null), but payout
 * IS NEVER derived from cost. This is enforced structurally: deriveFromPayout()
 * only computes cost, and there is no reciprocal code path.
 */
final class RateResolutionService
{
    private const RATE_KINDS = ['payout', 'cost', 'bill'];
    private const FALLBACK_CURRENCY = 'RUB';

    /** @var array<string, array> memoization cache */
    private array $memo = [];

    public function __construct(private readonly RateCardRepository $repo)
    {
    }

    /**
     * Resolve all three rate kinds for a worklog entry.
     *
     * @param int $userId
     * @param int|null $taskId
     * @param string $loggedAtDate  'Y-m-d' in UTC (= DATE(work_logs.logged_at))
     * @param string|null $activityCode  the worklog's activity_code; null → will fall back to tasks.activity_code
     * @param string|null $explicitProjectPublicId  project context when no task is given (TZ 7.3 preview)
     * @param string|null $explicitClientPublicId  client context when no task is given (TZ 7.3 preview)
     * @return array
     */
    public function resolve(
        int $userId,
        ?int $taskId,
        string $loggedAtDate,
        ?string $activityCode,
        ?string $explicitProjectPublicId = null,
        ?string $explicitClientPublicId = null
    ): array {
        $cacheKey = "{$userId}|{$taskId}|{$explicitProjectPublicId}|{$explicitClientPublicId}|{$loggedAtDate}|{$activityCode}";
        if (isset($this->memo[$cacheKey])) {
            return $this->memo[$cacheKey];
        }

        // --- 4.2 Prepare context ---
        $taskCtx = null;
        if ($taskId !== null) {
            $taskCtx = $this->repo->taskContext($taskId);
        }

        // Resolve project and client public IDs: task context wins, then the
        // explicit preview context (TZ 7.3: task OR project+client).
        $projectPublicId = $taskCtx['project_public_id'] ?? $explicitProjectPublicId;
        $taskClientPublicId = $taskCtx['task_client_public_id'] ?? null;
        $projectClientPublicId = $taskCtx['project_client_public_id'] ?? null;
        $clientPublicId = $taskClientPublicId ?: $projectClientPublicId ?: $explicitClientPublicId;

        // DEBUG
        @file_put_contents(__DIR__ . '/rate_debug.log', "userId=$userId taskId=$taskId project=$projectPublicId client=$clientPublicId taskClient=$taskClientPublicId projectClient=$projectClientPublicId activity=$effectiveActivityCode date=$loggedAtDate\n", FILE_APPEND);

        // Activity code: worklog's own → task default → null
        $effectiveActivityCode = $activityCode
            ?? ($taskCtx['activity_code'] ?? null);

        // User role codes
        $roleCodes = $this->repo->userRoleCodes($userId);

        // Task overrides
        $taskOverrides = $taskCtx ? [
            'cost' => $this->nullableFloat($taskCtx['override_cost_rate'] ?? null),
            'bill' => $this->nullableFloat($taskCtx['override_bill_rate'] ?? null),
            'payout' => $this->nullableFloat($taskCtx['override_payout_rate'] ?? null),
        ] : ['cost' => null, 'bill' => null, 'payout' => null];

        // Preload card lines for project/counterparty/default
        $projectCardLines = [];
        $counterpartyCardLines = [];
        $defaultCardLines = [];
        $defaultCardData = null;
        $projectCardData = [];
        $counterpartyCardData = [];

        // Collect rate card IDs from active assignments (task OR explicit scope)
        if ($projectPublicId !== null) {
            $assigns = $this->repo->activeAssignments('project', $projectPublicId, $loggedAtDate);
            @file_put_contents(__DIR__ . '/rate_debug.log', "PROJECT assigns=" . count($assigns) . " ref=$projectPublicId\n", FILE_APPEND);
            if ($assigns !== []) {
                $cardIds = array_map(static fn(array $a): int => (int)$a['rate_card_id'], $assigns);
                $projectCardLines = $this->repo->candidateLines($cardIds, $userId, $effectiveActivityCode, $roleCodes, $loggedAtDate);
                $projectCardData = $this->cardDataForAssignments($cardIds);
            }
        }
        if ($clientPublicId !== null) {
            $assigns = $this->repo->activeAssignments('counterparty', $clientPublicId, $loggedAtDate);
            @file_put_contents(__DIR__ . '/rate_debug.log', "COUNTERPARTY assigns=" . count($assigns) . " ref=$clientPublicId\n", FILE_APPEND);
            if ($assigns !== []) {
                $cardIds = array_map(static fn(array $a): int => (int)$a['rate_card_id'], $assigns);
                $counterpartyCardLines = $this->repo->candidateLines($cardIds, $userId, $effectiveActivityCode, $roleCodes, $loggedAtDate);
                @file_put_contents(__DIR__ . '/rate_debug.log', "COUNTERPARTY lines=" . count($counterpartyCardLines) . " cards=" . implode(',', $cardIds) . "\n", FILE_APPEND);
                $counterpartyCardData = $this->cardDataForAssignments($cardIds);
            }
        }

        // Default card
        $defaultCard = $this->repo->defaultCard();
        if ($defaultCard !== null) {
            $defaultCardId = (int)$defaultCard['id'];
            $defaultCardLines = $this->repo->candidateLines([$defaultCardId], $userId, $effectiveActivityCode, $roleCodes, $loggedAtDate);
            $defaultCardData = $defaultCard;
        }

        // User-level rates
        $userRates = $this->repo->userRates($userId);

        // Finance settings
        $defaultCurrency = $this->repo->defaultCurrency() ?: self::FALLBACK_CURRENCY;
        $markupPercent = $this->repo->costFromPayoutMarkupPercent();

        // --- 4.3–4.6 Resolve each kind independently ---
        $results = [];
        $currency = $defaultCurrency;
        $overallAmbiguous = false;
        $allAmbiguousCandidates = [];
        $trace = [];

        foreach (self::RATE_KINDS as $kind) {
            $result = $this->resolveKind(
                kind: $kind,
                taskOverride: $taskOverrides[$kind],
                projectLines: $projectCardLines,
                projectCardData: $projectCardData,
                counterpartyLines: $counterpartyCardLines,
                counterpartyCardData: $counterpartyCardData,
                defaultLines: $defaultCardLines,
                defaultCardData: $defaultCardData,
                userRates: $userRates,
                markupPercent: $markupPercent,
                resolvedPayout: $results['payout'] ?? null, // needed for cost-from-payout
            );

            $results[$kind] = [
                'rate' => $result['rate'],
                'source_type' => $result['source_type'],
                'source_ref' => $result['source_ref'],
            ];

            if ($result['ambiguous']) {
                $overallAmbiguous = true;
                $allAmbiguousCandidates = array_merge($allAmbiguousCandidates, $result['ambiguous_candidates']);
            }

            // Currency from winning line
            if ($result['currency'] !== null) {
                $currency = $result['currency'];
            }

            $trace[$kind] = $result['trace'];
        }

        // Cross-currency consistency check (TZ 4.7)
        $currencies = [];
        foreach ($trace as $kind => $t) {
            foreach ($t as $step) {
                if (($step['currency'] ?? null) !== null) {
                    $currencies[] = $step['currency'];
                }
            }
        }
        $currencies = array_unique($currencies);
        if (count($currencies) > 1) {
            $overallAmbiguous = true;
            error_log('[RateResolution] Currency mismatch: ' . implode(', ', $currencies));
        }

        $out = [
            'cost' => $results['cost'],
            'bill' => $results['bill'],
            'payout' => $results['payout'],
            'currency_code' => $currency,
            'client_public_id' => $clientPublicId,
            'project_public_id' => $projectPublicId,
            'activity_code' => $effectiveActivityCode,
            'ambiguous' => $overallAmbiguous,
            'ambiguous_candidates' => array_values(array_unique($allAmbiguousCandidates)),
            'trace' => $trace,
        ];

        $this->memo[$cacheKey] = $out;
        return $out;
    }

    /**
     * Resolve a single rate kind through the priority chain.
     *
     * @return array{rate: ?float, source_type: string, source_ref: ?string, currency: ?string, ambiguous: bool, ambiguous_candidates: string[], trace: array}
     */
    private function resolveKind(
        string $kind,
        ?float $taskOverride,
        array $projectLines,
        array $projectCardData,
        array $counterpartyLines,
        array $counterpartyCardData,
        array $defaultLines,
        ?array $defaultCardData,
        ?array $userRates,
        ?float $markupPercent,
        ?array $resolvedPayout,
    ): array {
        $rateField = "{$kind}_rate"; // cost_rate, bill_rate, payout_rate
        $trace = [];

        // 1. Task override
        if ($taskOverride !== null) {
            return $this->kindResult(
                kind: $kind,
                rate: $taskOverride,
                sourceType: 'task_override',
                sourceRef: null,
                currency: null,
                ambiguous: false,
                trace: [['source' => 'task_override', 'rate' => $taskOverride]]
            );
        }

        // 2. Project card
        if ($projectLines !== []) {
            $winner = $this->pickBestLine($projectLines, $kind, 'project_card', $projectCardData);
            if ($winner !== null) {
                return $winner;
            }
        }

        // 3. Counterparty card
        if ($counterpartyLines !== []) {
            $winner = $this->pickBestLine($counterpartyLines, $kind, 'counterparty_card', $counterpartyCardData);
            if ($winner !== null) {
                return $winner;
            }
        }

        // 4. Default card
        if ($defaultLines !== []) {
            $defaultCData = $defaultCardData ? ["c" . (string)($defaultCardData['id'] ?? 0) => $defaultCardData] : [];
            $winner = $this->pickBestLine($defaultLines, $kind, 'default_card', $defaultCData);
            if ($winner !== null) {
                return $winner;
            }
        }

        // 5. User default
        if ($userRates !== null && isset($userRates[$rateField]) && $userRates[$rateField] !== null) {
            return $this->kindResult(
                kind: $kind,
                rate: (float)$userRates[$rateField],
                sourceType: 'user_default',
                sourceRef: null,
                currency: null,
                ambiguous: false,
                trace: [['source' => 'user_default', 'rate' => (float)$userRates[$rateField]]]
            );
        }

        // 6. Cost-from-payout derivation (only for cost kind)
        if ($kind === 'cost' && $markupPercent !== null && $resolvedPayout !== null && ($resolvedPayout['rate'] ?? null) !== null) {
            $derivedRate = round((float)$resolvedPayout['rate'] * (1 + $markupPercent / 100), 2);
            return $this->kindResult(
                kind: $kind,
                rate: $derivedRate,
                sourceType: 'derived_from_payout',
                sourceRef: null,
                currency: null,
                ambiguous: false,
                trace: [['source' => 'derived_from_payout', 'rate' => $derivedRate, 'markup_percent' => $markupPercent]]
            );
        }

        // 7. None
        return $this->kindResult(
            kind: $kind,
            rate: null,
            sourceType: 'none',
            sourceRef: null,
            currency: null,
            ambiguous: false,
            trace: [['source' => 'none', 'rate' => null]]
        );
    }

    /**
     * Pick the best line for a given rate kind from candidate lines.
     *
     * Returns null if no line has a non-null rate for this kind.
     *
     * @param array<int, array> $lines  Candidate rate_card_lines rows
     * @param string $kind  'cost', 'bill', or 'payout'
     * @param string $sourceType  'project_card', 'counterparty_card', or 'default_card'
     * @param array $cardData  card_id → {public_id, currency_code, ...} map
     * @return array|null
     */
    private function pickBestLine(
        array $lines,
        string $kind,
        string $sourceType,
        array $cardData
    ): ?array {
        $rateField = "{$kind}_rate";

        // Filter to lines that have a non-null rate for this kind
        $candidates = array_filter(
            $lines,
            static fn(array $l): bool => ($l[$rateField] ?? null) !== null
        );

        if ($candidates === []) {
            return null;
        }

        // Score by specificity (TZ 4.4): user_id=4, activity_code=2, role_code=1
        $scored = [];
        foreach ($candidates as $line) {
            $specificity = 0;
            if (($line['user_id'] ?? null) !== null) $specificity += 4;
            if (($line['activity_code'] ?? null) !== null) $specificity += 2;
            if (($line['role_code'] ?? null) !== null) $specificity += 1;
            $scored[] = [
                'line' => $line,
                'specificity' => $specificity,
                'effective_from' => (string)($line['effective_from'] ?? ''),
            ];
        }

        // Tiebreak: specificity DESC → effective_from DESC → line.id ASC
        usort($scored, static function (array $a, array $b): int {
            if ($a['specificity'] !== $b['specificity']) {
                return $b['specificity'] <=> $a['specificity'];
            }
            if ($a['effective_from'] !== $b['effective_from']) {
                return $b['effective_from'] <=> $a['effective_from'];
            }
            return ((int)($a['line']['id'] ?? 0)) <=> ((int)($b['line']['id'] ?? 0));
        });

        $winner = $scored[0];
        $rate = (float)$winner['line'][$rateField];
        $linePublicId = (string)($winner['line']['public_id'] ?? '');
        $cardId = (int)($winner['line']['rate_card_id'] ?? 0);
        $currency = $winner['line']['currency_code']
            ?? ($cardData["c{$cardId}"]['currency_code'] ?? null);

        // Card public_id as source_ref
        $cardPublicId = $cardData["c{$cardId}"]['public_id'] ?? null;

        // Ambiguity check (TZ 4.5)
        $ambiguous = false;
        $ambiguousCandidates = [];
        if (count($scored) > 1) {
            $second = $scored[1];
            if ($second['specificity'] === $winner['specificity']
                && $second['effective_from'] === $winner['effective_from']) {
                $ambiguous = true;
                $ambiguousCandidates = array_map(
                    static fn(array $s): string => (string)($s['line']['public_id'] ?? ''),
                    $scored
                );
                error_log('[RateResolution] Ambiguous: ' . json_encode([
                    'kind' => $kind,
                    'winner' => $linePublicId,
                    'candidates' => $ambiguousCandidates,
                ]));
            }
        }

        return $this->kindResult(
            kind: $kind,
            rate: $rate,
            sourceType: $sourceType,
            sourceRef: $cardPublicId,
            currency: $currency,
            ambiguous: $ambiguous,
            trace: [[
                'source' => $sourceType,
                'rate' => $rate,
                'line_public_id' => $linePublicId,
                'card_public_id' => $cardPublicId,
                'specificity' => $winner['specificity'],
                'currency' => $currency,
            ]],
            ambiguousCandidates: $ambiguousCandidates,
        );
    }

    /**
     * Build the standardized result array for a rate kind.
     */
    private function kindResult(
        string $kind,
        ?float $rate,
        string $sourceType,
        ?string $sourceRef,
        ?string $currency,
        bool $ambiguous,
        array $trace,
        array $ambiguousCandidates = []
    ): array {
        return [
            'rate' => $rate,
            'source_type' => $sourceType,
            'source_ref' => $sourceRef,
            'currency' => $currency,
            'ambiguous' => $ambiguous,
            'ambiguous_candidates' => $ambiguousCandidates,
            'trace' => $trace,
        ];
    }

    /**
     * @param mixed $val
     */
    private function nullableFloat(mixed $val): ?float
    {
        if ($val === null || $val === '' || $val === false) {
            return null;
        }
        return (float)$val;
    }

    /**
     * Build a "c{id}" → card-data map for the given card IDs.
     *
     * The "c{id}" prefix is used by pickBestLine to look up card data
     * from the line's rate_card_id.
     *
     * @param int[] $cardIds
     * @return array<string, array>
     */
    private function cardDataForAssignments(array $cardIds): array
    {
        $map = [];
        foreach ($cardIds as $id) {
            $card = $this->repo->findCardById($id);
            if ($card !== null) {
                $map["c{$id}"] = $card;
            }
        }
        return $map;
    }
}