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

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    assertTrue(count($intentItems) > 0, 'Intent settings must not be empty');

    $schemas = request('GET', '/api/v1/ai/json-schemas', [], $rootHeaders);
    assertTrue($schemas['status'] === 200, 'JSON schemas list status must be 200');
    $schemaItems = (array)($schemas['payload']['data']['items'] ?? []);

    $activeByIntent = [];
    foreach ($schemaItems as $schema) {
        if (!is_array($schema)) {
            continue;
        }
        if (!(bool)($schema['is_active'] ?? false)) {
            continue;
        }

        $intentCode = (string)($schema['intent_code'] ?? '');
        if ($intentCode === '') {
            continue;
        }

        $activeByIntent[$intentCode] = true;
    }

    $missing = [];
    foreach ($intentItems as $intent) {
        if (!is_array($intent)) {
            continue;
        }
        $intentCode = (string)($intent['intent_code'] ?? '');
        if ($intentCode === '') {
            continue;
        }

        if (!isset($activeByIntent[$intentCode])) {
            $missing[] = $intentCode;
        }
    }

    assertTrue($missing === [], 'Each intent must have active json schema. Missing: ' . implode(', ', $missing));

    $requiredIntentCodes = [
        'daily_work_plan',
        'task_decomposition',
        'project_risk_summary',
        'security_log_review',
        'semantic_search',
    ];

    foreach ($requiredIntentCodes as $intentCode) {
        $intent = findIntent($intentItems, $intentCode);
        assertTrue(is_array($intent), 'Intent must exist in settings for schema coverage: ' . $intentCode);
        assertTrue(isset($activeByIntent[$intentCode]), 'Intent must have active schema: ' . $intentCode);
    }

    fwrite(STDOUT, "[OK] ai_intent_schema_coverage_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_intent_schema_coverage_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
