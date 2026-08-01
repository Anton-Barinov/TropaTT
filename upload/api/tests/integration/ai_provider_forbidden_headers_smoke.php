<?php
declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

try {
    $root = loginRoot();
    $headers = authHeaders($root['token']);

    $cases = [
        'Authorization',
        'Proxy-Authorization',
        'X-Api-Key',
        'Cookie',
        'Set-Cookie',
        'Host',
        'X-Forwarded-For',
        'X-Forwarded-Host',
        'X-Forwarded-Proto',
        'X-Real-IP',
        'Forwarded',
    ];

    foreach ($cases as $headerName) {
        $create = request('POST', '/api/v1/ai/providers', [
            'provider_code' => 'blocked_' . strtolower(str_replace(['-', '_'], '', $headerName)) . '_' . randomSuffix(),
            'title' => 'Blocked header test',
            'base_url' => 'https://example.com',
            'extra_headers' => [$headerName => 'secret-value'],
        ], $headers);
        assertTrue($create['status'] === 422, 'Create must reject forbidden header: ' . $headerName);
        assertTrue((string)($create['payload']['code'] ?? '') === 'AI_PROVIDER_HEADERS_FORBIDDEN', 'Create error code mismatch: ' . $headerName);
    }

    $okProvider = request('POST', '/api/v1/ai/providers', [
        'provider_code' => 'openai_compatible',
        'title' => 'Allowed header update baseline',
        'base_url' => 'https://example.com',
        'extra_headers' => ['X-Workspace' => 'crm'],
        'is_active' => 1,
    ], $headers);
    assertTrue($okProvider['status'] === 201, 'Baseline provider create must be 201');
    $providerPublicId = (string)($okProvider['payload']['data']['provider']['public_id'] ?? '');
    assertTrue($providerPublicId !== '', 'Baseline provider public_id is required');

    foreach ($cases as $headerName) {
        $update = request('PATCH', '/api/v1/ai/providers/' . $providerPublicId, [
            'extra_headers' => [$headerName => 'secret-value'],
        ], $headers);
        assertTrue($update['status'] === 422, 'Update must reject forbidden header: ' . $headerName);
        assertTrue((string)($update['payload']['code'] ?? '') === 'AI_PROVIDER_HEADERS_FORBIDDEN', 'Update error code mismatch: ' . $headerName);
    }

    echo "OK\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

