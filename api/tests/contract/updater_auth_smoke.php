<?php
declare(strict_types=1);

/**
 * Contract smoke test for the public updater entry point.
 * Run against an isolated installation with UPDATER_BASE_URL set.
 * It deliberately does not submit credentials or tokens.
 */
$base = rtrim((string)(getenv('UPDATER_BASE_URL') ?: ''), '/');
if ($base === '') {
    fwrite(STDERR, "UPDATER_BASE_URL is required\n");
    exit(2);
}

$ch = curl_init($base . '/updater/index.php?action=preflight');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => '{}',
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
]);
$body = (string)curl_exec($ch);
$status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$error = curl_error($ch);
// curl_close() is unnecessary on PHP 8+ and deprecated on PHP 8.5+.
if ($error !== '') {
    fwrite(STDERR, "request failed: {$error}\n");
    exit(1);
}
$payload = json_decode($body, true);
if ($status < 400 || !is_array($payload) || ($payload['success'] ?? true) !== false) {
    fwrite(STDERR, "anonymous preflight was not rejected\n");
    exit(1);
}
echo "anonymous preflight rejected: HTTP {$status}\n";
