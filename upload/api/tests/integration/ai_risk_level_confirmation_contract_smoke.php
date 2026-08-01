<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @return array<string,mixed>
 */
function findIntent835(array $items, string $intentCode): array
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

/**
 * @return array<string,mixed>
 */
function snapshotIntent835(array $intent): array
{
    return [
        'provider_public_id' => trim((string)($intent['provider_public_id'] ?? '')),
        'model' => (string)($intent['model'] ?? ''),
        'feature_flag' => (string)($intent['feature_flag'] ?? ''),
        'required_permission' => (string)($intent['required_permission'] ?? ''),
        'is_enabled' => (bool)($intent['is_enabled'] ?? true),
        'max_tokens' => (int)($intent['max_tokens'] ?? 0),
    ];
}

/**
 * @param array<string,mixed> $snapshot
 */
function restoreIntent835(string $intentCode, array $snapshot, array $headers): void
{
    $restore = request('PATCH', '/api/v1/ai/intent-settings/' . rawurlencode($intentCode), [
        'provider_public_id' => (string)($snapshot['provider_public_id'] ?? ''),
        'model' => (string)($snapshot['model'] ?? ''),
        'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
        'required_permission' => (string)($snapshot['required_permission'] ?? ''),
        'is_enabled' => (bool)($snapshot['is_enabled'] ?? true) ? 1 : 0,
        'max_tokens' => max(1, (int)($snapshot['max_tokens'] ?? 0) > 0 ? (int)$snapshot['max_tokens'] : 1200),
    ], $headers);
    assertTrue($restore['status'] === 200, 'Intent restore must return 200 for ' . $intentCode);
}

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Risk level contract provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-risk-level',
        'provider_payload' => [
            'mock_models' => ['mock-risk-level'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'risk-level-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $intentsResponse = request('GET', '/api/v1/ai/intent-settings', [], $headers);
    assertTrue($intentsResponse['status'] === 200, 'Intent settings list must return 200');
    $intentItems = (array)($intentsResponse['payload']['data']['items'] ?? []);

    $targetIntents = ['task_checklist', 'task_next_action', 'task_comment_draft'];
    $snapshots = [];
    foreach ($targetIntents as $intentCode) {
        $intent = findIntent835($intentItems, $intentCode);
        $snapshots[$intentCode] = snapshotIntent835($intent);
        $patch = request('PATCH', '/api/v1/ai/intent-settings/' . rawurlencode($intentCode), [
            'provider_public_id' => $providerPublicId,
            'model' => 'mock-risk-level',
            'feature_flag' => (string)($snapshots[$intentCode]['feature_flag'] ?? '') !== '' ? (string)$snapshots[$intentCode]['feature_flag'] : 'ai.task',
            'required_permission' => (string)($snapshots[$intentCode]['required_permission'] ?? '') !== '' ? (string)$snapshots[$intentCode]['required_permission'] : 'ai.use',
            'is_enabled' => 1,
            'max_tokens' => max(1, (int)($snapshots[$intentCode]['max_tokens'] ?? 0) > 0 ? (int)$snapshots[$intentCode]['max_tokens'] : 1200),
        ], $headers);
        assertTrue($patch['status'] === 200, 'Intent patch must return 200 for ' . $intentCode);
    }

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI risk level contract task ' . randomSuffix(),
        'description' => 'Проверка risk_level и high-risk confirmation contract.',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $checklistCreate = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/checklist', [], $headers);
    assertTrue($checklistCreate['status'] === 201, 'Checklist suggestion create must return 201');
    $checklistSuggestionId = (string)($checklistCreate['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($checklistSuggestionId !== '', 'Checklist suggestion public_id is required');

    $checklistPreview = request('POST', '/api/v1/ai/suggestions/' . $checklistSuggestionId . '/preview-apply', [], $headers);
    assertTrue($checklistPreview['status'] === 200, 'Checklist preview must return 200');
    $checklistChanges = (array)($checklistPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($checklistChanges) > 0, 'Checklist preview must return actions');

    $riskLevels = [];
    foreach ($checklistChanges as $change) {
        if (!is_array($change)) {
            continue;
        }
        $riskLevel = (string)($change['risk_level'] ?? '');
        assertTrue(in_array($riskLevel, ['low', 'medium', 'high'], true), 'risk_level must be low|medium|high');
        $riskLevels[$riskLevel] = true;
    }
    assertTrue(isset($riskLevels['low']) || isset($riskLevels['medium']), 'Checklist preview must contain low or medium risk actions');

    $nextActionCreate = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/next-action', [], $headers);
    assertTrue($nextActionCreate['status'] === 201, 'Next-action suggestion create must return 201');
    $nextActionSuggestionId = (string)($nextActionCreate['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($nextActionSuggestionId !== '', 'Next-action suggestion public_id is required');

    $nextActionPreview = request('POST', '/api/v1/ai/suggestions/' . $nextActionSuggestionId . '/preview-apply', [], $headers);
    assertTrue($nextActionPreview['status'] === 200, 'Next-action preview must return 200');
    $nextActionChanges = (array)($nextActionPreview['payload']['data']['preview']['changes'] ?? []);
    assertTrue(count($nextActionChanges) > 0, 'Next-action preview must return actions');

    $hasHighRisk = false;
    foreach ($nextActionChanges as $change) {
        if (!is_array($change)) {
            continue;
        }
        if ((string)($change['risk_level'] ?? '') === 'high') {
            $hasHighRisk = true;
            break;
        }
    }
    assertTrue($hasHighRisk, 'Next-action preview must expose high-risk action');

    $taskJsPath = dirname(__DIR__, 3) . '/web/assets/js/br1.js';
    $taskJs = file_get_contents($taskJsPath);
    assertTrue(is_string($taskJs), 'Unable to read task web runtime source');
    assertTrue(str_contains($taskJs, "action && action.high_risk"), 'Web apply flow must detect high-risk actions');
    assertTrue(str_contains($taskJs, 'Вы выбрали действие с повышенным риском. Продолжить применение?'), 'Web apply flow must require separate confirmation for high-risk actions');

    foreach ($targetIntents as $intentCode) {
        restoreIntent835($intentCode, (array)$snapshots[$intentCode], $headers);
    }
    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_risk_level_confirmation_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_risk_level_confirmation_contract_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

