<?php
declare(strict_types=1);

/**
 * TropaTT post-install secrets rotation script.
 *
 * Regenerates the cryptographic secrets used to sign and encrypt TropaTT data:
 *   - APP_KEY              (HMAC for 2FA pending-token)
 *   - CSRF_SECRET_KEY      (HMAC for state-changing browser requests)
 *   - WEBHOOK_SECRET_KEY   (HMAC for inbound webhook payloads)
 *   - CRON_SECRET_KEY      (HMAC for cron endpoint authorization)
 *   - AI_ENCRYPTION_KEY    (AES-256-GCM for TOTP secret encryption; opt-in only via --include-ai-key)
 *
 * IMPORTANT: rotating AI_ENCRYPTION_KEY invalidates all previously encrypted
 * TOTP secrets stored in two_factor_secrets.backup_codes. After running with
 * --include-ai-key, every user with 2FA enabled must re-enroll their
 * authenticator. Notify all admin/root users before running in production.
 *
 * Usage (CLI only):
 *     php api/scripts/post_install_rotate_keys.php [--dry-run] [--include-ai-key]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be invoked from the command line only.\n");
}

$dryRun = in_array('--dry-run', $argv ?? [], true) || in_array('-n', $argv ?? [], true);
// SEC-C7-001 rotation guard: rotating AI_ENCRYPTION_KEY invalidates all
// existing TOTP secrets (TwoFactorService keeps ciphertexts under the old
// key). The flag must be passed explicitly so operators do not silently
// break 2FA for every user.
$includeAiKey = in_array('--include-ai-key', $argv ?? [], true);

$rootPath = dirname(__DIR__, 2);
$envPath = $rootPath . '/api/.env';

echo "==== TropaTT post-install rotate-keys ====\n";
echo "Target env file: {$envPath}\n";
echo "Mode: " . ($dryRun ? 'dry-run (no writes)' : 'LIVE (will overwrite)') . "\n";
echo "AI encryption key: " . ($includeAiKey ? 'WILL rotate (breaks existing 2FA)' : 'skip (pass --include-ai-key to rotate)') . "\n\n";

if (!is_file($envPath)) {
    fwrite(STDERR, "ERROR: env file not found: {$envPath}\n");
    exit(1);
}

$envContent = file_get_contents($envPath);
if ($envContent === false) {
    fwrite(STDERR, "ERROR: failed to read env file\n");
    exit(1);
}

$rotatableKeys = [
    'APP_KEY' => 'Application encryption key (HMAC for 2FA pending-token)',
    'CSRF_SECRET_KEY' => 'CSRF protection key (HMAC for state-changing browser requests)',
    'WEBHOOK_SECRET_KEY' => 'Webhook signature key (HMAC for inbound webhooks)',
    'CRON_SECRET_KEY' => 'Cron endpoint authorization key',
];
$aiKeyEntry = [
    'AI_ENCRYPTION_KEY' => 'AI encryption key (AES-256-GCM for TOTP secret encryption; breaks all existing 2FA enrollments)',
];

$newValues = [];
foreach ($rotatableKeys as $key => $description) {
    $newValues[$key] = bin2hex(random_bytes(32));
}
if ($includeAiKey) {
    foreach ($aiKeyEntry as $key => $description) {
        $newValues[$key] = bin2hex(random_bytes(32));
    }
}

$updatedContent = $envContent;
$regenerated = [];
$missing = [];
$allKeys = array_merge(array_keys($rotatableKeys), array_keys($aiKeyEntry));

foreach ($allKeys as $key) {
    if (!isset($newValues[$key])) {
        // AI key without explicit flag: skip silently (will print summary note later)
        continue;
    }
    $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';
    $newLine = $key . '=' . $newValues[$key];

    if (preg_match($pattern, $updatedContent)) {
        if ($dryRun) {
            $regenerated[] = "  [dry-run] {$key}: would rotate";
        } else {
            $updatedContent = preg_replace($pattern, $newLine, $updatedContent);
            $regenerated[] = "  [rotated] {$key}";
        }
    } else {
        $missing[] = $key;
        if (!$dryRun) {
            $append = PHP_EOL . PHP_EOL . $newLine . PHP_EOL;
            $updatedContent = rtrim($updatedContent) . $append;
            $regenerated[] = "  [appended] {$key}";
        } else {
            $regenerated[] = "  [dry-run] {$key}: would append (missing)";
        }
    }
}

if (!$includeAiKey) {
    echo "NOTE: AI_ENCRYPTION_KEY not rotated (pass --include-ai-key to rotate; breaking 2FA).\n";
}

echo "Actions:\n";
foreach ($regenerated as $line) {
    echo $line . "\n";
}

if ($missing !== [] && !$dryRun) {
    echo "\nNOTE: keys not previously present, appended at end of env file:\n";
    foreach ($missing as $key) {
        echo "  + {$key}\n";
    }
}

if ($dryRun) {
    echo "\nDry run complete — no changes written.\n";
    exit(0);
}

// Atomic write to prevent torn-file race with concurrent worker
$tmpPath = $envPath . '.rotating.' . bin2hex(random_bytes(4));
if (file_put_contents($tmpPath, $updatedContent, LOCK_EX) === false) {
    fwrite(STDERR, "\nERROR: failed to write tmp file {$tmpPath}\n");
    exit(1);
}
if (!rename($tmpPath, $envPath)) {
    @unlink($tmpPath);
    fwrite(STDERR, "\nERROR: failed to rename tmp to env file\n");
    exit(1);
}

echo "\nEnv file updated atomically.\n";

if (!$dryRun) {
    // Self-verification: re-read the env file and assert each rotated key is
    // a 64-character lowercase hex string. Catches torn writes or partial
    // writes if rename succeeded but writeblock was truncated by FS fault.
    $verifyContent = file_get_contents($envPath);
    $verifyFailures = [];
    foreach ($newValues as $key => $expectedValue) {
        $pattern = '/^' . preg_quote($key, '/') . '=([a-f0-9]{64})\s*$/m';
        if (!preg_match($pattern, $verifyContent, $m) || $m[1] !== $expectedValue) {
            $verifyFailures[] = $key;
        }
    }
    if ($verifyFailures !== []) {
        fwrite(STDERR, "ERROR: verify-after-write failed for keys: " . implode(', ', $verifyFailures) . "\n");
        exit(2);
    }
    echo "Self-verification: PASS (all rotated keys readable, 64 hex chars, match).\n";
}

echo "IMPORTANT: restart PHP-FPM so workers reload the new keys:\n";
echo "    systemctl reload php8.3-fpm  # or php8.2-fpm / php8.1-fpm / php-fpm\n";
if ($includeAiKey) {
    echo "IMPORTANT: AI_ENCRYPTION_KEY was rotated; all existing 2FA enrollments\n";
    echo "    are now invalid and users must re-enroll their TOTP.\n";
}
echo "\n==== Done ====\n";
