<?php

declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }

/**
 * finance_fields_security_check
 *
 * Guards the financial-field disclosure model (TZ section 6). Every assertion
 * here encodes a rule from TZ 6.2–6.8; if one regresses it silently leaks
 * internal cost/margin data or reveals an executor's own cost (which exposes
 * the company's markup).
 *
 * Runs standalone in CI (no database, no network):
 *   php upload/api/scripts/finance_fields_security_check.php
 *
 * Mutation testing (Definition of Done #5):
 *   php upload/api/scripts/finance_fields_security_check.php --mutation-test
 * Each invariant is paired with a mutation of the guarded source; the check
 * must flip to FAIL when its mutation is applied (i.e. no vacuous assertions).
 *
 * This is a paired script to external_users_security_check.php (TZ 6.8 allows
 * "Расширить external_users_security_check.php (или добавить парный
 * finance_fields_security_check.php)"). It guards the orthogonal finance-field
 * disclosure surface, which is not about the client portal per se.
 */

$root = dirname(__DIR__, 2);
$apiRoot = $root . '/api';
$webRoot = $root . '/web';

$policyPath = $apiRoot . '/system/library/security/FinancialFieldPolicy.php';
$routesPath = $apiRoot . '/config/routes.php';
$worklogControllerPath = $apiRoot . '/controller/worklog/WorklogController.php';
$mcpControllerPath = $apiRoot . '/controller/mcp/McpController.php';
$rateResolutionPath = $apiRoot . '/system/library/service/RateResolutionService.php';
$financeMigrationPath = $apiRoot . '/system/library/database/migration/FinancePermissionsMigration.php';
$webIndexPath = $webRoot . '/index.php';

function failSmoke(string $message): void
{
    fwrite(STDERR, "[FAIL] finance_fields_security_check: {$message}\n");
    exit(1);
}

