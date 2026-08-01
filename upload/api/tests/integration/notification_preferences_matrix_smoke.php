<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $auth = loginRoot();
    $headers = authHeaders($auth['token']);

    $suffix = randomSuffix();
    $payload = [
        'preferences' => [
            'notify_sound_enabled' => 1,
            'notify_quiet_hours_enabled' => 1,
            'notify_quiet_hours_start' => '23:00',
            'notify_quiet_hours_end' => '07:00',
            'notify_quiet_hours_timezone' => 'Europe/Moscow',

            'notify_tasks_in_app' => 1,
            'notify_tasks_sound' => 1,
            'notify_tasks_push' => 0,

            'notify_mentions_in_app' => 1,
            'notify_mentions_sound' => 1,
            'notify_mentions_push' => 1,

            'notify_security_in_app' => 1,
            'notify_security_sound' => 0,
            'notify_security_push' => 1,

            // marker to ensure values are not stale from previous run
            'notify_matrix_smoke_marker' => $suffix,
        ],
    ];

    $set = request('PUT', '/api/v1/profile/preferences', $payload, $headers);
    assertTrue($set['status'] === 200, 'Profile preferences set for notifications matrix must return 200');
    assertTrue((string)($set['payload']['code'] ?? '') === 'PROFILE_PREFERENCES_UPDATED', 'Profile preferences set code mismatch');

    $get = request('GET', '/api/v1/profile/preferences', [], $headers);
    assertTrue($get['status'] === 200, 'Profile preferences get must return 200');
    assertTrue((string)($get['payload']['code'] ?? '') === 'PROFILE_PREFERENCES', 'Profile preferences get code mismatch');

    $prefs = (array)($get['payload']['data']['preferences'] ?? []);
    assertTrue((string)($prefs['notify_quiet_hours_start'] ?? '') === '23:00', 'quiet hours start must persist');
    assertTrue((string)($prefs['notify_quiet_hours_end'] ?? '') === '07:00', 'quiet hours end must persist');
    assertTrue((string)($prefs['notify_quiet_hours_timezone'] ?? '') === 'Europe/Moscow', 'quiet hours timezone must persist');
    assertTrue((int)($prefs['notify_tasks_push'] ?? 1) === 0, 'tasks push channel must persist as disabled');
    assertTrue((int)($prefs['notify_mentions_push'] ?? 0) === 1, 'mentions push channel must persist as enabled');
    assertTrue((int)($prefs['notify_security_in_app'] ?? 0) === 1, 'security in-app channel must remain enabled');
    assertTrue((string)($prefs['notify_matrix_smoke_marker'] ?? '') === $suffix, 'matrix smoke marker must persist');

    fwrite(STDOUT, "[OK] notification_preferences_matrix_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] notification_preferences_matrix_smoke: ' . $e->getMessage() . "\n");
    exit(1);
}

