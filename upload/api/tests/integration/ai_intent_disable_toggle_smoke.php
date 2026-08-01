<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlag(array $items, string $code): array
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

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $aiEnabledFlag = findFlag($flagItems, 'ai.enabled');
    $aiTaskFlag = findFlag($flagItems, 'ai.task');

    $aiEnabledPublicId = (string)($aiEnabledFlag['public_id'] ?? '');
    $aiTaskPublicId = (string)($aiTaskFlag['public_id'] ?? '');
    assertTrue($aiEnabledPublicId !== '', 'ai.enabled public_id is required');
    assertTrue($aiTaskPublicId !== '', 'ai.task public_id is required');

    $aiEnabledOriginal = (bool)($aiEnabledFlag['is_enabled'] ?? false);
    $aiTaskOriginal = (bool)($aiTaskFlag['is_enabled'] ?? false);

    $minuteLimitOriginal = null;
    $dayLimitOriginal = null;
    $minuteLimitSetting = request('GET', '/api/v1/settings/max_requests_per_minute?scope=ai_limits', [], $rootHeaders);
    if ($minuteLimitSetting['status'] === 200) {
        $minuteLimitOriginal = $minuteLimitSetting['payload']['data']['setting']['value'] ?? null;
    }
    $dayLimitSetting = request('GET', '/api/v1/settings/max_requests_per_day?scope=ai_limits', [], $rootHeaders);
    if ($dayLimitSetting['status'] === 200) {
        $dayLimitOriginal = $dayLimitSetting['payload']['data']['setting']['value'] ?? null;
    }

    $setMinuteLimit = request('PATCH', '/api/v1/settings/max_requests_per_minute', [
        'scope' => 'ai_limits',
        'value' => 5000,
    ], $rootHeaders);
    assertTrue($setMinuteLimit['status'] === 200, 'Set max_requests_per_minute must be 200');

    $setDayLimit = request('PATCH', '/api/v1/settings/max_requests_per_day', [
        'scope' => 'ai_limits',
        'value' => 50000,
    ], $rootHeaders);
    assertTrue($setDayLimit['status'] === 200, 'Set max_requests_per_day must be 200');

    $enableAi = request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAi['status'] === 200, 'Enable ai.enabled must be 200');

    $enableAiTask = request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => 1], $rootHeaders);
    assertTrue($enableAiTask['status'] === 200, 'Enable ai.task must be 200');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Intent Toggle Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-intent-toggle',
        'provider_payload' => [
            'mock_models' => ['mock-intent-toggle'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create must be 201');

    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $setSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'intent-toggle-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($setSecret['status'] === 200, 'Set provider secret must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);

    $taskSummaryIntent = null;
    foreach ($intentItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryIntent = $item;
            break;
        }
    }
    assertTrue(is_array($taskSummaryIntent), 'task_summary intent setting must exist');

    $intentOriginalProvider = trim((string)($taskSummaryIntent['provider_public_id'] ?? ''));
    $intentOriginalModel = (string)($taskSummaryIntent['model'] ?? '');
    $intentOriginalFeatureFlag = (string)($taskSummaryIntent['feature_flag'] ?? '');
    $intentOriginalEnabled = (bool)($taskSummaryIntent['is_enabled'] ?? true);
    $intentOriginalMaxTokens = (int)($taskSummaryIntent['max_tokens'] ?? 2000);

    $bindIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-intent-toggle',
        'feature_flag' => 'ai.task',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentOriginalMaxTokens),
    ], $rootHeaders);
    assertTrue($bindIntent['status'] === 200, 'Enable task_summary intent must be 200');

    $enabledAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_intent_enabled_' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($enabledAction['status'] === 200, 'Enabled task_summary action must be 200');

    $disableIntent = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-intent-toggle',
        'feature_flag' => 'ai.task',
        'is_enabled' => 0,
        'max_tokens' => max(1, $intentOriginalMaxTokens),
    ], $rootHeaders);
    assertTrue($disableIntent['status'] === 200, 'Disable task_summary intent must be 200');

    $disabledAction = request('POST', '/api/v1/ai/actions/task_summary', [
        'scope_type' => 'task',
        'scope_public_id' => 'tsk_intent_disabled_' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($disabledAction['status'] === 409, 'Disabled task_summary action must be 409');
    assertTrue((string)($disabledAction['payload']['code'] ?? '') === 'AI_INTENT_DISABLED', 'Disabled task_summary must return AI_INTENT_DISABLED');

    request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $intentOriginalProvider,
        'model' => $intentOriginalModel,
        'feature_flag' => $intentOriginalFeatureFlag,
        'is_enabled' => $intentOriginalEnabled ? 1 : 0,
        'max_tokens' => max(1, $intentOriginalMaxTokens),
    ], $rootHeaders);

    request('PATCH', '/api/v1/feature-flags/' . $aiEnabledPublicId, ['is_enabled' => $aiEnabledOriginal ? 1 : 0], $rootHeaders);
    request('PATCH', '/api/v1/feature-flags/' . $aiTaskPublicId, ['is_enabled' => $aiTaskOriginal ? 1 : 0], $rootHeaders);

    if ($minuteLimitOriginal !== null) {
        request('PATCH', '/api/v1/settings/max_requests_per_minute', [
            'scope' => 'ai_limits',
            'value' => $minuteLimitOriginal,
        ], $rootHeaders);
    }
    if ($dayLimitOriginal !== null) {
        request('PATCH', '/api/v1/settings/max_requests_per_day', [
            'scope' => 'ai_limits',
            'value' => $dayLimitOriginal,
        ], $rootHeaders);
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_intent_disable_toggle_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_intent_disable_toggle_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
