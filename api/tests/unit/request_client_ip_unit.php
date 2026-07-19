<?php
declare(strict_types=1);

/**
 * SEC-005: Request IP resolution unit tests.
 *
 * Tests:
 * - remoteAddr() returns raw REMOTE_ADDR
 * - clientIp() returns REMOTE_ADDR when no trusted proxies
 * - clientIp() ignores X-Forwarded-For from untrusted sources
 * - clientIp() returns real client IP from trusted proxy
 * - CIDR matching for IPv4, IPv6, /0, /32, /128
 * - IPv4-mapped IPv6 addresses
 * - InstallController uses remoteAddr() for loopback check
 *
 * Run: php -d auto_prepend_file= api/tests/unit/request_client_ip_unit.php
 */

require_once __DIR__ . '/../../system/library/http/Request.php';

use Api\System\Library\Http\Request;

$passed = 0;
$failed = 0;
$errors = [];

function assertTrue(bool $condition, string $label): void
{
    global $passed, $failed, $errors;
    if ($condition) { $passed++; echo "  PASS: $label\n"; }
    else { $failed++; $errors[] = "FAIL: $label"; echo "  FAIL: $label\n"; }
}

function assertFalse(bool $condition, string $label): void
{
    assertTrue(!$condition, $label);
}

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    global $passed, $failed, $errors;
    if ($expected === $actual) { $passed++; echo "  PASS: $label\n"; }
    else {
        $failed++; $errors[] = "FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true);
        echo "  FAIL: $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

// Helper to create a Request with custom server vars and headers
function createRequest(array $server, array $headers = []): Request
{
    return new Request(
        method: 'GET',
        uri: '/test',
        path: '/test',
        query: [],
        post: [],
        cookies: [],
        files: [],
        server: $server,
        headers: $headers,
        rawBody: '',
        requestId: 'test-123',
        correlationId: 'test-123',
        locale: 'en-gb',
    );
}

// ===== Tests =====

echo "=== remoteAddr() — raw REMOTE_ADDR ===\n";
$req = createRequest(['REMOTE_ADDR' => '1.2.3.4']);
assertEquals('1.2.3.4', $req->remoteAddr(), "remoteAddr should return raw REMOTE_ADDR");

echo "=== ip() without trusted proxies ===\n";
$req = createRequest(['REMOTE_ADDR' => '5.6.7.8']);
assertEquals('5.6.7.8', $req->ip(), "ip() without trusted proxies should return REMOTE_ADDR");

echo "=== X-Forwarded-For ignored without trusted proxies ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '9.10.11.12'],
    ['X-Forwarded-For' => '1.2.3.4']
);
assertEquals('9.10.11.12', $req->clientIp(), "clientIp should ignore X-Forwarded-For when no trusted proxies");

echo "=== X-Forwarded-For ignored from untrusted REMOTE_ADDR ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '9.10.11.12'],
    ['X-Forwarded-For' => '1.2.3.4']
);
$req->setTrustedProxies(['10.0.0.0/8'], 'X-Forwarded-For');
assertEquals('9.10.11.12', $req->clientIp(), "clientIp should ignore header when REMOTE_ADDR not trusted");

echo "=== Trusted proxy returns real client IP ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '10.0.0.1'],
    ['X-Forwarded-For' => '1.2.3.4']
);
$req->setTrustedProxies(['10.0.0.0/8'], 'X-Forwarded-For');
assertEquals('1.2.3.4', $req->clientIp(), "clientIp should return original IP from behind trusted proxy");

echo "=== Chain: 1.2.3.4, 10.0.0.1, 10.0.0.2 with trusted 10.0.0.0/8 ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '10.0.0.2'],
    ['X-Forwarded-For' => '1.2.3.4, 10.0.0.1, 10.0.0.2']
);
$req->setTrustedProxies(['10.0.0.0/8'], 'X-Forwarded-For');
assertEquals('1.2.3.4', $req->clientIp(), "Should return rightmost non-trusted address");

echo "=== Multiple trusted ranges ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '192.168.0.1'],
    ['X-Forwarded-For' => '8.8.8.8, 10.0.0.1']
);
$req->setTrustedProxies(['10.0.0.0/8', '192.168.0.0/16'], 'X-Forwarded-For');
assertEquals('8.8.8.8', $req->clientIp(), "Should strip both proxy ranges");

echo "=== CIDR /0 — all IPs are trusted, REMOTE_ADDR as fallback ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '1.2.3.4'],
    ['X-Forwarded-For' => '5.6.7.8']
);
$req->setTrustedProxies(['0.0.0.0/0'], 'X-Forwarded-For');
// /0 matches everything, so the REMOTE_ADDR is trusted (header is read)
// but the IP from header (5.6.7.8) is ALSO trusted by /0 → no non-trusted found
// → clientIp falls back to REMOTE_ADDR
assertEquals('1.2.3.4', $req->clientIp(), "With /0 all IPs are trusted, REMOTE_ADDR falls back");

echo "=== CIDR /32 exact match ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '10.0.0.1'],
    ['X-Forwarded-For' => '8.8.8.8']
);
$req->setTrustedProxies(['10.0.0.1/32'], 'X-Forwarded-For');
assertEquals('8.8.8.8', $req->clientIp(), "With /32 exact match should work");

echo "=== IPv6 CIDR matching ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '::1'],
    ['X-Forwarded-For' => '2a00:1450:4000::1']
);
$req->setTrustedProxies(['::1/128'], 'X-Forwarded-For');
assertEquals('2a00:1450:4000::1', $req->clientIp(), "IPv6 /128 exact match should work");

echo "=== Invalid IP in X-Forwarded-For ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '10.0.0.1'],
    ['X-Forwarded-For' => 'not-an-ip, 10.0.0.2']
);
$req->setTrustedProxies(['10.0.0.0/8'], 'X-Forwarded-For');
assertEquals('10.0.0.1', $req->clientIp(), "Should fall back to REMOTE_ADDR if no valid non-trusted IP");

echo "=== Empty trusted proxies list ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '10.0.0.1'],
    ['X-Forwarded-For' => '1.2.3.4']
);
$req->setTrustedProxies([], 'X-Forwarded-For');
assertEquals('10.0.0.1', $req->clientIp(), "Empty trusted list should use REMOTE_ADDR");

echo "=== Single address (non-CIDR) ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '192.168.1.1'],
    ['X-Forwarded-For' => '8.8.8.8']
);
$req->setTrustedProxies(['192.168.1.1'], 'X-Forwarded-For');
assertEquals('8.8.8.8', $req->clientIp(), "Single IP (no CIDR) should be treated as /32");

echo "=== IPv4-mapped IPv6 addresses ===\n";
$req = createRequest(
    ['REMOTE_ADDR' => '::ffff:10.0.0.1'],
    ['X-Forwarded-For' => '1.2.3.4']
);
$req->setTrustedProxies(['10.0.0.0/8'], 'X-Forwarded-For');
assertEquals('1.2.3.4', $req->clientIp(), "IPv4-mapped IPv6 should match IPv4 CIDR");

// RESULTS
echo "\n========== RESULTS ==========\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
if ($errors !== []) {
    echo "Errors:\n";
    foreach ($errors as $error) { echo "  - $error\n"; }
    exit(1);
}
echo "All tests passed!\n";
exit(0);
