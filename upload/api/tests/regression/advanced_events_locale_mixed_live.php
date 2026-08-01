<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

function eventsAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];

    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'events_locale_' . $suffix,
        'title' => 'Events Locale ' . $suffix,
    ], $rootHeaders);
    eventsAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    eventsAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    eventsAssert($setPerms['status'] === 200, 'Role permission update must return 200');

    $login = 'events_locale_' . $suffix;
    $tokenFactor = 'events-token-' . $suffix;

    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'EventsLocale123!',
        'token' => $tokenFactor,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    eventsAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    eventsAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'EventsLocale123!',
        'token' => $tokenFactor,
    ]);
    eventsAssert($userLogin['status'] === 200, 'User login must return 200');
    $accessToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    eventsAssert($accessToken !== '', 'Access token is required');

    $stream = liveRequest('GET', 'api/v1/events/stream', [], [
        'Authorization' => 'Bearer ' . $accessToken,
        'X-Locale' => 'ru-ru',
    ]);
    eventsAssert($stream['status'] === 200, 'Events stream must return 200');
    eventsAssert(
        str_contains(strtolower(implode("\n", $stream['headers'])), 'text/event-stream'),
        'Events stream must return text/event-stream'
    );
    eventsAssert(
        str_contains($stream['body'], '"type":"stream.ready"'),
        'Events stream must emit stream.ready event payload'
    );
    eventsAssert(
        !preg_match('/\p{Cyrillic}/u', $stream['body']),
        'Events stream body must not contain Cyrillic symbols for en-gb locale'
    );

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_events_locale_mixed_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_events_locale_mixed_live: ' . $e->getMessage() . "\n");
    exit(1);
}
