<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

function headerContains(array $headers, string $needle): bool
{
    $needle = strtolower($needle);
    foreach ($headers as $line) {
        if (str_contains(strtolower((string)$line), $needle)) {
            return true;
        }
    }

    return false;
}

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];

    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    $roleCreate = liveRequest('POST', 'api/v1/roles', [
        'code' => 'sse_session_' . $suffix,
        'title' => 'SSE Session ' . $suffix,
    ], $rootHeaders);
    liveAssert($roleCreate['status'] === 201, 'Role create must return 201');
    $rolePublicId = (string)($roleCreate['payload']['data']['role']['public_id'] ?? '');
    liveAssert($rolePublicId !== '', 'Role public_id is required');

    $setPerms = liveRequest('PUT', 'api/v1/roles/' . $rolePublicId . '/permissions', [
        'permission_codes' => ['user.view'],
    ], $rootHeaders);
    liveAssert($setPerms['status'] === 200, 'Role permissions set must return 200');

    $login = 'sse_user_' . $suffix;
    $initialTokenFactor = 'sse-token-' . $suffix;

    $userCreate = liveRequest('POST', 'api/v1/users', [
        'login' => $login,
        'password' => 'SseUser123!',
        'token' => $initialTokenFactor,
        'email' => $login . '@crm.local',
        'locale' => 'en-gb',
        'role_public_ids' => [$rolePublicId],
    ], $rootHeaders);
    liveAssert($userCreate['status'] === 201, 'User create must return 201');
    $userPublicId = (string)($userCreate['payload']['data']['user']['public_id'] ?? '');
    liveAssert($userPublicId !== '', 'User public_id is required');

    $userLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'SseUser123!',
        'token' => $initialTokenFactor,
    ]);
    liveAssert($userLogin['status'] === 200, 'User login must return 200');
    $accessToken = (string)($userLogin['payload']['data']['access_token'] ?? '');
    liveAssert($accessToken !== '', 'Access token is required');
    $userHeaders = ['Authorization' => 'Bearer ' . $accessToken];

    // Baseline SSE availability.
    $streamOk = liveRequest('GET', 'api/v1/events/stream', [], $userHeaders);
    liveAssert($streamOk['status'] === 200, 'SSE stream with valid token must return 200');
    liveAssert(headerContains($streamOk['headers'], 'text/event-stream'), 'SSE response must include text/event-stream content type');

    // Revoke current session -> same token must be rejected.
    $sessions = liveRequest('GET', 'api/v1/security/sessions', [], $userHeaders);
    liveAssert($sessions['status'] === 200, 'Session list must return 200');
    $sessionPublicId = (string)($sessions['payload']['data']['items'][0]['public_id'] ?? '');
    liveAssert($sessionPublicId !== '', 'Session public_id is required for revoke test');

    $revokeSelf = liveRequest('DELETE', 'api/v1/security/sessions/' . $sessionPublicId, [], $userHeaders);
    liveAssert($revokeSelf['status'] === 200, 'Self session revoke must return 200');

    $streamAfterRevoke = liveRequest('GET', 'api/v1/events/stream', [], $userHeaders);
    liveAssert($streamAfterRevoke['status'] === 401, 'SSE stream after session revoke must return 401');

    // Login again and verify stream works.
    $userLogin2 = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'SseUser123!',
        'token' => $initialTokenFactor,
    ]);
    liveAssert($userLogin2['status'] === 200, 'Second login must return 200');
    $accessToken2 = (string)($userLogin2['payload']['data']['access_token'] ?? '');
    liveAssert($accessToken2 !== '', 'Second access token is required');
    $userHeaders2 = ['Authorization' => 'Bearer ' . $accessToken2];

    $streamOk2 = liveRequest('GET', 'api/v1/events/stream', [], $userHeaders2);
    liveAssert($streamOk2['status'] === 200, 'SSE stream before logout must return 200');

    // Logout invalidates current token for stream.
    $logout = liveRequest('POST', 'api/v1/auth/logout', [], $userHeaders2);
    liveAssert($logout['status'] === 200, 'Logout must return 200');

    $streamAfterLogout = liveRequest('GET', 'api/v1/events/stream', [], $userHeaders2);
    liveAssert($streamAfterLogout['status'] === 401, 'SSE stream after logout must return 401');

    // Invalid bearer token must be rejected.
    $streamInvalidToken = liveRequest('GET', 'api/v1/events/stream', [], [
        'Authorization' => 'Bearer invalid-sse-token-' . $suffix,
    ]);
    liveAssert($streamInvalidToken['status'] === 401, 'SSE stream with invalid bearer must return 401');

    // Token rotation: old token-factor login must fail, new one must pass.
    $rotate = liveRequest('POST', 'api/v1/users/' . $userPublicId . '/tokens/rotate', [], $rootHeaders);
    liveAssert($rotate['status'] === 200, 'User token rotate must return 200');
    $newTokenFactor = (string)($rotate['payload']['data']['plain_token'] ?? '');
    liveAssert($newTokenFactor !== '', 'Rotate must return new plain_token');

    $loginWithOldTokenFactor = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'SseUser123!',
        'token' => $initialTokenFactor,
    ]);
    liveAssert($loginWithOldTokenFactor['status'] === 401, 'Login with old token-factor after rotate must return 401');

    $loginWithNewTokenFactor = liveRequest('POST', 'api/v1/auth/login', [
        'login' => $login,
        'password' => 'SseUser123!',
        'token' => $newTokenFactor,
    ]);
    liveAssert($loginWithNewTokenFactor['status'] === 200, 'Login with new token-factor after rotate must return 200');

    liveRequest('DELETE', 'api/v1/users/' . $userPublicId, [], $rootHeaders);
    liveRequest('DELETE', 'api/v1/roles/' . $rolePublicId, [], $rootHeaders);

    echo "[OK] advanced_sse_session_expiry_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_sse_session_expiry_live: ' . $e->getMessage() . "\n");
    exit(1);
}
