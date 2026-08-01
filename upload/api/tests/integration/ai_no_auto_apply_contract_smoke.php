<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findFlagByCodeOrFail(array $items, string $code): array
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

/**
 * @param list<array<string,mixed>> $items
 * @return array<string,mixed>
 */
function findIntentByCodeOrFail(array $items, string $intentCode): array
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

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsResponse = request('GET', '/api/v1/feature-flags', [], $rootHeaders);
    assertTrue($flagsResponse['status'] === 200, 'Feature flags list status must be 200');
    $flagItems = (array)($flagsResponse['payload']['data']['items'] ?? []);

    $flagCodes = ['ai.enabled', 'ai.task'];
    $flagSnapshots = [];
    foreach ($flagCodes as $flagCode) {
        $flag = findFlagByCodeOrFail($flagItems, $flagCode);
        $flagPublicId = (string)($flag['public_id'] ?? '');
        assertTrue($flagPublicId !== '', 'Feature flag public_id is required for ' . $flagCode);
        $flagSnapshots[$flagCode] = [
            'public_id' => $flagPublicId,
            'is_enabled' => (bool)($flag['is_enabled'] ?? false),
        ];
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $rootHeaders);
        assertTrue($enable['status'] === 200, 'Enable feature flag must be 200 for ' . $flagCode);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'AI No Auto Apply Provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-no-auto-apply',
        'provider_payload' => [
            'mock_models' => ['mock-no-auto-apply'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $rootHeaders);
    assertTrue($providerCreate['status'] === 201, 'Provider create status must be 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'no-auto-apply-secret-' . randomSuffix(),
    ], $rootHeaders);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set status must be 200');

    $intentSettings = request('GET', '/api/v1/ai/intent-settings', [], $rootHeaders);
    assertTrue($intentSettings['status'] === 200, 'Intent settings list status must be 200');
    $intentItems = (array)($intentSettings['payload']['data']['items'] ?? []);

    $intentCodes = ['task_decomposition', 'task_checklist', 'task_comment_draft'];
    $intentSnapshots = [];
    foreach ($intentCodes as $intentCode) {
        $intent = findIntentByCodeOrFail($intentItems, $intentCode);
        $intentSnapshots[$intentCode] = [
            'provider_public_id' => trim((string)($intent['provider_public_id'] ?? '')),
            'model' => (string)($intent['model'] ?? ''),
            'feature_flag' => (string)($intent['feature_flag'] ?? ''),
            'required_permission' => (string)($intent['required_permission'] ?? ''),
            'is_enabled' => (bool)($intent['is_enabled'] ?? true),
            'max_tokens' => (int)($intent['max_tokens'] ?? 0),
        ];

        $patch = request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'provider_public_id' => $providerPublicId,
            'model' => 'mock-no-auto-apply',
            'feature_flag' => 'ai.task',
            'required_permission' => $intentSnapshots[$intentCode]['required_permission'],
            'is_enabled' => 1,
            'max_tokens' => max(1, $intentSnapshots[$intentCode]['max_tokens'] > 0 ? $intentSnapshots[$intentCode]['max_tokens'] : 1200),
        ], $rootHeaders);
        assertTrue($patch['status'] === 200, 'Intent patch status must be 200 for ' . $intentCode);
    }

    $taskCreate = request('POST', '/api/v1/tasks', [
        'title' => 'AI No Auto Apply Task ' . randomSuffix(),
        'description' => 'No auto apply contract test',
    ], $rootHeaders);
    assertTrue($taskCreate['status'] === 201, 'Task create status must be 201');
    $taskPublicId = (string)($taskCreate['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskPublicId !== '', 'Task public_id is required');

    $subtasksBefore = request('GET', '/api/v1/tasks/' . $taskPublicId . '/subtasks', [], $rootHeaders);
    assertTrue($subtasksBefore['status'] === 200, 'Subtasks list before AI must be 200');
    $subtasksBeforeCount = count((array)($subtasksBefore['payload']['data']['items'] ?? []));

    $checklistsBefore = request('GET', '/api/v1/tasks/' . $taskPublicId . '/checklists', [], $rootHeaders);
    assertTrue($checklistsBefore['status'] === 200, 'Checklists list before AI must be 200');
    $checklistsBeforeCount = count((array)($checklistsBefore['payload']['data']['items'] ?? []));

    $commentDraftBefore = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comment-draft', [], $rootHeaders);
    assertTrue(in_array($commentDraftBefore['status'], [200, 404], true), 'Comment draft before AI must be 200/404');
    $commentDraftBeforeBody = trim((string)($commentDraftBefore['payload']['data']['draft']['body'] ?? ''));
    assertTrue($commentDraftBefore['status'] === 404 || $commentDraftBeforeBody === '', 'Comment draft must be absent before AI apply');

    $decompose = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/decompose', [], $rootHeaders);
    assertTrue($decompose['status'] === 201, 'Task decompose AI create must be 201');
    $decomposeSuggestionPublicId = (string)($decompose['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($decomposeSuggestionPublicId !== '', 'Task decompose suggestion public_id is required');

    $checklist = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/checklist', [], $rootHeaders);
    assertTrue($checklist['status'] === 201, 'Task checklist AI create must be 201');
    $checklistSuggestionPublicId = (string)($checklist['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($checklistSuggestionPublicId !== '', 'Task checklist suggestion public_id is required');

    $commentDraft = request('POST', '/api/v1/ai/tasks/' . $taskPublicId . '/comment-draft', [], $rootHeaders);
    assertTrue($commentDraft['status'] === 201, 'Task comment-draft AI create must be 201');
    $commentDraftSuggestionPublicId = (string)($commentDraft['payload']['data']['suggestion']['public_id'] ?? '');
    assertTrue($commentDraftSuggestionPublicId !== '', 'Task comment-draft suggestion public_id is required');

    $decomposePreview = request('POST', '/api/v1/ai/suggestions/' . $decomposeSuggestionPublicId . '/preview-apply', [], $rootHeaders);
    assertTrue($decomposePreview['status'] === 200, 'Task decompose preview must be 200');
    $checklistPreview = request('POST', '/api/v1/ai/suggestions/' . $checklistSuggestionPublicId . '/preview-apply', [], $rootHeaders);
    assertTrue($checklistPreview['status'] === 200, 'Task checklist preview must be 200');
    $commentDraftPreview = request('POST', '/api/v1/ai/suggestions/' . $commentDraftSuggestionPublicId . '/preview-apply', [], $rootHeaders);
    assertTrue($commentDraftPreview['status'] === 200, 'Task comment-draft preview must be 200');

    $confirmDecompose = request('POST', '/api/v1/ai/suggestions/' . $decomposeSuggestionPublicId . '/confirm', [
        'decision' => 'applied',
    ], $rootHeaders);
    assertTrue($confirmDecompose['status'] === 200, 'Task decompose confirm(applied) must be 200');

    $confirmChecklist = request('POST', '/api/v1/ai/suggestions/' . $checklistSuggestionPublicId . '/confirm', [
        'decision' => 'applied',
    ], $rootHeaders);
    assertTrue($confirmChecklist['status'] === 200, 'Task checklist confirm(applied) must be 200');

    $confirmCommentDraft = request('POST', '/api/v1/ai/suggestions/' . $commentDraftSuggestionPublicId . '/confirm', [
        'decision' => 'applied',
    ], $rootHeaders);
    assertTrue($confirmCommentDraft['status'] === 200, 'Task comment-draft confirm(applied) must be 200');

    $subtasksAfter = request('GET', '/api/v1/tasks/' . $taskPublicId . '/subtasks', [], $rootHeaders);
    assertTrue($subtasksAfter['status'] === 200, 'Subtasks list after AI must be 200');
    $subtasksAfterCount = count((array)($subtasksAfter['payload']['data']['items'] ?? []));
    assertTrue($subtasksAfterCount === $subtasksBeforeCount, 'AI generate/preview/confirm must not auto-create subtasks');

    $checklistsAfter = request('GET', '/api/v1/tasks/' . $taskPublicId . '/checklists', [], $rootHeaders);
    assertTrue($checklistsAfter['status'] === 200, 'Checklists list after AI must be 200');
    $checklistsAfterCount = count((array)($checklistsAfter['payload']['data']['items'] ?? []));
    assertTrue($checklistsAfterCount === $checklistsBeforeCount, 'AI generate/preview/confirm must not auto-create checklists');

    $commentDraftAfter = request('GET', '/api/v1/tasks/' . $taskPublicId . '/comment-draft', [], $rootHeaders);
    assertTrue(in_array($commentDraftAfter['status'], [200, 404], true), 'Comment draft after AI must be 200/404');
    $commentDraftAfterBody = trim((string)($commentDraftAfter['payload']['data']['draft']['body'] ?? ''));
    assertTrue($commentDraftAfter['status'] === 404 || $commentDraftAfterBody === '', 'AI generate/preview/confirm must not auto-save comment draft');

    foreach ($intentCodes as $intentCode) {
        $snapshot = $intentSnapshots[$intentCode] ?? null;
        if (!is_array($snapshot)) {
            continue;
        }
        request('PATCH', '/api/v1/ai/intent-settings/' . $intentCode, [
            'provider_public_id' => (string)($snapshot['provider_public_id'] ?? ''),
            'model' => (string)($snapshot['model'] ?? ''),
            'feature_flag' => (string)($snapshot['feature_flag'] ?? ''),
            'required_permission' => (string)($snapshot['required_permission'] ?? ''),
            'is_enabled' => (bool)($snapshot['is_enabled'] ?? true) ? 1 : 0,
            'max_tokens' => max(1, (int)($snapshot['max_tokens'] ?? 0) > 0 ? (int)$snapshot['max_tokens'] : 1200),
        ], $rootHeaders);
    }

    foreach ($flagCodes as $flagCode) {
        $snapshot = $flagSnapshots[$flagCode] ?? null;
        if (!is_array($snapshot)) {
            continue;
        }
        $flagPublicId = (string)($snapshot['public_id'] ?? '');
        if ($flagPublicId === '') {
            continue;
        }
        request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => (bool)($snapshot['is_enabled'] ?? false) ? 1 : 0], $rootHeaders);
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $rootHeaders);

    fwrite(STDOUT, "[OK] ai_no_auto_apply_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_no_auto_apply_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}