function readFileSafe(string $path): string
{
    if (!is_file($path)) {
        failSmoke("file not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        failSmoke("unable to read file: {$path}");
    }

    return $content;
}

/** @var array<int,array<string,mixed>> $routes */
$routes = require $routesPath;
if (!is_array($routes) || $routes === []) {
    failSmoke('api/config/routes.php must return a non-empty array');
}

/** @var array<string, mixed> $src */
$src = [
    'policy'             => readFileSafe($policyPath),
    'routes'             => $routes,
    'web_index'          => readFileSafe($webIndexPath),
    'worklog_controller' => readFileSafe($worklogControllerPath),
    'mcp_controller'     => readFileSafe($mcpControllerPath),
    'rate_resolution'    => readFileSafe($rateResolutionPath),
    'finance_migration'  => readFileSafe($financeMigrationPath),
];

/**
 * @param array<string, mixed> $src
 * @param array<int, array{0:string, 1:Closure(array<string, mixed>):bool}> $checks
 * @return list<string>  names of failing checks
 */
function runChecks(array $src, array $checks): array
{
    $failures = [];
    foreach ($checks as [$name, $fn]) {
        try {
            $ok = $fn($src);
        } catch (\Throwable $e) {
            $ok = false;
        }
        if (!$ok) {
            $failures[] = $name;
        }
    }
    return $failures;
}

/** Family of a single field in FIELD_FAMILY, or null when absent. */
function fieldFamily(string $policy, string $field): ?string
{
    if (preg_match("/'" . preg_quote($field, '/') . "'\s*=>\s*'([a-z]+)'/", $policy, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * The 10 invariants of TZ 6.8. Each closure receives $src and returns true
 * when the invariant holds.
 *
 * @var array<int, array{0:string, 1:Closure(array<string, mixed>):bool}> $checks
 */
$checks = [
    // 6.8.1 — FinancialFieldPolicy exists and carries the UNCONDITIONAL
    // external-actor gate that strips others_cost/commercial/config.
    ['policy_exists_unconditional_external', function (array $src): bool {
        $p = (string)$src['policy'];
        return str_contains($p, 'final class FinancialFieldPolicy')
            && str_contains($p, 'if ($isExternal)');
    }],

    // 6.8.2 — the policy distinguishes observer from executor.
    ['policy_distinguishes_observer_executor', function (array $src): bool {
        $p = (string)$src['policy'];
        return str_contains($p, "\$externalRole === 'observer'")
            && str_contains($p, "\$externalRole === 'executor'");
    }],

    // 6.8.3 — own_cost is NEVER given to an external actor: the external
    // branch may only ever return the payout family (or nothing), never
    // cost/bill/config.
    ['policy_never_own_cost_to_external', function (array $src): bool {
        $p = (string)$src['policy'];
        if (!preg_match('/if \(\$isExternal\) \{(.*?)\/\/ --- Internal users/s', $p, $m)) {
            return false;
        }
        $block = $m[1];
        // No cost/bill/config family literal may be returned for external actors.
        if (preg_match("/'(cost|bill|config)'/", $block)) {
            return false;
        }
        // The executor path returns own payout, and observer/unknown fail closed.
        return str_contains($block, "['payout']") && str_contains($block, 'return [];');
    }],

    // 6.8.4 — among the section-7 routes, only /me/earnings and
    // /me/earnings/available may carry external_ok/external_executor_ok.
    ['section7_only_me_earnings_external', function (array $src): bool {
        $prefixes = [
            '/api/v1/rate-cards',
            '/api/v1/rate-card-lines',
            '/api/v1/rate-card-assignments',
            '/api/v1/rates/',
            '/api/v1/me/earnings',
        ];
        $expected = ['/api/v1/me/earnings', '/api/v1/me/earnings/available'];

        $found = [];
        foreach ((array)$src['routes'] as $route) {
            $pattern = (string)($route['pattern'] ?? '');
            $inSection7 = false;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($pattern, $prefix)) {
                    $inSection7 = true;
                    break;
                }
            }
            if (!$inSection7) {
                continue;
            }
            $isExternal = ($route['external_ok'] ?? false) === true
                || ($route['external_executor_ok'] ?? false) === true;
            if ($isExternal) {
                $found[] = $pattern;
            }
        }
        sort($found);
        sort($expected);
        return $found === $expected;
    }],

    // 6.8.5 — among the new pages, only my-earnings is in $externalAllowedRoutes.
    ['web_external_routes_only_my_earnings', function (array $src): bool {
        $s = (string)$src['web_index'];
        if (!preg_match('/\$externalAllowedRoutes\s*=\s*\[(.*?)\];/s', $s, $m)) {
            return false;
        }
        $block = $m[1];
        return str_contains($block, "'my-earnings'")
            && !str_contains($block, "'rate-cards'");
    }],

    // 6.8.6 — WorklogController and McpController must not hand-roll their own
    // unset() of financial fields (the policy is the single filter point).
    ['no_handrolled_financial_unset', function (array $src): bool {
        foreach (['worklog_controller', 'mcp_controller'] as $key) {
            $s = (string)$src[$key];
            if (preg_match(
                '/unset\s*\([^)]*(cost_rate|bill_rate|payout_rate|cost_amount|bill_amount|payout_amount|payout_rate_snapshot|cost_rate_snapshot|bill_rate_snapshot)[^)]*\)/',
                $s
            )) {
                return false;
            }
        }
        return true;
    }],

    // 6.8.7 — the field→family map includes every rate/amount column of 3.5.
    ['field_family_map_complete', function (array $src): bool {
        $p = (string)$src['policy'];
        $required = [
            'cost_rate_snapshot' => 'cost',
            'cost_rate' => 'cost',
            'cost_amount' => 'cost',
            'cost_source_type' => 'cost',
            'cost_source_ref' => 'cost',
            'override_cost_rate' => 'cost',
            'bill_rate_snapshot' => 'bill',
            'bill_rate' => 'bill',
            'bill_amount' => 'bill',
            'bill_source_type' => 'bill',
            'bill_source_ref' => 'bill',
            'override_bill_rate' => 'bill',
            'margin' => 'bill',
            'margin_amount' => 'bill',
            'payout_rate_snapshot' => 'payout',
            'payout_rate' => 'payout',
            'payout_amount' => 'payout',
            'payout_source_type' => 'payout',
            'payout_source_ref' => 'payout',
            'override_payout_rate' => 'payout',
        ];
        foreach ($required as $field => $family) {
            if (fieldFamily($p, $field) !== $family) {
                return false;
            }
        }
        return true;
    }],

    // 6.8.8 — anti-inference: each kind's rate snapshot and computed amount
    // live in the SAME family (rate and amount are disclosed or hidden together).
    ['anti_inference_rate_amount_same_family', function (array $src): bool {
        $p = (string)$src['policy'];
        foreach (['cost', 'bill', 'payout'] as $kind) {
            $rateFamily = fieldFamily($p, "{$kind}_rate_snapshot");
            $amountFamily = fieldFamily($p, "{$kind}_amount");
            if ($rateFamily === null || $amountFamily === null || $rateFamily !== $amountFamily) {
                return false;
            }
        }
        return true;
    }],

    // 6.8.9 — RateResolutionService derives cost from payout only, never the
    // reverse (4.6). payout is resolved before cost so the derivation is possible.
    ['no_payout_from_cost_derivation', function (array $src): bool {
        $s = (string)$src['rate_resolution'];
        return str_contains($s, "['payout', 'cost', 'bill']")
            && str_contains($s, 'derived_from_payout')
            && !str_contains($s, 'derived_from_cost');
    }],

    // 6.8.10 — the seeding migration grants no finance permission to any role
    // except external_guest → finance.rate.view_own_payout.
    ['migration_grants_only_external_guest_payout', function (array $src): bool {
        $m = (string)$src['finance_migration'];
        $grantsGuestPayout = str_contains($m, "code = 'external_guest'")
            && str_contains($m, "code = 'finance.rate.view_own_payout'");
        // A single role_permissions INSERT, reached only via the external_guest
        // + view_own_payout pair above.
        $rolePermInserts = substr_count($m, 'INSERT INTO role_permissions');
        return $grantsGuestPayout && $rolePermInserts === 1;
    }],
];

// ---------------------------------------------------------------------------
// Run
// ---------------------------------------------------------------------------

$failures = runChecks($src, $checks);

$mutationTest = in_array('--mutation-test', $argv, true);

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] finance_fields_security_check: {$failure}\n");
    }
    fwrite(STDERR, '=== Results: 0 passed, ' . count($failures) . " failed ===\n");
    exit(1);
}

