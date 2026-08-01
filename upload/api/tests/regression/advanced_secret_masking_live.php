<?php
declare(strict_types=1);

require_once __DIR__ . '/../_live_http.php';

try {
    $root = liveLoginRoot();
    $rootHeaders = ['Authorization' => 'Bearer ' . $root['token']];
    $suffix = strtolower(gmdate('YmdHis') . '_' . bin2hex(random_bytes(3)));

    // 1) Generate login request with multiple sensitive keys to verify request log masking.
    $badLogin = liveRequest('POST', 'api/v1/auth/login', [
        'login' => 'mask_user_' . $suffix,
        'password' => 'PlainPass!123',
        'token' => 'plain-token-' . $suffix,
        'refresh_token' => 'refresh-' . $suffix,
        'api_key' => 'api-key-' . $suffix,
        'db_password' => 'db-pass-' . $suffix,
    ]);
    liveAssert(in_array($badLogin['status'], [401, 429], true), 'Bad login must return 401 or 429');

    // 2) Read request logs and find the latest auth/login POST payload.
    $logs = liveRequest('GET', 'api/v1/logs/request', [
        'request_route' => '/api/v1/auth/login',
        'method' => 'POST',
        'limit' => 30,
    ], $rootHeaders);
    liveAssert($logs['status'] === 200, 'Request logs must return 200');
    $items = (array)($logs['payload']['data']['items'] ?? []);
    liveAssert($items !== [], 'Request logs for auth/login must not be empty');

    $candidate = null;
    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }
        $payloadRaw = $row['payload'] ?? null;
        if (!is_string($payloadRaw) || $payloadRaw === '') {
            continue;
        }
        $decoded = json_decode($payloadRaw, true);
        if (!is_array($decoded)) {
            continue;
        }
        if (($decoded['login'] ?? null) === ('mask_user_' . $suffix)) {
            $candidate = $decoded;
            break;
        }
    }

    liveAssert(is_array($candidate), 'Masked login payload must be found in request logs');
    liveAssert((string)($candidate['password'] ?? '') === '***', 'password must be masked in request logs');
    liveAssert((string)($candidate['token'] ?? '') === '***', 'token must be masked in request logs');
    liveAssert((string)($candidate['refresh_token'] ?? '') === '***', 'refresh_token must be masked in request logs');
    liveAssert((string)($candidate['api_key'] ?? '') === '***', 'api_key must be masked in request logs');
    liveAssert((string)($candidate['db_password'] ?? '') === '***', 'db_password must be masked in request logs');

    // 3) Error response must not leak token-like value in message/errors.
    $badReset = liveRequest('POST', 'api/v1/security/password-reset/confirm', [
        'reset_token' => 'leak-token-' . $suffix,
        'new_password' => 'NewPass123!',
    ]);
    liveAssert(in_array($badReset['status'], [401, 404, 422], true), 'Password reset confirm negative status must be 401/404/422');
    $errorBody = json_encode($badReset['payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $needle = 'leak-token-' . $suffix;
    liveAssert(!is_string($errorBody) || !str_contains($errorBody, $needle), 'Error payload must not leak reset token value');

    echo "[OK] advanced_secret_masking_live\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[FAIL] advanced_secret_masking_live: ' . $e->getMessage() . "\n");
    exit(1);
}

