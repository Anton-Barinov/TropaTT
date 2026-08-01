<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $intentCode = 'task_summary';
    $locale = 'zz-test';

    $createV1 = request('POST', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
        'version' => 1,
        'template_text' => 'Prompt version 1 ' . randomSuffix(),
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($createV1['status'] === 201, 'Prompt v1 create must return 201');

    $createV2 = request('POST', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
        'version' => 2,
        'template_text' => 'Prompt version 2 ' . randomSuffix(),
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($createV2['status'] === 201, 'Prompt v2 create must return 201');

    $list = request('GET', '/api/v1/ai/prompt-templates', [
        'intent_code' => $intentCode,
        'locale' => $locale,
    ], $rootHeaders);
    assertTrue($list['status'] === 200, 'Prompt list status must be 200');

    $items = (array)($list['payload']['data']['items'] ?? []);
    $versions = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') !== $intentCode) {
            continue;
        }
        if ((string)($item['locale'] ?? '') !== $locale) {
            continue;
        }

        $versions[] = (int)($item['version'] ?? 0);
    }

    sort($versions);
    assertTrue(in_array(1, $versions, true), 'Prompt version 1 must exist for intent+locale');
    assertTrue(in_array(2, $versions, true), 'Prompt version 2 must exist for intent+locale');

    fwrite(STDOUT, "[OK] ai_prompt_versioning_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_prompt_versioning_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
