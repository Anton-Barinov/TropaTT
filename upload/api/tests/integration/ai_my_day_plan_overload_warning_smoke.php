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
        'title' => 'My-day overload provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-my-day-overload',
        'provider_payload' => [
            'mock_models' => ['mock-my-day-overload'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'my-day-overload-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $planDate = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $eventWindows = [
        ['09:00:00', '11:00:00'],
        ['11:15:00', '13:00:00'],
        ['13:30:00', '15:30:00'],
        ['16:00:00', '17:30:00'],
    ];
    foreach ($eventWindows as $index => $window) {
        $createEvent = request('POST', '/api/v1/calendar/events', [
            'title' => 'Overload event #' . ($index + 1) . ' ' . randomSuffix(),
            'description' => 'Overload test event',
            'starts_at' => $planDate . ' ' . $window[0],
            'ends_at' => $planDate . ' ' . $window[1],
        ], $headers);
        assertTrue($createEvent['status'] === 201, 'Calendar event create for overload setup must return 201');
    }

    for ($i = 1; $i <= 4; $i += 1) {
        $createTask = request('POST', '/api/v1/tasks', [
            'title' => 'Overload task #' . $i . ' ' . randomSuffix(),
            'description' => 'My-day overload task',
            'status' => 'new',
            'priority' => 'high',
            'due_at' => $planDate . ' 18:00:00',
        ], $headers);
        assertTrue($createTask['status'] === 201, 'Task create for overload setup must return 201');
    }

    $plan = request('POST', '/api/v1/ai/my-day/plan', [
        'date' => $planDate,
    ], $headers);
    assertTrue(in_array($plan['status'], [200, 201], true), 'My-day plan create must return 200/201');
    $suggestion = is_array($plan['payload']['data']['suggestion'] ?? null) ? (array)$plan['payload']['data']['suggestion'] : [];
    $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];

    $availableMinutes = (int)($payload['available_minutes'] ?? 0);
    $plannedMinutes = (int)($payload['planned_minutes'] ?? 0);
    $overloadWarnings = is_array($payload['overload_warnings'] ?? null) ? (array)$payload['overload_warnings'] : [];
    $suggestedDeferrals = is_array($payload['suggested_deferrals'] ?? null) ? (array)$payload['suggested_deferrals'] : [];

    assertTrue($availableMinutes > 0, 'My-day payload must include available_minutes > 0');
    assertTrue($plannedMinutes <= $availableMinutes, 'My-day planned_minutes must not exceed available_minutes');
    assertTrue($overloadWarnings !== [], 'My-day payload must include overload_warnings when overloaded');
    assertTrue($suggestedDeferrals !== [], 'My-day payload must include suggested_deferrals in overload scenario');

    $warningText = strtolower(implode(' ', array_map(static fn($item): string => (string)$item, $overloadWarnings)));
    assertTrue(
        str_contains($warningText, 'перегруз') || str_contains($warningText, 'превышает'),
        'Overload warning text must be human-readable'
    );

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_my_day_plan_overload_warning_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_my_day_plan_overload_warning_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
