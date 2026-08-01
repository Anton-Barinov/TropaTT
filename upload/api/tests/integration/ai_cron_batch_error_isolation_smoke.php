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

/**
 * @return array{exit_code:int,stdout:string,stderr:string}
 */
function runAiCronCommand(string $command): array
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];
    $process = proc_open($command, $descriptors, $pipes, dirname(__DIR__, 3));
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start process: ' . $command);
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'exit_code' => is_int($exitCode) ? $exitCode : 1,
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
    ];
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

    foreach (['ai.enabled', 'ai.cron.semantic_index_refresh', 'ai.cron.suggestion_cleanup', 'ai.cron.import_review'] as $flagCode) {
        $flag = findFlagByCodeOrFail($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];
    }

    // Ensure batch contains both success and failure paths.
    $setAiEnabled = request('PATCH', '/api/v1/feature-flags/' . $flagSnapshots['ai.enabled']['public_id'], ['is_enabled' => 1], $rootHeaders);
    assertTrue($setAiEnabled['status'] === 200, 'Enable ai.enabled must return 200');

    $setSemanticRefresh = request('PATCH', '/api/v1/feature-flags/' . $flagSnapshots['ai.cron.semantic_index_refresh']['public_id'], ['is_enabled' => 1], $rootHeaders);
    assertTrue($setSemanticRefresh['status'] === 200, 'Enable ai.cron.semantic_index_refresh must return 200');

    $setSuggestionCleanup = request('PATCH', '/api/v1/feature-flags/' . $flagSnapshots['ai.cron.suggestion_cleanup']['public_id'], ['is_enabled' => 1], $rootHeaders);
    assertTrue($setSuggestionCleanup['status'] === 200, 'Enable ai.cron.suggestion_cleanup must return 200');

    $setImportReview = request('PATCH', '/api/v1/feature-flags/' . $flagSnapshots['ai.cron.import_review']['public_id'], ['is_enabled' => 0], $rootHeaders);
    assertTrue($setImportReview['status'] === 200, 'Disable ai.cron.import_review must return 200');

    putenv('CRM_AI_CRON_BEARER_TOKEN=' . $root['token']);
    $runDate = date('Y-m-d', time() + 86400 * (500 + random_int(1, 7000)));
    $command = 'php ' . dirname(__DIR__, 3) . '/api/scripts/ai_cron.php ai:suggestion-cleanup --all --run-once --date=' . escapeshellarg($runDate) . ' --json';
    $execution = runAiCronCommand($command);

    // In mixed success/failure batch script returns exit code 4 by contract.
    assertTrue($execution['exit_code'] === 4, 'ai_cron --all run-once must return exit 4 when some jobs fail');

    $decoded = json_decode((string)$execution['stdout'], true);
    assertTrue(is_array($decoded), 'ai_cron --json output must be valid JSON');

    $items = is_array($decoded['items'] ?? null) ? (array)$decoded['items'] : [];
    assertTrue($items !== [], 'ai_cron --all JSON must contain items');
    assertTrue((int)($decoded['total'] ?? 0) === count($items), 'ai_cron --all JSON total must match items count');

    $okCount = 0;
    $errorCount = 0;
    $hasImportReviewFailure = false;
    $hasSuggestionCleanupItem = false;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $ok = (bool)($item['ok'] ?? false);
        if ($ok) {
            $okCount++;
        } else {
            $errorCount++;
        }
        $jobCode = (string)($item['job_code'] ?? '');
        if ($jobCode === 'ai:import-review' && !$ok) {
            $hasImportReviewFailure = true;
        }
        if ($jobCode === 'ai:suggestion-cleanup') {
            $hasSuggestionCleanupItem = true;
        }
    }

    assertTrue($okCount > 0, 'Batch run must contain at least one successful job');
    assertTrue($errorCount > 0, 'Batch run must contain at least one failed job');
    assertTrue((int)($decoded['failed'] ?? -1) === $errorCount, 'ai_cron --all JSON failed counter must match failed items');
    assertTrue($hasImportReviewFailure, 'Batch run must contain forced ai:import-review failure');
    assertTrue($hasSuggestionCleanupItem, 'Batch run must continue to final job (ai:suggestion-cleanup) after earlier failures');
} catch (Throwable $e) {
    $error = $e;
} finally {
    putenv('CRM_AI_CRON_BEARER_TOKEN');
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
    fwrite(STDERR, '[FAIL] ai_cron_batch_error_isolation_smoke: ' . $error->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "[OK] ai_cron_batch_error_isolation_smoke\n");
