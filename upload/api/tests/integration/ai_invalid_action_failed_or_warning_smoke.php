<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI invalid action lifecycle task ' . randomSuffix(),
        'description' => 'Проверка перевода suggestion в failed при невалидных action.',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $summaryCreate = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [], $headers);
    assertTrue($summaryCreate['status'] === 201, 'Summary suggestion create must return 201');
    $suggestionPublicId = (string)($summaryCreate['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');

    $markFailed = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/confirm', [
        'decision' => 'failed',
        'apply_target' => 'invalid_action',
        'apply_target_public_id' => $taskPublicId,
        'warnings' => ['UNSUPPORTED_ACTION_TYPE', 'INVALID_PAYLOAD_SCHEMA'],
    ], $headers);
    assertTrue($markFailed['status'] === 200, 'Suggestion confirm failed status must return 200');
    assertTrue((string)($markFailed['payload']['data']['suggestion']['status'] ?? '') === 'failed', 'Suggestion status must become failed');

    $suggestionGet = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $headers);
    assertTrue($suggestionGet['status'] === 200, 'Suggestion detail must return 200');
    assertTrue((string)($suggestionGet['payload']['data']['suggestion']['status'] ?? '') === 'failed', 'Suggestion detail must persist failed status');

    $taskJsPath = dirname(__DIR__, 3) . '/web/assets/js/br1.js';
    $taskJs = file_get_contents($taskJsPath);
    assertTrue(is_string($taskJs), 'Unable to read web task runtime source');
    assertTrue(str_contains($taskJs, 'applyResult.invalidCount > 0'), 'Web apply flow must detect invalid action count');
    assertTrue(str_contains($taskJs, "decision: 'failed'"), 'Web apply flow must mark suggestion as failed when invalid action is detected');

    fwrite(STDOUT, "[OK] ai_invalid_action_failed_or_warning_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_invalid_action_failed_or_warning_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

