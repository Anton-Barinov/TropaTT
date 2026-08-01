<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);
    assertTrue(count($intentItems) > 0, 'Intent settings must not be empty');

    $prompts = request('GET', '/api/v1/ai/prompt-templates', [], $rootHeaders);
    assertTrue($prompts['status'] === 200, 'Prompt templates list status must be 200');
    $promptItems = (array)($prompts['payload']['data']['items'] ?? []);

    $activePromptByIntent = [];
    foreach ($promptItems as $prompt) {
        if (!is_array($prompt)) {
            continue;
        }
        if (!(bool)($prompt['is_active'] ?? false)) {
            continue;
        }

        $intentCode = (string)($prompt['intent_code'] ?? '');
        $locale = strtolower(trim((string)($prompt['locale'] ?? '')));
        if ($intentCode === '' || $locale !== 'ru-ru') {
            continue;
        }

        $activePromptByIntent[$intentCode] = true;
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

        if (!isset($activePromptByIntent[$intentCode])) {
            $missing[] = $intentCode;
        }
    }

    assertTrue($missing === [], 'Each intent must have active prompt template. Missing: ' . implode(', ', $missing));

    $requiredIntentCodes = [
        'daily_work_plan',
        'task_decomposition',
        'project_risk_summary',
        'security_log_review',
        'semantic_search',
    ];

    foreach ($requiredIntentCodes as $intentCode) {
        assertTrue(isset($activePromptByIntent[$intentCode]), 'Intent must have active prompt: ' . $intentCode);
    }

    fwrite(STDOUT, "[OK] ai_intent_prompt_coverage_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_intent_prompt_coverage_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
