<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlagForDismissSmoke(array $items, string $code): array
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
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flags = request('GET', '/api/v1/feature-flags', [], $headers);
    assertTrue($flags['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flags['payload']['data']['items'] ?? []);
    foreach (['ai.enabled', 'ai.task'] as $flagCode) {
        $flag = findFlagForDismissSmoke($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagEnable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $headers);
        assertTrue($flagEnable['status'] === 200, 'Enable feature flag must return 200 for ' . $flagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI Dismiss Apply Guard Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-dismiss-apply',
        'provider_payload' => [
            'mock_models' => ['mock-dismiss-apply'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'dismiss-apply-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentList = request('GET', '/api/v1/ai/intent-settings', [], $headers);
    assertTrue($intentList['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentList['payload']['data']['items'] ?? []);
    $taskSummaryIntent = null;
    foreach ($intentItems as $item) {
        if (is_array($item) && (string)($item['intent_code'] ?? '') === 'task_summary') {
            $taskSummaryIntent = $item;
            break;
        }
    }
    assertTrue(is_array($taskSummaryIntent), 'task_summary intent setting must exist');

    $intentPatch = request('PATCH', '/api/v1/ai/intent-settings/task_summary', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-dismiss-apply',
        'feature_flag' => (string)($taskSummaryIntent['feature_flag'] ?? 'ai.task'),
        'required_permission' => (string)($taskSummaryIntent['required_permission'] ?? 'ai.use'),
        'is_enabled' => 1,
        'max_tokens' => max(1, (int)($taskSummaryIntent['max_tokens'] ?? 1200)),
    ], $headers);
    assertTrue($intentPatch['status'] === 200, 'Intent patch status must be 200');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI dismissed suggestion apply guard ' . randomSuffix(),
        'description' => 'Проверка блокировки apply-preview после dismiss.',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $summary = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [], $headers);
    assertTrue($summary['status'] === 201, 'Task summary suggestion create must return 201');
    $suggestionPublicId = (string)($summary['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');

    $dismiss = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/dismiss', [], $headers);
    assertTrue($dismiss['status'] === 200, 'Suggestion dismiss status must be 200');
    assertTrue((string)($dismiss['payload']['data']['suggestion']['status'] ?? '') === 'dismissed', 'Suggestion status must become dismissed');

    $previewAfterDismiss = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/apply-preview', [], $headers);
    assertTrue($previewAfterDismiss['status'] === 400, 'Dismissed suggestion apply-preview must return 400');
    assertTrue((string)($previewAfterDismiss['payload']['code'] ?? '') === 'AI_SUGGESTION_NOT_ACTIONABLE', 'Dismissed suggestion apply-preview code must be AI_SUGGESTION_NOT_ACTIONABLE');

    fwrite(STDOUT, "[OK] ai_dismissed_suggestion_not_applicable_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_dismissed_suggestion_not_applicable_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
