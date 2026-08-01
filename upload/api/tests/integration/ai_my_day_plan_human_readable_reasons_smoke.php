<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $headers);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $flagsList = request('GET', '/api/v1/feature-flags', ['limit' => 200], $headers);
    assertTrue($flagsList['status'] === 200, 'Feature flags list status must be 200');
    $flags = is_array($flagsList['payload']['data']['items'] ?? null) ? (array)$flagsList['payload']['data']['items'] : [];
    $flagsByCode = [];
    foreach ($flags as $flag) {
        if (!is_array($flag)) {
            continue;
        }
        $code = trim((string)($flag['code'] ?? ''));
        $publicId = trim((string)($flag['public_id'] ?? ''));
        if ($code !== '' && $publicId !== '') {
            $flagsByCode[$code] = $publicId;
        }
    }
    foreach (['ai.enabled', 'ai.calendar', 'ai.cron.daily_work_plan'] as $requiredFlag) {
        $flagPublicId = (string)($flagsByCode[$requiredFlag] ?? '');
        assertTrue($flagPublicId !== '', 'Missing feature flag for code: ' . $requiredFlag);
        $enable = request('PATCH', '/api/v1/feature-flags/' . $flagPublicId, ['is_enabled' => 1], $headers);
        assertTrue($enable['status'] === 200, 'Enable feature flag must return 200 for code: ' . $requiredFlag);
    }

    $providerCreate = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'mock',
        'title' => 'My-day reasons provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-my-day-reasons',
        'provider_payload' => [
            'mock_models' => ['mock-my-day-reasons'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'my-day-reasons-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $planDate = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
    $createTask = request('POST', '/api/v1/tasks', [
        'title' => 'Reasonable my-day task ' . randomSuffix(),
        'description' => 'Task for my-day reason verification',
        'status' => 'new',
        'priority' => 'high',
        'due_at' => $planDate . ' 18:00:00',
    ], $headers);
    assertTrue($createTask['status'] === 201, 'Task create for reasons setup must return 201');

    $plan = request('POST', '/api/v1/ai/my-day/plan', [
        'date' => $planDate,
        'regenerate' => 1,
    ], $headers);
    assertTrue(in_array($plan['status'], [200, 201], true), 'My-day plan create must return 200/201');
    $suggestion = is_array($plan['payload']['data']['suggestion'] ?? null) ? (array)$plan['payload']['data']['suggestion'] : [];
    $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
    $tasks = is_array($payload['suggested_tasks'] ?? null) ? (array)$payload['suggested_tasks'] : [];
    assertTrue($tasks !== [], 'My-day plan must include suggested_tasks');

    foreach ($tasks as $task) {
        if (!is_array($task)) {
            continue;
        }
        $reason = trim((string)($task['reason'] ?? ''));
        assertTrue($reason !== '', 'Each suggested task must include non-empty human-readable reason');
        assertTrue(mb_strlen($reason) >= 6, 'Reason text must be human-readable and sufficiently descriptive');
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_my_day_plan_human_readable_reasons_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_my_day_plan_human_readable_reasons_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
