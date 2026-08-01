<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI partial apply lifecycle task ' . randomSuffix(),
        'description' => 'Проверка partially_applied статуса и metadata по действиям.',
    ], $headers);
    assertTrue($taskCreate['status'] === 201, 'Task create must return 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $summaryCreate = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/summary', [], $headers);
    assertTrue($summaryCreate['status'] === 201, 'Summary suggestion create must return 201');
    $suggestionPublicId = (string)($summaryCreate['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');

    $confirmPartial = request('POST', '/api/v1/ai/suggestions/' . $suggestionPublicId . '/confirm', [
        'decision' => 'partially_applied',
        'apply_target' => 'multiple',
        'apply_target_public_id' => $taskPublicId,
        'applied_action_types' => ['create_comment_draft'],
        'skipped_action_types' => ['create_subtask', 'create_checklist_item'],
        'warnings' => ['PARTIAL_APPLY_SKIPPED_ACTIONS'],
    ], $headers);
    assertTrue($confirmPartial['status'] === 200, 'Suggestion confirm partially_applied must return 200');
    assertTrue((string)($confirmPartial['payload']['data']['suggestion']['status'] ?? '') === 'partially_applied', 'Suggestion status must become partially_applied');

    $suggestionGet = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $headers);
    assertTrue($suggestionGet['status'] === 200, 'Suggestion detail must return 200');
    assertTrue((string)($suggestionGet['payload']['data']['suggestion']['status'] ?? '') === 'partially_applied', 'Suggestion detail must persist partially_applied status');

    $taskJsPath = dirname(__DIR__, 3) . '/web/assets/js/br1.js';
    $taskJs = file_get_contents($taskJsPath);
    assertTrue(is_string($taskJs), 'Unable to read web task runtime source');
    assertTrue(str_contains($taskJs, "var confirmDecision = isPartiallyApplied ? 'partially_applied' : 'applied';"), 'Web apply flow must choose partially_applied decision for partial apply result');
    assertTrue(str_contains($taskJs, 'applied_action_types'), 'Web apply flow must send applied action types metadata');
    assertTrue(str_contains($taskJs, 'skipped_action_types'), 'Web apply flow must send skipped action types metadata');
    assertTrue(str_contains($taskJs, 'var totalPreviewActions = Array.isArray(latestPreview && latestPreview.changes)'), 'Web apply flow must compare applied actions against full preview action count');

    fwrite(STDOUT, "[OK] ai_partial_apply_status_metadata_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_partial_apply_status_metadata_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

