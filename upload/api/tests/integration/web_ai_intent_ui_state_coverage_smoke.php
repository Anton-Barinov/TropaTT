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

    $intentCodes = [];
    foreach ($intentItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $intentCode = trim((string)($item['intent_code'] ?? ''));
        if ($intentCode !== '') {
            $intentCodes[$intentCode] = true;
        }
    }
    $intentCodes = array_keys($intentCodes);
    sort($intentCodes);
    assertTrue(count($intentCodes) > 0, 'Intent settings must provide at least one intent code');

    $webAiJsPath = __DIR__ . '/../../../web/assets/js/ai.js';
    assertTrue(is_file($webAiJsPath), 'web/assets/js/ai.js must exist');
    $content = file_get_contents($webAiJsPath);
    assertTrue(is_string($content) && $content !== '', 'web/assets/js/ai.js must be readable');

    $missing = [];
    $invalid = [];

    foreach ($intentCodes as $intentCode) {
        $quoted = preg_quote("'" . $intentCode . "'", '/');
        $pattern = '/' . $quoted . '\\s*:\\s*\\{([\\s\\S]*?)\\}/';
        if (!preg_match($pattern, $content, $match)) {
            $missing[] = $intentCode;
            continue;
        }

        $block = (string)($match[1] ?? '');
        $hasEmpty = preg_match('/empty\\s*:\\s*[\'"][^\'"]+[\'"]/u', $block) === 1;
        $hasError = preg_match('/error\\s*:\\s*[\'"][^\'"]+[\'"]/u', $block) === 1;

        if (!$hasEmpty || !$hasError) {
            $invalid[] = $intentCode;
        }
    }

    assertTrue($missing === [], 'Each intent must have explicit UI state block in web/assets/js/ai.js. Missing: ' . implode(', ', $missing));
    assertTrue($invalid === [], 'Each intent UI state block must contain non-empty empty/error copy. Invalid: ' . implode(', ', $invalid));

    assertTrue(str_contains($content, 'function getIntentUiStateCopy(intentCode)'), 'AI web runtime must export getIntentUiStateCopy helper');

    fwrite(STDOUT, "[OK] web_ai_intent_ui_state_coverage_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_ai_intent_ui_state_coverage_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
