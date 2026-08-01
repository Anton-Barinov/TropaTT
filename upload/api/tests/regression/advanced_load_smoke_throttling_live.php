<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $headers = ['Authorization' => 'Bearer ' . $root['token']];

    $listRoutes = [
        'api/v1/users',
        'api/v1/projects',
        'api/v1/tasks',
        'api/v1/activity/feed',
        'api/v1/audit/list',
        'api/v1/notifications',
    ];

    $iterationsPerRoute = 12;
    $totalCalls = 0;
    $maxDurationMs = 0.0;

    foreach ($listRoutes as $route) {
        for ($i = 0; $i < $iterationsPerRoute; $i++) {
            $started = microtime(true);
            $response = liveRequest('GET', $route, [], $headers);
            $durationMs = (microtime(true) - $started) * 1000.0;
            if ($durationMs > $maxDurationMs) {
                $maxDurationMs = $durationMs;
            }

            liveAssert($response['status'] === 200, 'Load-smoke list endpoint must return 200: ' . $route);
            liveAssert((bool)($response['payload']['success'] ?? false) === true, 'Load-smoke list endpoint success flag must be true: ' . $route);
            $totalCalls++;
        }
    }

    $throttleStatuses = [];
    $throttleRoute = 'api/v1/auth/login';
    $throttlePayload = [
        'login' => 'throttle_load_probe',
        'password' => 'wrong-password',
        'token' => 'wrong-token',
    ];

    for ($i = 0; $i < 12; $i++) {
        $response = liveRequest('POST', $throttleRoute, $throttlePayload);
        $status = (int)$response['status'];
        $throttleStatuses[] = $status;
        liveAssert(in_array($status, [401, 429], true), 'Auth throttling status must be 401 or 429');
    }

    $has429 = in_array(429, $throttleStatuses, true);
    liveAssert($has429, 'Auth throttling must eventually return 429');

    echo '[OK] advanced_load_smoke_throttling_live';
    echo ' total_calls=' . $totalCalls;
    echo ' max_ms=' . number_format($maxDurationMs, 2, '.', '');
    echo ' throttle_statuses=' . implode(',', $throttleStatuses);
    echo "\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_load_smoke_throttling_live: ' . $e->getMessage() . "\n");
    exit(1);
}
