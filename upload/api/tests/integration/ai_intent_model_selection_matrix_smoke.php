<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>|null
 */
function findIntent(array $items, string $intentCode): ?array
{
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === $intentCode) {
            return $item;
        }
    }

    return null;
}

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $targets = [
        'daily_work_plan',
        'task_decomposition',
        'project_risk_summary',
        'security_log_review',
        'semantic_search',
    ];

    $before = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($before['status'] === 200, 'Intent settings list status must be 200');
    $beforeItems = (array)($before['payload']['data']['items'] ?? []);

    $original = [];
    foreach ($targets as $intentCode) {
        $item = findIntent($beforeItems, $intentCode);
        assertTrue(is_array($item), 'Intent setting must exist for: ' . $intentCode);
        $original[$intentCode] = [
            'model' => (string)($item['model'] ?? ''),
            'feature_flag' => (string)($item['feature_flag'] ?? ''),
            'is_enabled' => (bool)($item['is_enabled'] ?? true),
            'max_tokens' => (int)($item['max_tokens'] ?? 2000),
        ];
    }

    foreach ($targets as $intentCode) {
        $model = 'model-' . $intentCode . '-' . randomSuffix();
        $snapshot = (array)($original[$intentCode] ?? []);
        $patch = request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'model' => $model,
            'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
            'is_enabled' => ((bool)($snapshot['is_enabled'] ?? true)) ? 1 : 0,
            'max_tokens' => max(1, (int)($snapshot['max_tokens'] ?? 2000)),
        ], $rootHeaders);
        assertTrue($patch['status'] === 200, 'Intent model patch must be 200 for: ' . $intentCode);

        $patchedModel = (string)($patch['payload']['data']['item']['model'] ?? '');
        assertTrue($patchedModel === $model, 'Patched model must match for: ' . $intentCode);
    }

    $after = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($after['status'] === 200, 'Intent settings list after patch must be 200');
    $afterItems = (array)($after['payload']['data']['items'] ?? []);

    foreach ($targets as $intentCode) {
        $item = findIntent($afterItems, $intentCode);
        assertTrue(is_array($item), 'Intent must remain present after patch: ' . $intentCode);
        $actual = (string)($item['model'] ?? '');
        assertTrue(str_contains($actual, 'model-' . $intentCode . '-'), 'Intent model must be independently configurable for: ' . $intentCode);
    }

    foreach ($targets as $intentCode) {
        $snapshot = (array)($original[$intentCode] ?? []);
        request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'model' => (string)($snapshot['model'] ?? ''),
            'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
            'is_enabled' => ((bool)($snapshot['is_enabled'] ?? true)) ? 1 : 0,
            'max_tokens' => max(1, (int)($snapshot['max_tokens'] ?? 2000)),
        ], $rootHeaders);
    }

    fwrite(STDOUT, "[OK] ai_intent_model_selection_matrix_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_intent_model_selection_matrix_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
