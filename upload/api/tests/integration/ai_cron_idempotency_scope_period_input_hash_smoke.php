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

function queuedSuggestionCleanupTotal(array $headers): int
{
    $list = request('GET', '/api/v1/ai/jobs', [
        'job_type' => 'cron',
        'action_type' => 'suggestion_cleanup',
        'status' => 'queued',
        'scope_type' => 'system',
        'scope_public_id' => 'global',
        'limit' => 1,
        'page' => 1,
    ], $headers);
    assertTrue($list['status'] === 200, 'AI jobs list for queued suggestion_cleanup must return 200');
    return (int)($list['payload']['meta']['pagination']['total'] ?? 0);
}

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

    foreach (['ai.enabled', 'ai.cron.suggestion_cleanup'] as $flagCode) {
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

    $dryRunA = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run', [], $rootHeaders);
    assertTrue($dryRunA['status'] === 200, 'Dry-run A must return 200');
    $hashA = (string)($dryRunA['payload']['data']['dry_run']['idempotency_key_hash_preview'] ?? '');
    assertTrue($hashA !== '', 'Dry-run A must return idempotency_key_hash_preview');
    assertTrue((string)($dryRunA['payload']['data']['dry_run']['job_code'] ?? '') === 'ai:suggestion-cleanup', 'Dry-run A job_code mismatch');
    assertTrue((string)($dryRunA['payload']['data']['dry_run']['scope_type'] ?? '') === 'system', 'Dry-run A scope_type must be system');
    assertTrue((string)($dryRunA['payload']['data']['dry_run']['scope_public_id'] ?? '') === 'global', 'Dry-run A scope_public_id must be global');

    $dryRunARepeat = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run', [], $rootHeaders);
    assertTrue($dryRunARepeat['status'] === 200, 'Dry-run A repeat must return 200');
    $hashARepeat = (string)($dryRunARepeat['payload']['data']['dry_run']['idempotency_key_hash_preview'] ?? '');
    assertTrue($hashARepeat === $hashA, 'Idempotency hash must be stable for same job_code+scope+period+input');

    $futureDate = date('Y-m-d', time() + 86400);
    $dryRunPeriodChanged = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run', [
        'date' => $futureDate,
    ], $rootHeaders);
    assertTrue($dryRunPeriodChanged['status'] === 200, 'Dry-run period-changed must return 200');
    $hashPeriodChanged = (string)($dryRunPeriodChanged['payload']['data']['dry_run']['idempotency_key_hash_preview'] ?? '');
    assertTrue($hashPeriodChanged !== '' && $hashPeriodChanged !== $hashA, 'Idempotency hash must change when period changes');

    $dryRunInputChanged = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run', [
        'force' => 1,
    ], $rootHeaders);
    assertTrue($dryRunInputChanged['status'] === 200, 'Dry-run input-changed must return 200');
    $hashInputChanged = (string)($dryRunInputChanged['payload']['data']['dry_run']['idempotency_key_hash_preview'] ?? '');
    assertTrue($hashInputChanged !== '' && $hashInputChanged !== $hashA, 'Idempotency hash must change when input hash changes');

    $runOnceDate = date('Y-m-d', time() + 86400 * (370 + random_int(1, 5000)));
    $runOnceInput = ['date' => $runOnceDate];
    $queuedBefore = queuedSuggestionCleanupTotal($rootHeaders);

    $runOnceA = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/run-once', $runOnceInput, $rootHeaders);
    assertTrue(
        $runOnceA['status'] === 201,
        'Run-once A must return 201, got '
        . (int)$runOnceA['status']
        . ' code=' . (string)($runOnceA['payload']['code'] ?? 'n/a')
        . ' message=' . (string)($runOnceA['payload']['message'] ?? 'n/a')
    );
    assertTrue((string)($runOnceA['payload']['code'] ?? '') === 'AI_JOB_RUN_ONCE_SCHEDULED', 'Run-once A response code mismatch');
    $runOnceJobPublicId = (string)($runOnceA['payload']['data']['job']['public_id'] ?? '');
    assertTrue($runOnceJobPublicId !== '', 'Run-once A must return job public_id');
    $queuedAfterFirstRun = queuedSuggestionCleanupTotal($rootHeaders);
    assertTrue($queuedAfterFirstRun === ($queuedBefore + 1), 'First run-once must add exactly one queued cron job row');

    $runOnceDuplicate = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/run-once', $runOnceInput, $rootHeaders);
    assertTrue($runOnceDuplicate['status'] === 409, 'Duplicate run-once must return 409');
    assertTrue((string)($runOnceDuplicate['payload']['code'] ?? '') === 'AI_JOB_ALREADY_QUEUED', 'Duplicate run-once must be blocked by idempotency');
    $queuedAfterDuplicate = queuedSuggestionCleanupTotal($rootHeaders);
    assertTrue($queuedAfterDuplicate === $queuedAfterFirstRun, 'Duplicate run-once must not create additional queued cron job rows');
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
    fwrite(STDERR, '[FAIL] ai_cron_idempotency_scope_period_input_hash_smoke: ' . $error->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "[OK] ai_cron_idempotency_scope_period_input_hash_smoke\n");
