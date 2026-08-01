<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

/**
 * @param mixed $value
 */
function assertDateTimeFieldsIso8601(mixed $value, string $path = 'payload'): void
{
    if (!is_array($value)) {
        return;
    }

    foreach ($value as $key => $item) {
        $keyString = is_string($key) ? $key : (string)$key;
        $childPath = $path . '.' . $keyString;

        if (is_array($item)) {
            assertDateTimeFieldsIso8601($item, $childPath);
            continue;
        }

        if (!is_string($item)) {
            continue;
        }

        $normalizedKey = strtolower($keyString);
        $isDateTimeKey = in_array($normalizedKey, [
            'due_at',
            'starts_at',
            'ends_at',
            'start_at',
            'end_at',
            'created_at',
            'updated_at',
            'expires_at',
            'scheduled_for_utc',
        ], true) || str_ends_with($normalizedKey, '_at');
        $isDateKey = $normalizedKey === 'date';
        if (!$isDateTimeKey && !$isDateKey) {
            continue;
        }

        $trimmed = trim($item);
        if ($trimmed === '') {
            continue;
        }

        if ($isDateKey) {
            assertTrue((bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed), 'Date field must use ISO-8601 date format (YYYY-MM-DD): ' . $childPath . ' -> ' . $trimmed);
            continue;
        }

        $isIsoDateTime = (bool)preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+\-]\d{2}:\d{2})$/', $trimmed);
        assertTrue($isIsoDateTime, 'Datetime field must be ISO-8601: ' . $childPath . ' -> ' . $trimmed);
    }
}

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'Datetime ISO contract provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-datetime-iso',
        'provider_payload' => [
            'mock_models' => ['mock-datetime-iso'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'datetime-iso-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $intents = request('GET', '/api/v1/ai/intent-settings', [], $headers);
    assertTrue($intents['status'] === 200, 'Intent settings list must return 200');
    $items = (array)($intents['payload']['data']['items'] ?? []);
    $taskListIntent = null;
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        if ((string)($item['intent_code'] ?? '') === 'task_list_priority') {
            $taskListIntent = $item;
            break;
        }
    }
    assertTrue(is_array($taskListIntent), 'task_list_priority intent must exist');
    $intentSnapshot = [
        'provider_public_id' => trim((string)($taskListIntent['provider_public_id'] ?? '')),
        'model' => (string)($taskListIntent['model'] ?? ''),
        'feature_flag' => (string)($taskListIntent['feature_flag'] ?? ''),
        'required_permission' => (string)($taskListIntent['required_permission'] ?? ''),
        'is_enabled' => (bool)($taskListIntent['is_enabled'] ?? true),
        'max_tokens' => (int)($taskListIntent['max_tokens'] ?? 0),
    ];

    $intentPatch = request('PATCH', '/api/v1/ai/intent-settings/task_list_priority', [
        'provider_public_id' => $providerPublicId,
        'model' => 'mock-datetime-iso',
        'feature_flag' => $intentSnapshot['feature_flag'] !== '' ? $intentSnapshot['feature_flag'] : 'ai.task',
        'required_permission' => $intentSnapshot['required_permission'] !== '' ? $intentSnapshot['required_permission'] : 'ai.use',
        'is_enabled' => 1,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $headers);
    assertTrue($intentPatch['status'] === 200, 'Intent patch must return 200');

    $taskOne = request('POST', '/api/v1/tasks', [
        'title' => 'Datetime ISO task #1 ' . randomSuffix(),
        'due_at' => '2026-05-02 12:30:00',
    ], $headers);
    assertTrue($taskOne['status'] === 201, 'Task #1 create must return 201');
    $taskOneId = (string)($taskOne['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskOneId !== '', 'Task #1 public_id is required');

    $taskTwo = request('POST', '/api/v1/tasks', [
        'title' => 'Datetime ISO task #2 ' . randomSuffix(),
        'due_at' => '2026-05-03 09:15:00',
    ], $headers);
    assertTrue($taskTwo['status'] === 201, 'Task #2 create must return 201');
    $taskTwoId = (string)($taskTwo['payload']['data']['task']['public_id'] ?? '');
    assertTrue($taskTwoId !== '', 'Task #2 public_id is required');

    $prioritySuggestion = request('POST', '/api/v1/ai/tasks/priority', [
        'task_public_ids' => [$taskOneId, $taskTwoId],
        'view_mode' => 'list',
    ], $headers);
    assertTrue($prioritySuggestion['status'] === 201, 'Task priority suggestion create must return 201');
    $suggestion = (array)($prioritySuggestion['payload']['data']['suggestion'] ?? []);
    $suggestionPublicId = (string)($suggestion['public_id'] ?? '');
    assertTrue($suggestionPublicId !== '', 'Suggestion public_id is required');
    $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
    assertTrue($payload !== [], 'Suggestion payload is required');
    assertDateTimeFieldsIso8601($payload, 'suggestion.payload');

    $detail = request('GET', '/api/v1/ai/suggestions/' . $suggestionPublicId, [], $headers);
    assertTrue($detail['status'] === 200, 'Suggestion detail must return 200');
    $detailPayload = (array)($detail['payload']['data']['suggestion']['payload'] ?? []);
    assertDateTimeFieldsIso8601($detailPayload, 'suggestion.detail.payload');

    $restoreIntent = request('PATCH', '/api/v1/ai/intent-settings/task_list_priority', [
        'provider_public_id' => $intentSnapshot['provider_public_id'],
        'model' => $intentSnapshot['model'],
        'feature_flag' => $intentSnapshot['feature_flag'],
        'required_permission' => $intentSnapshot['required_permission'],
        'is_enabled' => $intentSnapshot['is_enabled'] ? 1 : 0,
        'max_tokens' => max(1, $intentSnapshot['max_tokens'] > 0 ? $intentSnapshot['max_tokens'] : 1200),
    ], $headers);
    assertTrue($restoreIntent['status'] === 200, 'Intent restore patch must return 200');
    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_datetime_iso8601_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_datetime_iso8601_contract_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

