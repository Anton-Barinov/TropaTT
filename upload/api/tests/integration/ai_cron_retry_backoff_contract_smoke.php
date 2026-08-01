<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlagByCodeOrFail(array $items, string $code): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Feature flag not found: ' . $code);
}

const CRON_JOB_CODES = [
    'ai:user-daily-work-plan',
    'ai:user-daily-digest',
    'ai:user-weekly-plan',
    'ai:manager-weekly-digest',
    'ai:task-risk-scan',
    'ai:task-quality-scan',
    'ai:task-decomposition-scan',
    'ai:meeting-agenda',
    'ai:project-daily-summary',
    'ai:client-weekly-report',
    'ai:team-workload-scan',
    'ai:sla-approval-scan',
    'ai:data-quality-scan',
    'ai:import-review',
    'ai:security-log-review',
    'ai:webhook-health-review',
    'ai:workflow-audit',
    'ai:semantic-index-refresh',
    'ai:suggestion-cleanup',
];

$rootHeaders = [];
/** @var array<string,array{public_id:string,is_enabled:bool}> $flagSnapshots */
$flagSnapshots = [];
$error = null;

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    foreach (['ai.enabled', 'ai.cron.enabled'] as $flagCode) {
        $flag = findFlagByCodeOrFail($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must be 200 for ' . $flagCode);
    }

    $hasPositiveBackoff = false;
    $checkedCount = 0;

    foreach (CRON_JOB_CODES as $jobCode) {
        $dryRun = request('POST', '/api/v1/ai/jobs/' . $jobCode . '/dry-run', [], $rootHeaders);
        assertTrue($dryRun['status'] === 200, 'Dry-run must be 200 for job ' . $jobCode);

        $dryRunData = is_array($dryRun['payload']['data']['dry_run'] ?? null) ? (array)$dryRun['payload']['data']['dry_run'] : [];
        assertTrue($dryRunData !== [], 'Dry-run data must be present for job ' . $jobCode);

        $retry = is_array($dryRunData['retry'] ?? null) ? (array)$dryRunData['retry'] : [];
        assertTrue(array_key_exists('attempts', $retry), 'Retry policy must include attempts for job ' . $jobCode);
        assertTrue(array_key_exists('backoff_ms', $retry), 'Retry policy must include backoff_ms for job ' . $jobCode);

        $attempts = (int)($retry['attempts'] ?? -1);
        $backoff = (int)($retry['backoff_ms'] ?? -1);
        assertTrue($attempts >= 1, 'Retry attempts must be >= 1 for job ' . $jobCode);
        assertTrue($backoff >= 0, 'Retry backoff_ms must be >= 0 for job ' . $jobCode);

        if ($backoff > 0) {
            $hasPositiveBackoff = true;
        }
        $checkedCount++;
    }

    assertTrue($checkedCount === count(CRON_JOB_CODES), 'All cron job codes must be checked for retry/backoff policy');
    assertTrue($hasPositiveBackoff, 'At least one cron job must define positive retry backoff_ms');
} catch (Throwable $e) {
    $error = $e;
} finally {
    if ($rootHeaders !== []) {
        foreach ($flagSnapshots as $snapshot) {
            $flagPublicId = (string)($snapshot['public_id'] ?? '');
            if ($flagPublicId === '') {
                continue;
            }
            request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, [
                'is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0,
            ], $rootHeaders);
        }
    }
}

if ($error instanceof Throwable) {
    fwrite(STDERR, '[FAIL] ai_cron_retry_backoff_contract_smoke: ' . $error->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "[OK] ai_cron_retry_backoff_contract_smoke\n");
