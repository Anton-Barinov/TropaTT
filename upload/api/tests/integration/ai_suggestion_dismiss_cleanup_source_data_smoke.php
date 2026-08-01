<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<int,mixed> $items @return array<string,mixed> */
function findFlagByCodeOrFail473(array $items, string $code): array
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

/** @param array<int,mixed> $items @return array<string,mixed> */
function findIntentByCodeOrFail473(array $items, string $intentCode): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === $intentCode) {
            return $item;
        }
    }

    throw new RuntimeException('Intent setting not found: ' . $intentCode);
}

$restore = [
    'root_headers' => [],
    'flag_snapshots' => [],
    'intent_snapshot' => null,
    'provider_public_id' => '',
];
$failedMessage = '';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);
    $restore['root_headers'] = $rootHeaders;

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $flagCodes = ['ai.enabled', 'ai.task', 'ai.cron.enabled', 'ai.cron.suggestion_cleanup'];
    $flagSnapshots = [];
    foreach ($flagCodes as $flagCode) {
        $flag = findFlagByCodeOrFail473($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];

        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must return 200 for ' . $flagCode);
    }
    $restore['flag_snapshots'] = $flagSnapshots;

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Dismiss/Cleanup Source Contract Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-dismiss-cleanup',
        'provider_payload' => [
            'mock_models' => ['mock-dismiss-cleanup'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');
    $restore['provider_public_id'] = $providerPublicId;

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'dismiss-cleanup-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentByCodeOrFail473($intentItems, 'task_summary');

    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskSummaryIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskSummaryIntent['model'] ?? ''),
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? 'ai.task'),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? 'ai.use'),
        'is_enabled' => (bool)($taskSummaryIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskSummaryIntent['max_tokens'] ?? 1200),
    ];
    $restore['intent_snapshot'] = $intentSnapshot;

    $patchIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-dismiss-cleanup',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens']),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'Intent patch status must be 200 for task_summary');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Dismiss/Cleanup Source Contract Task ' . randomSuffix(),
        'description' => 'Task for dismiss/cleanup source data guard',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $task = (array)($taskCreate['payload']['data']['task'] ?? []);
    $taskPublicId = (string)($task['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskBefore = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $rootHeaders);
    assertTrue($taskBefore['status'] === 200, 'Task get before suggestion flow must be 200');
    $taskBeforeData = (array)($taskBefore['payload']['data']['task'] ?? []);
    $taskBeforeTitle = (string)($taskBeforeData['title'] ?? '');
    $taskBeforeDescription = (string)($taskBeforeData['description'] ?? '');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Create summary for dismiss-cleanup source guard test',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary suggestion create must return 201');
    $suggestionPublicId = (string)($taskSummary['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Task summary suggestion public_id is required');

    $dismiss = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/dismiss', [], $rootHeaders);
    assertTrue($dismiss['status'] === 200, 'Suggestion dismiss status must return 200');
    assertTrue((string)($dismiss['payload']['data']['suggestion']['status'] ?? '') === 'dismissed', 'Suggestion status after dismiss must be dismissed');

    $taskAfterDismiss = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $rootHeaders);
    assertTrue($taskAfterDismiss['status'] === 200, 'Task must still exist after suggestion dismiss');
    $taskAfterDismissData = (array)($taskAfterDismiss['payload']['data']['task'] ?? []);
    assertTrue((string)($taskAfterDismissData['title'] ?? '') === $taskBeforeTitle, 'Dismiss must not mutate source task title');
    assertTrue((string)($taskAfterDismissData['description'] ?? '') === $taskBeforeDescription, 'Dismiss must not mutate source task description');

    $cleanupRunOnce = request('POST', '/api/v1/ai/jobs/ai:suggestion-cleanup/run-once', [], $rootHeaders);
    assertTrue($cleanupRunOnce['status'] === 201, 'Suggestion-cleanup run-once status must be 201');
    assertTrue((string)($cleanupRunOnce['payload']['code'] ?? '') === 'AI_JOB_RUN_ONCE_SCHEDULED', 'Suggestion-cleanup run-once code mismatch');

    $taskAfterCleanupRunOnce = request('GET', '/api/v1/tasks/' . $taskPublicId, [], $rootHeaders);
    assertTrue($taskAfterCleanupRunOnce['status'] === 200, 'Task must still exist after suggestion cleanup run-once scheduling');
    $taskAfterCleanupData = (array)($taskAfterCleanupRunOnce['payload']['data']['task'] ?? []);
    assertTrue((string)($taskAfterCleanupData['title'] ?? '') === $taskBeforeTitle, 'Cleanup run-once must not mutate source task title');
    assertTrue((string)($taskAfterCleanupData['description'] ?? '') === $taskBeforeDescription, 'Cleanup run-once must not mutate source task description');

    fwrite(STDOUT, "[OK] ai_suggestion_dismiss_cleanup_source_data_smoke\n");
} catch (Throwable $e) {
    $failedMessage = $e->getMessage();
} finally {
    $rootHeaders = is_array($restore['root_headers']) ? (array)$restore['root_headers'] : [];
    if ($rootHeaders !== []) {
        $intentSnapshot = is_array($restore['intent_snapshot']) ? (array)$restore['intent_snapshot'] : [];
        if ($intentSnapshot !== []) {
            request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
                'provider_public_id' => (string)($intentSnapshot['provider_public_id'] ?? ''),
                'model' => (string)($intentSnapshot['model'] ?? ''),
                'feature_flag' => (string)($intentSnapshot['feature_flag'] ?? 'ai.task'),
                'required_permission' => (string)($intentSnapshot['required_permission'] ?? 'ai.use'),
                'is_enabled' => (bool)($intentSnapshot['is_enabled'] ?? true) ? 1 : 0,
                'max_tokens' => max(1, (int)($intentSnapshot['max_tokens'] ?? 1200)),
            ], $rootHeaders);
        }

        $flagSnapshots = is_array($restore['flag_snapshots']) ? (array)$restore['flag_snapshots'] : [];
        foreach ($flagSnapshots as $snapshot) {
            if (!is_array($snapshot)) {
                continue;
            }
            $flagPublicId = (string)($snapshot['public_id'] ?? '');
            if ($flagPublicId === '') {
                continue;
            }
            request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, [
                'is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0,
            ], $rootHeaders);
        }

        $providerPublicId = trim((string)($restore['provider_public_id'] ?? ''));
        if ($providerPublicId !== '') {
            request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
        }
    }
}

if ($failedMessage !== '') {
    fwrite(STDERR, "[FAIL] ai_suggestion_dismiss_cleanup_source_data_smoke: " . $failedMessage . "\n");
    exit(1);
}
