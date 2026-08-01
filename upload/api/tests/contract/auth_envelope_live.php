<?php
declare(strict_types=1);

require __DIR__ . '/../_live_http.php';

function runAuthEnvelopeLive(): void
{
    $unauthorized = liveRequest('GET', 'api/v1/auth/me');
    liveAssert($unauthorized['status'] === 401, 'GET /auth/me without token must be 401');
    liveAssert(($unauthorized['payload']['success'] ?? true) === false, 'Unauthorized success must be false');
    liveAssert((string)($unauthorized['payload']['code'] ?? '') === 'UNAUTHORIZED', 'Unauthorized code must be UNAUTHORIZED');
    liveAssert(isset($unauthorized['payload']['meta']['request_id']), 'Unauthorized meta.request_id is required');
    liveAssert(isset($unauthorized['payload']['meta']['correlation_id']), 'Unauthorized meta.correlation_id is required');

    $auth = liveLoginRoot();
    $login = (array)($auth['login_response'] ?? []);
    liveAssert($login['status'] === 200, 'POST /auth/login must be 200');
    liveAssert(($login['payload']['success'] ?? false) === true, 'Login success must be true');
    liveAssert((string)($login['payload']['code'] ?? '') === 'AUTH_LOGIN_SUCCESS', 'Login code must be AUTH_LOGIN_SUCCESS');
    liveAssert(isset($login['payload']['meta']['request_id']), 'Login meta.request_id is required');
    liveAssert(isset($login['payload']['meta']['correlation_id']), 'Login meta.correlation_id is required');

    $token = (string)($auth['token'] ?? $login['payload']['data']['access_token'] ?? '');
    liveAssert($token !== '', 'Login token is required');
    $headers = ['Authorization' => 'Bearer ' . $token];

    $me = liveRequest('GET', 'api/v1/auth/me', [], $headers);
    liveAssert($me['status'] === 200, 'GET /auth/me with token must be 200');
    liveAssert(($me['payload']['success'] ?? false) === true, 'Auth me success must be true');
    liveAssert((string)($me['payload']['code'] ?? '') === 'AUTH_ME', 'Auth me code must be AUTH_ME');
    liveAssert(isset($me['payload']['data']['user']['public_id']), 'Auth me user public_id is required');
}

runAuthEnvelopeLive();
echo "[OK] auth_envelope_live\n";
