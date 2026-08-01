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
        'title' => 'My-day respects slots provider ' . randomSuffix(),
        'base_url' => 'https://example.com',
        'api_path' => '/v1/chat/completions',
        'default_model' => 'mock-my-day-respects-slots',
        'provider_payload' => [
            'mock_models' => ['mock-my-day-respects-slots'],
        ],
        'is_default' => 0,
        'is_active' => 1,
    ], $headers);
    assertTrue($providerCreate['status'] === 201, 'Provider create must return 201');
    $providerPublicId = (string)($providerCreate['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Provider public_id is required');

    $providerSecret = request('PUT', '/api/v1/ai/providers/' . $providerPublicId . '/secret', [
        'secret' => 'my-day-respects-slots-secret-' . randomSuffix(),
    ], $headers);
    assertTrue($providerSecret['status'] === 200, 'Provider secret set must return 200');

    $planDate = (new DateTimeImmutable('+14 days'))->format('Y-m-d');
    $busyWindows = [
        ['09:00:00', '10:00:00'],
        ['11:00:00', '12:00:00'],
        ['14:00:00', '15:00:00'],
    ];
    foreach ($busyWindows as $index => $window) {
        $createEvent = request('POST', '/api/v1/calendar/events', [
            'title' => 'Busy window #' . ($index + 1) . ' ' . randomSuffix(),
            'starts_at' => $planDate . ' ' . $window[0],
            'ends_at' => $planDate . ' ' . $window[1],
        ], $headers);
        assertTrue($createEvent['status'] === 201, 'Calendar event create must return 201');
    }

    for ($i = 1; $i <= 4; $i += 1) {
        $createTask = request('POST', '/api/v1/tasks', [
            'title' => 'Slots-aware task #' . $i . ' ' . randomSuffix(),
            'description' => 'My-day slots-aware task',
            'status' => 'new',
            'priority' => 'high',
            'due_at' => $planDate . ' 18:00:00',
        ], $headers);
        assertTrue($createTask['status'] === 201, 'Task create for slots setup must return 201');
    }

    $plan = request('POST', '/api/v1/ai/my-day/plan', [
        'date' => $planDate,
    ], $headers);
    assertTrue(in_array($plan['status'], [200, 201], true), 'My-day plan create must return 200/201');
    $suggestion = is_array($plan['payload']['data']['suggestion'] ?? null) ? (array)$plan['payload']['data']['suggestion'] : [];
    $payload = is_array($suggestion['payload'] ?? null) ? (array)$suggestion['payload'] : [];
    $calendarSlots = is_array($payload['calendar_slots'] ?? null) ? (array)$payload['calendar_slots'] : [];
    assertTrue($calendarSlots !== [], 'My-day plan must include calendar_slots');

    $overlaps = static function (string $aStart, string $aEnd, string $bStart, string $bEnd): bool {
        $aStartTs = strtotime($aStart);
        $aEndTs = strtotime($aEnd);
        $bStartTs = strtotime($bStart);
        $bEndTs = strtotime($bEnd);
        if (!is_int($aStartTs) || !is_int($aEndTs) || !is_int($bStartTs) || !is_int($bEndTs)) {
            return false;
        }
        return $aStartTs < $bEndTs && $bStartTs < $aEndTs;
    };

    foreach ($calendarSlots as $slot) {
        if (!is_array($slot)) {
            continue;
        }
        $slotStart = trim((string)($slot['start_at'] ?? ''));
        $slotEnd = trim((string)($slot['end_at'] ?? ''));
        if ($slotStart === '' || $slotEnd === '') {
            continue;
        }
        foreach ($busyWindows as $window) {
            $eventStart = $planDate . ' ' . $window[0];
            $eventEnd = $planDate . ' ' . $window[1];
            assertTrue(
                !$overlaps($slotStart, $slotEnd, $eventStart, $eventEnd),
                'Calendar slot must not overlap existing event window'
            );
        }
    }

    request('DELETE', '/api/v1/ai/providers/' . $providerPublicId, [], $headers);

    fwrite(STDOUT, "[OK] ai_my_day_plan_respects_calendar_slots_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_my_day_plan_respects_calendar_slots_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
