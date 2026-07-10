<?php

if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
declare(strict_types=1);

/**
 * Generate VAPID key pair for Web Push notifications.
 *
 * Usage: php api/scripts/generate_vapid_keys.php [subject-email]
 *
 * Outputs three values suitable for .env configuration:
 *   NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY=...
 *   NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY=...
 *   NOTIFICATIONS_PUSH_VAPID_SUBJECT=mailto:...
 *
 * Uses only PHP built-in openssl functions — no external dependencies.
 */

$subject = $argv[1] ?? 'mailto:admin@example.com';

if (!function_exists('openssl_pkey_new')) {
    fwrite(STDERR, "Error: openssl extension is required.\n");
    exit(1);
}

$config = [
    'curve_name' => 'prime256v1',
    'private_key_type' => OPENSSL_KEYTYPE_EC,
];

$key = openssl_pkey_new($config);
if ($key === false) {
    fwrite(STDERR, "Error: failed to generate key pair.\n");
    exit(1);
}

$details = openssl_pkey_get_details($key);
if (!is_array($details) || !isset($details['ec'])) {
    fwrite(STDERR, "Error: failed to extract key details.\n");
    exit(1);
}

$ec = $details['ec'];

// Uncompressed public key: 0x04 || x (32 bytes) || y (32 bytes)
$publicKeyRaw = "\x04" . $ec['x'] . $ec['y'];
$publicKeyBase64 = rtrim(base64_encode($publicKeyRaw), '=');
$publicKeyBase64 = strtr($publicKeyBase64, '+/', '-_');

// Private key: raw 32-byte scalar
$privateKeyRaw = $ec['d'];
$privateKeyBase64 = rtrim(base64_encode($privateKeyRaw), '=');
$privateKeyBase64 = strtr($privateKeyBase64, '+/', '-_');

// PHP 8.0+: OpenSSLAsymmetricKey objects are freed automatically

echo "NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY={$publicKeyBase64}\n";
echo "NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY={$privateKeyBase64}\n";
echo "NOTIFICATIONS_PUSH_VAPID_SUBJECT={$subject}\n";
