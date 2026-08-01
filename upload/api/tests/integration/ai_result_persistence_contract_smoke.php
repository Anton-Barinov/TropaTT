<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findByCodeOrFail(array $items, string $code): array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['code'] ?? '') === $code) {
            return $item;
        }
    }

    throw new RuntimeException('Item not found by code: ' . $code);
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

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $hasAiDashboard = false;
    foreach ($flagItems as $flagItem) {
        if (!is_array($flagItem)) {
            continue;
        }
        if ((string)($flagItem['code'] ?? '') === 'ai.dashboard') {
            $hasAiDashboard = true;
            break;
        }
    }
    $dashboardFeatureFlag = $hasAiDashboard ? 'ai.dashboard' : 'ai.analytics';

    $flagCodes = ['ai.enabled', 'ai.task', 'ai.client', $dashboardFeatureFlag];
    $flagSnapshots = [];
    foreach ($flagCodes as $flagCode) {
        $flag = findByCodeOrFail($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Feature flag enable must return 200 for ' . $flagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Persistence Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-persistence-default',
        'provider_payload' => [
            'mock_models' => ['mock-persistence-default', 'mock-persistence-alt'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'persistence-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);

    $intentCodes = ['task_summary', 'dashboard_daily_digest', 'client_safe_report'];
    $intentSnapshots = [];
    foreach ($intentCodes as $intentCode) {
        $intent = findIntentOrFail($intentItems, $intentCode);
        $intentSnapshots[$intentCode] = [
            'provider_public_id' => trim((string)($intent['provider_public_id'] ?? '')),
            'model' => (string)($intent['model'] ?? ''),
            'feature_flag' => (string)($intent['feature_flag'] ?? ''),
            'required_permission' => (string)($intent['required_permission'] ?? ''),
            'is_enabled' => (bool)($intent['is_enabled'] ?? true),
            'max_tokens' => (int)($intent['max_tokens'] ?? 0),
        ];

        $patch = request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'provider_public_id' => $providerPublicId,
            'model' => 'mock-persistence-default',
            'feature_flag' => match ($intentCode) {
                'task_summary' => 'ai.task',
                'dashboard_daily_digest' => $dashboardFeatureFlag,
                default => 'ai.client',
            },
            'required_permission' => $intentSnapshots[$intentCode]['required_permission'],
            'is_enabled' => 1,
            'max_tokens' => max(1, $intentSnapshots[$intentCode]['max_tokens'] > 0 ? $intentSnapshots[$intentCode]['max_tokens'] : 1200),
        ], $rootHeaders);
        assertTrue($patch['status'] === 200, 'Intent patch status must be 200 for ' . $intentCode);
    }

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI Persistence Task ' . randomSuffix(),
        'description' => 'Persistent output contract validation task',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $taskSummary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [
        'prompt' => 'Create compact summary for persistence checks',
    ], $rootHeaders);
    assertTrue($taskSummary['status'] === 201, 'Task summary AI create must be 201');
    $taskSuggestionPublicId = (string)($taskSummary['payload']['data']['suggestion']['public_id'] ?? '');
    $taskJobPublicId = (string)($taskSummary['payload']['data']['job_public_id'] ?? '');
    assertTrue($taskSuggestionPublicId !== '', 'Task summary suggestion public_id is required');
    assertTrue($taskJobPublicId !== '', 'Task summary job_public_id is required');

    $taskSuggestionGet = request('GET', '/api/v1/ai/suggestions/' . $taskSuggestionPublicId, [], $rootHeaders);
    assertTrue($taskSuggestionGet['status'] === 200, 'Task summary suggestion detail must be 200');
    $taskSuggestion = (array)($taskSuggestionGet['payload']['data']['suggestion'] ?? []);
    assertTrue((string)($taskSuggestion['status'] ?? '') === 'draft', 'Task summary suggestion must be persisted in draft status');
    $taskPayload = is_array($taskSuggestion['payload'] ?? null) ? (array)$taskSuggestion['payload'] : [];
    assertTrue($taskPayload !== [], 'Task summary suggestion payload must be persisted');

    $taskJobGet = request('GET', '/api/v1/ai/jobs/' . $taskJobPublicId, [], $rootHeaders);
    assertTrue($taskJobGet['status'] === 200, 'Task summary job detail must be 200');
    $taskJob = (array)($taskJobGet['payload']['data']['job'] ?? []);
    $taskJobResultFields = is_array($taskJob['result_fields'] ?? null) ? (array)$taskJob['result_fields'] : [];
    assertTrue(in_array('suggestion_public_id', $taskJobResultFields, true), 'Task summary job result must be persisted (result_fields includes suggestion_public_id)');

    $dashboardDigest = request('POST', '/api/v1/ai/dashboard/digest', [], $rootHeaders);
    assertTrue($dashboardDigest['status'] === 201, 'Dashboard digest AI create must be 201');
    $digestSuggestionPublicId = (string)($dashboardDigest['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($digestSuggestionPublicId !== '', 'Dashboard digest suggestion public_id is required');

    $digestSuggestionGet = request('GET', '/api/v1/ai/suggestions/' . $digestSuggestionPublicId, [], $rootHeaders);
    assertTrue($digestSuggestionGet['status'] === 200, 'Dashboard digest suggestion detail must be 200');
    $digestSuggestion = (array)($digestSuggestionGet['payload']['data']['suggestion'] ?? []);
    assertTrue((string)($digestSuggestion['intent_code'] ?? '') === 'dashboard_daily_digest', 'Dashboard digest intent_code must be persisted');
    assertTrue((string)($digestSuggestion['status'] ?? '') === 'draft', 'Dashboard digest suggestion must be draft');
    $digestPayload = is_array($digestSuggestion['payload'] ?? null) ? (array)$digestSuggestion['payload'] : [];
    assertTrue(trim((string)($digestPayload['summary'] ?? '')) !== '', 'Dashboard digest payload summary must be persisted');

    $clientCreate = request('POST', '/api/v1/clients', [
        'title' => 'AI Persistence Client ' . randomSuffix(),
        'client_type' => 'legal_entity',
        'legal_name' => 'AI Persistence Client LLC',
        'tax_inn' => '1234567890',
        'tax_kpp' => '123456789',
        'tax_ogrn' => '1234567890123',
        'status' => 'active',
    ], $rootHeaders);
    assertTrue($clientCreate['status'] === 201, 'Client create status must be 201');
    $clientPublicId = (string)($clientCreate['payload']['data']['client']['public_id'] ?? '');
    assertTrue($clientPublicId !== '', 'Client public_id is required');

    $clientSafeReport = request('POST', '/api/v1/ai/clients/' . $clientPublicId . '/client-safe-report', [], $rootHeaders);
    assertTrue($clientSafeReport['status'] === 201, 'Client-safe-report AI create must be 201');
    $clientSuggestionPublicId = (string)($clientSafeReport['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($clientSuggestionPublicId !== '', 'Client-safe-report suggestion public_id is required');

    $clientSuggestionGet = request('GET', '/api/v1/ai/suggestions/' . $clientSuggestionPublicId, [], $rootHeaders);
    assertTrue($clientSuggestionGet['status'] === 200, 'Client-safe-report suggestion detail must be 200');
    $clientSuggestion = (array)($clientSuggestionGet['payload']['data']['suggestion'] ?? []);
    $clientPayload = is_array($clientSuggestion['payload'] ?? null) ? (array)$clientSuggestion['payload'] : [];
    assertTrue(trim((string)($clientPayload['report_draft'] ?? '')) !== '', 'Client-safe-report draft text must be persisted');

    $actionRun = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => $taskPublicId,
        'input_text' => 'Persistence contract check',
    ], $rootHeaders);
    assertTrue($actionRun['status'] === 200, 'AI action task_summary must return 200');
    $actionJobPublicId = (string)($actionRun['payload']['data']['result']['job_public_id'] ?? '');
    assertTrue($actionJobPublicId !== '', 'AI action job_public_id is required');

    $actionJobGet = request('GET', '/api/v1/ai/jobs/' . $actionJobPublicId, [], $rootHeaders);
    assertTrue($actionJobGet['status'] === 200, 'AI action job detail must be 200');
    $actionJob = (array)($actionJobGet['payload']['data']['job'] ?? []);
    $actionJobResultFields = is_array($actionJob['result_fields'] ?? null) ? (array)$actionJob['result_fields'] : [];
    assertTrue(in_array('suggestion', $actionJobResultFields, true), 'AI action job result_json must be persisted (suggestion field expected)');

    foreach ($intentCodes as $intentCode) {
        $snapshot = $intentSnapshots[$intentCode] ?? null;
        if (!is_array($snapshot)) {
            continue;
        }
        request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'provider_public_id' => (string)($snapshot['provider_public_id'] ?? ''),
            'model' => (string)($snapshot['model'] ?? ''),
            'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
            'required_permission' => (string)($snapshot['required_permission'] ?? ''),
            'is_enabled' => (bool)($snapshot['is_enabled'] ?? true) ? 1 : 0,
            'max_tokens' => max(1, (int)($snapshot['max_tokens'] ?? 0) > 0 ? (int)$snapshot['max_tokens'] : 1200),
        ], $rootHeaders);
    }

    foreach ($flagCodes as $flagCode) {
        $snapshot = $flagSnapshots[$flagCode] ?? null;
        if (!is_array($snapshot)) {
            continue;
        }
        $flagPublicId = (string)($snapshot['public_id'] ?? '');
        if ($flagPublicId === '') {
            continue;
        }
        request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0], $rootHeaders);
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_result_persistence_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_result_persistence_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
