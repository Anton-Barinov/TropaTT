<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $token = (string)($root['token'] ?? '');
    $csrfToken = (string)($root['csrf_token'] ?? '');
    assertTrue($token !== '', 'Root session token is required');
    assertTrue($csrfToken !== '', 'Root csrf token is required');

    $cookies = ['crm_api_session' => $token];

    $withoutCsrf = request(
        'POST',
        '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run',
        [],
        [],
        [],
        $cookies
    );
    assertTrue($withoutCsrf['status'] === 403, 'AI cookie write without CSRF must be 403');
    assertTrue((string)($withoutCsrf['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'AI cookie write without CSRF must return CSRF_TOKEN_INVALID');

    $badOrigin = request(
        'POST',
        '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run',
        [],
        [
            'X-CSRF-Token' => $csrfToken,
            'Origin' => 'https://evil.example.test',
        ],
        [],
        $cookies
    );
    assertTrue($badOrigin['status'] === 403, 'AI cookie write with untrusted Origin must be 403');
    assertTrue((string)($badOrigin['payload']['code'] ?? '') === 'CSRF_TOKEN_INVALID', 'AI cookie write with untrusted Origin must return CSRF_TOKEN_INVALID');

    $allowedOrigin = request(
        'POST',
        '/api/v1/ai/jobs/ai:suggestion-cleanup/dry-run',
        [],
        [
            'X-CSRF-Token' => $csrfToken,
            'Origin' => 'https://localhost',
        ],
        [],
        $cookies
    );

    $allowedCode = (string)($allowedOrigin['payload']['code'] ?? '');
    assertTrue($allowedCode !== 'CSRF_TOKEN_INVALID', 'AI cookie write with valid CSRF/origin must not fail CSRF contract');
    assertTrue($allowedOrigin['status'] !== 401, 'AI cookie write with valid CSRF/origin must remain authenticated');

    fwrite(STDOUT, "[OK] ai_cookie_csrf_origin_contract_smoke\n");
} catch (Throwable $e) {
    fwrite(STDERR, "[FAIL] ai_cookie_csrf_origin_contract_smoke: " . $e->getMessage() . "\n");
    exit(1);
}
