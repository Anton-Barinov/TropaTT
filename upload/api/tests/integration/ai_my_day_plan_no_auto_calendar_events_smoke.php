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
        'title' => 'My-day no auto event provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-my-day-no-auto-event',
        'provider_payload' => [
            'mock_models' => ['mock-my-day-no-auto-event'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'my-day-no-auto-event-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $eventsBefore = request('GET', '/api/v1/calendar/events', ['limit' => 200], $headers);
    assertTrue($eventsBefore['status'] === 200, 'Calendar events list before plan must return 200');
    $beforeItems = is_array($eventsBefore['payload']['data']['items'] ?? null) ? (array)$eventsBefore['payload']['data']['items'] : [];
    $beforeCount = count($beforeItems);

    $plan = request('POST', '/api/v1/ai/my-day/plan', [], $headers);
    assertTrue(in_array($plan['status'], [200, 201], true), 'My-day plan create must return 200/201');
    $suggestion = is_array($plan['payload']['data']['suggestion'] ?? null) ? (array)$plan['payload']['data']['suggestion'] : [];
    assertTrue(trim((string)($suggestion['public_id'] ?? '')) !== '', 'My-day plan must return suggestion public_id');

    $eventsAfter = request('GET', '/api/v1/calendar/events', ['limit' => 200], $headers);
    assertTrue($eventsAfter['status'] === 200, 'Calendar events list after plan must return 200');
    $afterItems = is_array($eventsAfter['payload']['data']['items'] ?? null) ? (array)$eventsAfter['payload']['data']['items'] : [];
    $afterCount = count($afterItems);

    assertTrue($afterCount === $beforeCount, 'My-day plan must not auto-create calendar events');

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_my_day_plan_no_auto_calendar_events_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_my_day_plan_no_auto_calendar_events_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
