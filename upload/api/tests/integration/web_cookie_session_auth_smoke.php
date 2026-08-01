<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    assertTrue($token !== '', 'Root token is required');

    $meViaCookie = request('GET', '/api/v1/auth/me', [], [], [], [
        'crm_api_session' => $token,
    ]);
    assertTrue($meViaCookie['status'] === 200, 'Auth me via cookie session must return 200');
    assertTrue((string)($meViaCookie['payload']['code'] ?? '') === 'AUTH_ME', 'Auth me via cookie session must return AUTH_ME');

    $userPublicId = (string)($meViaCookie['payload']['data']['user']['public_id'] ?? '');
    assertTrue($userPublicId !== '', 'Auth me via cookie session must return user public_id');

    $invalidCookie = request('GET', '/api/v1/auth/me', [], [], [], [
        'crm_api_session' => 'invalid-session-token',
    ]);
    assertTrue($invalidCookie['status'] === 401, 'Auth me with invalid cookie session must return 401');

    fwrite(STDOUT, "[OK] web_cookie_session_auth_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] web_cookie_session_auth_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
