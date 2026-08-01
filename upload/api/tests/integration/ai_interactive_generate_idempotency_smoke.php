<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlagOrFail(array $items, string $code): array
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
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findIntentOrFail(array $items, string $intentCode): array
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

$rootHeaders = [];
$providerPublicId = '';
/** @var array<string,array{public_id:string,is_enabled:bool}> $flagSnapshots */
$flagSnapshots = [];
/** @var array<string,array{provider_public_id:string,model:string,feature_flag:string,required_permission:string,is_enabled:bool,max_tokens:int}> $intentSnapshots */
$intentSnapshots = [];
$error = null;

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    foreach (['ai.enabled', 'ai.task', 'ai.project'] as $flagCode) {
        $flag = findFlagOrFail($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must be 200 for ' . $flagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Interactive Idempotency Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-interactive-idempotency',
        'provider_payload' => [
            'mock_models' => ['mock-interactive-idempotency'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'interactive-idempotency-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);

    foreach (['task_summary' => 'ai.task', 'project_summary' => 'ai.project'] as $intentCode => $featureFlag) {
        $intent = findIntentOrFail($intentItems, $intentCode);
        $intentSnapshots[$intentCode] = [
            'provider_public_id' => trim((string)($intent['provider_public_id'] ?? '')),
            'model' => (string)($intent['model'] ?? ''),
            'feature_flag' => (string)($intent['feature_flag'] ?? ''),
            'required_permission' => (string)($intent['required_permission'] ?? ''),
            'is_enabled' => (bool)($intent['is_enabled'] ?? true),
            'max_tokens' => (int)($intent['max_tokens'] ?? 0),
        ];

        $patchIntent = request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'provider_public_id' => $providerPublicId,
            'model' => 'mock-interactive-idempotency',
            'feature_flag' => $featureFlag,
            'required_permission' => (string)($intentSnapshots[$intentCode]['required_permission'] ?? 'ai.use'),
            'is_enabled' => 1,
            'max_tokens' => max(1, (int)($intentSnapshots[$intentCode]['max_tokens'] ?? 0) > 0 ? (int)$intentSnapshots[$intentCode]['max_tokens'] : 1200),
        ], $rootHeaders);
        assertTrue($patchIntent['status'] === 200, 'Intent patch status must be 200 for ' . $intentCode);
    }

    $projectCreate = request('POST', '/api/v1/projects', [
        'title' => 'AI Interactive Idempotency Project ' . randomSuffix(),
        'description' => 'Project scope idempotency check',
    ], $rootHeaders);
    assertTrue($projectCreate['status'] === 201, 'Project create status must be 201');
    $projectPublicId = (string)($projectCreate['payload']['data']['project']['public_id'] ?? '');
    assertTrue($projectPublicId !== '', 'Project public_id is required');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Interactive Idempotency Task ' . randomSuffix(),
        'description' => 'Task scope idempotency check',
        'project_public_id' => $projectPublicId,
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskKey = 'ai-task-summary-idem-' . randomSuffix();
    $taskSummaryBody = ['prompt' => 'Сформируй безопасную краткую сводку по задаче.'];
    $taskSummaryFirst = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', $taskSummaryBody, array_merge($rootHeaders, [
        'X-Idempotency-Key' => $taskKey,
    ]));
    assertTrue(
        $taskSummaryFirst['status'] === 201,
        'Task summary #1 status must be 201, got '
        . (int)$taskSummaryFirst['status']
        . ' code=' . (string)($taskSummaryFirst['payload']['code'] ?? 'n/a')
        . ' message=' . (string)($taskSummaryFirst['payload']['message'] ?? 'n/a')
    );
    $taskSummarySecond = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', $taskSummaryBody, array_merge($rootHeaders, [
        'X-Idempotency-Key' => $taskKey,
    ]));
    assertTrue($taskSummarySecond['status'] === 201, 'Task summary #2 status must be 201');
    $taskSuggestionPublicId1 = (string)($taskSummaryFirst['payload']['data']['suggestion']['public_id'] ?? '');
    $taskSuggestionPublicId2 = (string)($taskSummarySecond['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($taskSuggestionPublicId1 !== '' && $taskSuggestionPublicId1 === $taskSuggestionPublicId2, 'Task summary idempotency must return same suggestion public_id');
    assertTrue((bool)($taskSummarySecond['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Task summary replay must be marked as idempotency_replayed');

    $projectKey = 'ai-project-summary-idem-' . randomSuffix();
    $projectSummaryBody = ['prompt' => 'Сформируй безопасную сводку по проекту без auto-apply.'];
    $projectSummaryFirst = request('POST', '/api/v1/ai/projects/' . $projectPublicId . '/summary', $projectSummaryBody, array_merge($rootHeaders, [
        'X-Idempotency-Key' => $projectKey,
    ]));
    assertTrue(
        $projectSummaryFirst['status'] === 201,
        'Project summary #1 status must be 201, got '
        . (int)$projectSummaryFirst['status']
        . ' code=' . (string)($projectSummaryFirst['payload']['code'] ?? 'n/a')
        . ' message=' . (string)($projectSummaryFirst['payload']['message'] ?? 'n/a')
    );
    $projectSummarySecond = request('POST', '/api/v1/ai/projects/' . $projectPublicId . '/summary', $projectSummaryBody, array_merge($rootHeaders, [
        'X-Idempotency-Key' => $projectKey,
    ]));
    assertTrue($projectSummarySecond['status'] === 201, 'Project summary #2 status must be 201');
    $projectSuggestionPublicId1 = (string)($projectSummaryFirst['payload']['data']['suggestion']['public_id'] ?? '');
    $projectSuggestionPublicId2 = (string)($projectSummarySecond['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($projectSuggestionPublicId1 !== '' && $projectSuggestionPublicId1 === $projectSuggestionPublicId2, 'Project summary idempotency must return same suggestion public_id');
    assertTrue((bool)($projectSummarySecond['payload']['meta']['idempotency_replayed'] ?? false) === true, 'Project summary replay must be marked as idempotency_replayed');
} catch (Throwable $e) {
    $error = $e;
} finally {
    if ($rootHeaders !== []) {
        foreach ($intentSnapshots as $intentCode => $snapshot) {
            $maxTokens = (int)($snapshot['max_tokens'] ?? 0);
            request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
                'provider_public_id' => (string)($snapshot['provider_public_id'] ?? ''),
                'model' => (string)($snapshot['model'] ?? ''),
                'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
                'required_permission' => (string)($snapshot['required_permission'] ?? 'ai.use'),
                'is_enabled' => (bool)($snapshot['is_enabled'] ?? true) ? 1 : 0,
                'max_tokens' => max(1, $maxTokens > 0 ? $maxTokens : 1200),
            ], $rootHeaders);
        }

        foreach ($flagSnapshots as $snapshot) {
            $flagPublicId = (string)($snapshot['public_id'] ?? '');
            if ($flagPublicId === '') {
                continue;
            }
            request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, [
                'is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0,
            ], $rootHeaders);
        }

        if ($providerPublicId !== '') {
            request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);
        }
    }
}

if ($error instanceof Throwable) {
    fwrite(STDERR, '[FAIL] ai_interactive_generate_idempotency_smoke: ' . $error->getMessage() . "\n");
    exit(1);
}

fwrite(STDOUT, "[OK] ai_interactive_generate_idempotency_smoke\n");
