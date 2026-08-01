<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/** @param array<int,mixed> $items @return array<string,mixed> */
function findFlagByCodeOrFail475(array $items, string $code): array
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
function findIntentByCodeOrFail475(array $items, string $intentCode): array
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

/** @param mixed $value @param array<int,string> $forbidden */
function assertNoForbiddenKeysRecursive(mixed $value, array $forbidden, string $context): void
{
    if (!is_array($value)) {
        return;
    }

    foreach ($value as $key => $item) {
        if (is_string($key)) {
            $normalized = strtolower(trim($key));
            foreach ($forbidden as $forbiddenKey) {
                if ($normalized === strtolower($forbiddenKey)) {
                    throw new RuntimeException($context . ': forbidden key found -> ' . $key);
                }
            }
        }
        assertNoForbiddenKeysRecursive($item, $forbidden, $context);
    }
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

    // Schema-level guard: no source_context_json/source_context_hash columns defined in AI migrations.
    $migrationFiles = [
        __DIR__ . '/../../system/library/database/migration/AiFoundationMigration.php',
        __DIR__ . '/../../system/library/database/migration/AiIndexCoverageMigration.php',
        __DIR__ . '/../../system/library/database/migration/AiAuthorTimestampCoverageMigration.php',
        __DIR__ . '/../../system/library/database/migration/AiSuggestionsInputHashMigration.php',
    ];
    foreach ($migrationFiles as $migrationFile) {
        $content = file_get_contents($migrationFile);
        assertTrue(is_string($content) && $content !== '', 'Migration file must be readable: ' . $migrationFile);
        assertTrue(stripos($content, 'source_context_json') === false, 'source_context_json must not be persisted in migration schema: ' . basename($migrationFile));
        assertTrue(stripos($content, 'source_context_hash') === false, 'source_context_hash must not be persisted in migration schema: ' . basename($migrationFile));
    }

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $flagCodes = ['ai.enabled', 'ai.task'];
    $flagSnapshots = [];
    foreach ($flagCodes as $flagCode) {
        $flag = findFlagByCodeOrFail475($flagItems, $flagCode);
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
        'title' => 'AI Source Context Storage Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-source-context',
        'provider_payload' => [
            'mock_models' => ['mock-source-context'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');
    $restore['provider_public_id'] = $providerPublicId;

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'source-context-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    $taskSummaryIntent = findIntentByCodeOrFail475($intentItems, 'task_summary');

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
        'model' => 'mock-source-context',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens']),
    ], $rootHeaders);
    assertTrue($patchIntent['status'] === 200, 'task_summary intent patch status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Source Context Storage Task ' . randomSuffix(),
        'description' => 'Sensitive markers: email sensitive.test@example.com, phone +7 (999) 123-45-67.',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Check source context storage contract',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary suggestion create status must be 201');

    $suggestion = (array)($taskSummary['payload']['data']['suggestion'] ?? []);
    $suggestionPublicId = (string)($suggestion['public_id'] ?? '');
    $jobPublicId = (string)($taskSummary['payload']['data']['job_public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');
    assertTrue($jobPublicId !== '', 'Job public_id is required');

    assertTrue(!array_key_exists('source_context_json', $suggestion), 'Suggestion payload must not expose source_context_json');
    assertTrue(!array_key_exists('source_context_hash', $suggestion), 'Suggestion payload must not expose source_context_hash');

    $suggestionDetail = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $rootHeaders);
    assertTrue($suggestionDetail['status'] === 200, 'Suggestion detail status must be 200');
    $detailSuggestion = (array)($suggestionDetail['payload']['data']['suggestion'] ?? []);
    assertTrue(!array_key_exists('source_context_json', $detailSuggestion), 'Suggestion detail must not expose source_context_json');
    assertTrue(!array_key_exists('source_context_hash', $detailSuggestion), 'Suggestion detail must not expose source_context_hash');

    $detailPayload = is_array($detailSuggestion['payload'] ?? null) ? (array)$detailSuggestion['payload'] : [];
    assertNoForbiddenKeysRecursive($detailPayload, ['source_context_json', 'source_context_hash', 'source_context'], 'suggestion payload');

    $jobDetail = request('GET', '/api/v1/ai/jobs/' . $jobPublicId, [], $rootHeaders);
    assertTrue($jobDetail['status'] === 200, 'Job detail status must be 200');
    $job = (array)($jobDetail['payload']['data']['job'] ?? []);
    assertTrue(!array_key_exists('payload_json', $job), 'Job detail must not expose raw payload_json');
    assertTrue(!array_key_exists('result_json', $job), 'Job detail must not expose raw result_json');

    fwrite(STDOUT, "[OK] ai_source_context_not_stored_smoke\n");
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
    fwrite(STDERR, "[FAIL] ai_source_context_not_stored_smoke: " . $failedMessage . "\n");
    exit(1);
}