if (!$mutationTest) {
    fwrite(STDOUT, "[OK] finance_fields_security_check\n");
    exit(0);
}

// ---------------------------------------------------------------------------
// Mutation testing (DoD #5). Each mutation corrupts one guarded source; the
// targeted invariant must then flip to FAIL. This proves the assertions are
// not vacuously green.
// ---------------------------------------------------------------------------

/**
 * @var array<int, array{0:string, 1:string, 2:Closure(array<string, mixed>):void}> $mutations
 */
$mutations = [
    // 1: remove the unconditional external gate.
    ['policy_exists_unconditional_external', 'policy', function (array &$src): void {
        $src['policy'] = str_replace('if ($isExternal)', 'if (false)', (string)$src['policy']);
    }],
    // 2: collapse observer/executor into one branch.
    ['policy_distinguishes_observer_executor', 'policy', function (array &$src): void {
        $src['policy'] = str_replace("\$externalRole === 'observer'", "true", (string)$src['policy']);
    }],
    // 3: leak cost to the executor.
    ['policy_never_own_cost_to_external', 'policy', function (array &$src): void {
        $src['policy'] = str_replace("? ['payout']", "? ['payout', 'cost']", (string)$src['policy']);
    }],
    // 4: open a rate-cards route to external executors.
    ['section7_only_me_earnings_external', 'routes', function (array &$src): void {
        foreach ($src['routes'] as &$route) {
            if (str_starts_with((string)($route['pattern'] ?? ''), '/api/v1/rate-cards')) {
                $route['external_executor_ok'] = true;
                break;
            }
        }
        unset($route);
    }],
    // 5: add rate-cards to the external page allowlist.
    ['web_external_routes_only_my_earnings', 'web_index', function (array &$src): void {
        $src['web_index'] = str_replace("'my-earnings'", "'my-earnings', 'rate-cards'", (string)$src['web_index']);
    }],
    // 6: reintroduce a hand-rolled unset.
    ['no_handrolled_financial_unset', 'worklog_controller', function (array &$src): void {
        $src['worklog_controller'] .= "\n// mutation\nunset(\$item['cost_rate']);\n";
    }],
    // 7: drop a field from the map.
    ['field_family_map_complete', 'policy', function (array &$src): void {
        $src['policy'] = str_replace("'payout_amount'          => 'payout',\n", '', (string)$src['policy']);
    }],
    // 8: move payout_amount into the wrong family.
    ['anti_inference_rate_amount_same_family', 'policy', function (array &$src): void {
        $src['policy'] = str_replace("'payout_amount'          => 'payout',", "'payout_amount'          => 'cost',", (string)$src['policy']);
    }],
    // 9: derive payout from cost.
    ['no_payout_from_cost_derivation', 'rate_resolution', function (array &$src): void {
        $src['rate_resolution'] = str_replace('derived_from_payout', 'derived_from_cost', (string)$src['rate_resolution']);
    }],
    // 10: grant the payout permission to the wrong role.
    ['migration_grants_only_external_guest_payout', 'finance_migration', function (array &$src): void {
        $src['finance_migration'] = str_replace(
            "code = 'external_guest'",
            "code = 'manager'",
            (string)$src['finance_migration']
        );
    }],
];

$mutationFailures = 0;
$mutationTotal = count($mutations);

foreach ($mutations as [$target, $kind, $mutate]) {
    $mutated = $src;
    $mutate($mutated);
    $failingNow = runChecks($mutated, $checks);
    if (!in_array($target, $failingNow, true)) {
        $mutationFailures++;
        fwrite(STDERR, "[MUTATION-FAIL] mutation of '{$kind}' did not trip invariant '{$target}'\n");
    }
}

if ($mutationFailures > 0) {
    fwrite(STDERR, "=== Mutation testing: {$mutationFailures} of {$mutationTotal} mutations NOT caught ===\n");
    exit(1);
}

fwrite(STDOUT, "[OK] finance_fields_security_check (mutation test: {$mutationTotal}/{$mutationTotal} caught)\n");
exit(0);
