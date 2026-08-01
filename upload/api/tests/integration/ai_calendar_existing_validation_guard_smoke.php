<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $migrationRun = request('POST', '/internal/migration/up', [], $rootHeaders);
    assertTrue(in_array($migrationRun['status'], [200, 201], true), 'Migration up must return 200/201');

    $pastEventCreate = request('POST', '/api/v1/calendar/events', [
        'title' => 'AI calendar validation past event ' . randomSuffix(),
        'starts_at' => (new DateTimeImmutable('yesterday 10:00:00'))->format('Y-m-d H:i:s'),
        'ends_at' => (new DateTimeImmutable('yesterday 10:30:00'))->format('Y-m-d H:i:s'),
    ], $rootHeaders);
    assertTrue($pastEventCreate['status'] === 422, 'Calendar create in the past must be rejected');
    assertTrue(isset($pastEventCreate['payload']['errors']['starts_at']), 'Past calendar validation must point to starts_at');

    $futureEventCreate = request('POST', '/api/v1/calendar/events', [
        'title' => 'AI calendar validation future event ' . randomSuffix(),
        'starts_at' => (new DateTimeImmutable('tomorrow 10:00:00'))->format('Y-m-d H:i:s'),
        'ends_at' => (new DateTimeImmutable('tomorrow 10:30:00'))->format('Y-m-d H:i:s'),
    ], $rootHeaders);
    assertTrue($futureEventCreate['status'] === 201, 'Calendar future create must succeed');
    $futureEventPublicId = (string)($futureEventCreate['payload']['data']['event']['public_id'] ?? '');
    assertTrue($futureEventPublicId !== '', 'Calendar future event public_id is required');

    $futureEventDelete = request('DELETE', '/api/v1/calendar/events/' . $futureEventPublicId, [], $rootHeaders);
    assertTrue($futureEventDelete['status'] === 200, 'Calendar future event cleanup must succeed');

    fwrite(STDOUT, "[OK] ai_calendar_existing_validation_guard_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] ai_calendar_existing_validation_guard_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}
