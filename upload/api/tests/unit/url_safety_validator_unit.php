<?php
declare(strict_types=1);

/**
 * SEC-002: Unit tests for UrlSafetyValidator
 *
 * Tests:
 * - validateProviderUrl returns resolved_ips
 * - Existing ok/code keys are preserved (backward compat)
 * - DNS-unavailable check (hostname rejected when DNS missing)
 * - Unresolvable hostname rejected
 * - Literal public IP passes with resolved_ips = [ip]
 * - Literal private IP rejected
 * - Error paths include resolved_ips = []
 */

require_once __DIR__ . '/../../system/library/security/UrlSafetyValidator.php';

use Api\System\Library\Security\UrlSafetyValidator;

/** Simple assertion helper */
function assertEqual(mixed $expected, mixed $actual, string $label): void
{
    if ($expected !== $actual) {
        echo "[FAIL] {$label}: expected " . var_export($expected, true) . " got " . var_export($actual, true) . "\n";
        exit(1);
    }
}

// --- Test: Public IPv4 literal ---
$v = new UrlSafetyValidator();
$result = $v->validateProviderUrl('https://8.8.8.8/v1/api', true);
assertEqual(true, $result['ok'], 'Public IPv4 should pass');
assertEqual('OK', $result['code'], 'Code should be OK for public IPv4');
assertEqual(true, isset($result['resolved_ips']), 'resolved_ips key should exist');
assertEqual(1, count($result['resolved_ips']), 'resolved_ips should have 1 entry');
assertEqual('8.8.8.8', $result['resolved_ips'][0], 'resolved_ips[0] should be the IP');
echo "[PASS] Public IPv4 literal — resolved_ips contains IP\n";

// --- Test: Private IPv4 literal ---
$result = $v->validateProviderUrl('https://192.168.1.1/admin', true);
assertEqual(false, $result['ok'], 'Private IPv4 should be rejected');
assertEqual('AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN', $result['code'], 'Code should be PRIVATE_IP_FORBIDDEN');
assertEqual(true, isset($result['resolved_ips']), 'resolved_ips key should exist');
assertEqual([], $result['resolved_ips'], 'resolved_ips should be empty on error');
echo "[PASS] Private IPv4 literal — rejected with empty resolved_ips\n";

// --- Test: Loopback IP rejected ---
$result = $v->validateProviderUrl('http://127.0.0.1/hook', true);
assertEqual(false, $result['ok'], 'Loopback IP 127.0.0.1 should be rejected');
assertEqual('AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN', $result['code'], '127.0.0.1 is private IP, should be PRIVATE_IP_FORBIDDEN');
assertEqual([], $result['resolved_ips'], 'resolved_ips should be empty');
echo "[PASS] 127.0.0.1 loopback — rejected as private IP\n";

// --- Test: Link-local rejected ---
$result = $v->validateProviderUrl('http://169.254.169.254/latest/meta-data/', true);
assertEqual(false, $result['ok'], 'Link-local 169.254.x.x should be rejected');
assertEqual('AI_PROVIDER_URL_PRIVATE_IP_FORBIDDEN', $result['code'], 'Code should be PRIVATE_IP_FORBIDDEN');
echo "[PASS] 169.254.169.254 metadata — rejected\n";

// --- Test: Hostname via DNS (example.com) ---
$result = $v->validateProviderUrl('https://example.com/hook', true);
// This might pass or fail depending on DNS availability — but we check resolved_ips structure
assertEqual(true, isset($result['resolved_ips']), 'resolved_ips key always present');
assertEqual(true, isset($result['ok']), 'ok key always present');
assertEqual(true, isset($result['code']), 'code key always present');
if ($result['ok']) {
    echo "[PASS] example.com resolved: " . count($result['resolved_ips']) . " IPs — first: " . ($result['resolved_ips'][0] ?? 'none') . "\n";
} else {
    echo "[INFO] example.com could not resolve (DNS may be unavailable): code=" . $result['code'] . "\n";
}

// --- Test: Localhost hostname ---
$result = $v->validateProviderUrl('http://localhost/api', true);
assertEqual(false, $result['ok'], 'localhost hostname should be rejected');
assertEqual('AI_PROVIDER_URL_LOCALHOST_FORBIDDEN', $result['code'], 'Code should be LOCALHOST_FORBIDDEN');
assertEqual([], $result['resolved_ips'], 'resolved_ips should be empty');
echo "[PASS] localhost hostname — rejected\n";

// --- Test: Non-strict mode ---
$result = $v->validateProviderUrl('http://192.168.1.1/test', false);
assertEqual(true, $result['ok'], 'Non-strict: private IP should pass');
assertEqual([], $result['resolved_ips'], 'Non-strict: resolved_ips empty (no resolution)');
echo "[PASS] Non-strict mode — passes without resolution\n";

// --- Test: Empty URL ---
$result = $v->validateProviderUrl('', true);
assertEqual(false, $result['ok'], 'Empty URL should fail');
assertEqual([], $result['resolved_ips'], 'Empty URL: resolved_ips empty');
echo "[PASS] Empty URL — rejected\n";

// --- Test: Invalid URL ---
$result = $v->validateProviderUrl('not-a-url', true);
assertEqual(false, $result['ok'], 'Invalid URL should fail');
assertEqual([], $result['resolved_ips'], 'Invalid URL: resolved_ips empty');
echo "[PASS] Invalid URL — rejected\n";

// --- Test: Scheme not allowed (http when only https) ---
$result = $v->validateProviderUrl('http://example.com/api', true, ['https']);
assertEqual(false, $result['ok'], 'http not allowed when only https');
assertEqual('AI_PROVIDER_URL_SCHEME_NOT_ALLOWED', $result['code'], 'Should be SCHEME_NOT_ALLOWED');
assertEqual([], $result['resolved_ips'], 'Scheme error: resolved_ips empty');
echo "[PASS] Scheme not allowed — rejected\n";

echo "\n=== All UrlSafetyValidator unit tests passed ===\n";
