<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $unauthorized = request('POST', '/api/v1/telemetry/frontend-event', [
        'event_type' => 'api_error',
        'payload' => ['message' => 'must fail without auth'],
    ]);
    assertTrue($unauthorized['status'] === 401, 'Telemetry endpoint must require auth');

    $root = loginRoot();
    $rootHeaders = authHeaders($root['token']);

    $invalid = request('POST', '/api/v1/telemetry/frontend-event', [
        'event_type' => 'unknown_event',
        'payload' => ['message' => 'bad event'],
    ], $rootHeaders);
    assertTrue($invalid['status'] === 422, 'Unknown telemetry event must be rejected');

    $accepted = request('POST', '/api/v1/telemetry/frontend-event', [
        'event_type' => 'api_error',
        'route' => 'tasks',
        'page_url' => '/web/index.php?route=tasks',
        'payload' => [
            'message' => 'test api error',
            'password' => 'secret-password',
            'token' => 'secret-token',
            'request_id' => 'req-demo',
        ],
    ], $rootHeaders);
    assertTrue($accepted['status'] === 200, 'Telemetry event must be accepted');
    assertTrue((string)($accepted['payload']['code'] ?? '') === 'TELEMETRY_ACCEPTED', 'Telemetry response code mismatch');

    $logs = request('GET', '/api/v1/logs/security', [
        'event_type' => 'frontend_api_error',
        'limit' => 20,
    ], $rootHeaders);
    assertTrue($logs['status'] === 200, 'Security logs query must be available');
    $items = (array)($logs['payload']['data']['items'] ?? []);
    assertTrue($items !== [], 'Telemetry security log item is required');

    $first = (array)$items[0];
    $details = (array)($first['details'] ?? []);
    $payload = (array)($details['payload'] ?? []);
    $passwordValue = (string)($payload['password'] ?? '');
    $tokenValue = (string)($payload['token'] ?? '');
    assertTrue($passwordValue === '[REDACTED]' || $passwordValue === '', 'Telemetry must redact password');
    assertTrue($tokenValue === '[REDACTED]' || $tokenValue === '', 'Telemetry must redact token');

    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
