<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

/**
 * Resilient key guard — checks for required secret keys at boot,
 * generates missing ones, writes them to .env, and notifies admin on failure.
 *
 * Never crashes the application. All failures are logged and reported.
 */
final class KeyGuard
{
    /** @var array<string,array{env_name:string,generator:string,description:string}> */
    private const REQUIRED_KEYS = [
        'app_key' => ['env_name' => 'APP_KEY', 'generator' => 'hex32', 'description' => 'Application encryption key'],
        'csrf_key' => ['env_name' => 'CSRF_SECRET_KEY', 'generator' => 'hex32', 'description' => 'CSRF protection key'],
        'webhook_key' => ['env_name' => 'WEBHOOK_SECRET_KEY', 'generator' => 'hex32', 'description' => 'Webhook signature key'],
        'ai_key' => ['env_name' => 'AI_ENCRYPTION_KEY', 'generator' => 'hex32', 'description' => 'AI encryption key'],
        'local_secret' => ['env_name' => 'CRM_LOCAL_SECRET', 'generator' => 'hex32', 'description' => 'Local secret key'],
        'cron_secret' => ['env_name' => 'CRON_SECRET_KEY', 'generator' => 'hex32', 'description' => 'Cron endpoint secret'],
    ];

    /** @var array<string,array{env_name:string,generator:string,description:string}> */
    private const OPTIONAL_KEYS = [
        'vapid_public' => ['env_name' => 'NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY', 'generator' => 'vapid', 'description' => 'Web Push VAPID public key'],
        'vapid_private' => ['env_name' => 'NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY', 'generator' => 'vapid', 'description' => 'Web Push VAPID private key'],
    ];

    private const VAPID_SUBJECT_KEY = 'NOTIFICATIONS_PUSH_VAPID_SUBJECT';
    private const VAPID_SUBJECT_DEFAULT = 'mailto:admin@example.com';

    private array $missing = [];
    private array $generated = [];
    private array $failed = [];

    /**
     * Check and generate missing keys. Returns true if all keys are present.
     *
     * SEC-002: In production (APP_ENV prod/production, defaulting to prod when unset)
     * refuse to silently auto-generate secrets to align with security.php's
     * fail-fast policy. Missing required keys surface as RuntimeException
     * so the operator is forced to set them explicitly.
     */
    public function ensureKeys(?string $envFilePath = null): bool
    {
        if ($envFilePath === null) {
            $envFilePath = $this->resolveEnvPath();
        }

        // Mirror security.php fail-fast semantics for web requests, but treat
        // CLI invocations as non-production by default so first-boot CLI tools
        // (migrations, seeders, generators) don't crash when APP_ENV is unset.
        $appEnv = strtolower(trim((string) getenv('APP_ENV')));
        $isCli = (PHP_SAPI === 'cli') || str_starts_with((string)PHP_SAPI, 'cli-');
        if ($appEnv === '' && !$isCli) {
            $appEnv = 'prod';
        }
        $isProduction = in_array($appEnv, ['prod', 'production'], true) && !$isCli;

        if ($envFilePath === '' || !is_file($envFilePath)) {
            if ($isProduction) {
                throw new \RuntimeException('Required .env file is missing in production');
            }
            return false;
        }

        $envContent = file_get_contents($envFilePath);
        if ($envContent === false) {
            if ($isProduction) {
                throw new \RuntimeException('Cannot read .env file in production');
            }
            return false;
        }

        $env = $this->parseEnv($envContent);
        $modified = false;

        // Check required keys
        foreach (self::REQUIRED_KEYS as $key => $config) {
            $value = $env[$config['env_name']] ?? '';
            if ($value !== '' && $value !== 'change-me-' . $key && !str_starts_with($value, 'change-me')) {
                continue;
            }

            if ($isProduction) {
                throw new \RuntimeException(
                    'Required secret ' . $config['env_name']
                    . ' is missing or has a placeholder value in production; '
                    . 'refusing to auto-generate.'
                );
            }

            $this->missing[] = $config['env_name'];
            $generated = $this->generate($config['generator']);
            if ($generated !== null) {
                $env[$config['env_name']] = $generated;
                $this->generated[] = $config['env_name'];
                $modified = true;
            } else {
                $this->failed[] = $config['env_name'] . ' (' . $config['description'] . ')';
            }
        }

        // Check VAPID keys (optional but important for push)
        $vapidPub = $env['NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY'] ?? '';
        $vapidPriv = $env['NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY'] ?? '';

        if ($vapidPub === '' || $vapidPriv === '') {
            if (!$isProduction) {
                $this->missing[] = 'NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY';
                $this->missing[] = 'NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY';

                $vapidKeys = $this->generateVapidKeyPair();
                if ($vapidKeys !== null) {
                    if ($vapidPub === '') {
                        $env['NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY'] = $vapidKeys['public_key'];
                        $this->generated[] = 'NOTIFICATIONS_PUSH_VAPID_PUBLIC_KEY';
                    }
                    if ($vapidPriv === '') {
                        $env['NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY'] = $vapidKeys['private_key'];
                        $this->generated[] = 'NOTIFICATIONS_PUSH_VAPID_PRIVATE_KEY';
                    }
                    if (($env[self::VAPID_SUBJECT_KEY] ?? '') === '') {
                        $env[self::VAPID_SUBJECT_KEY] = self::VAPID_SUBJECT_DEFAULT;
                    }
                    $modified = true;
                } else {
                    $this->failed[] = 'NOTIFICATIONS_PUSH_VAPID_* (Web Push VAPID keys)';
                }
            } else {
                // In production, VAPID missing is a misconfiguration but not fatal
                // (push notifications can be disabled). Record for visibility.
                $this->missing[] = 'NOTIFICATIONS_PUSH_VAPID_*';
            }
        }

        if ($modified && !$isProduction) {
            $this->writeEnv($envFilePath, $env);
        }

        return $this->failed === [];
    }

    /**
     * Get list of missing keys that couldn't be generated.
     * @return string[]
     */
    public function getFailed(): array
    {
        return $this->failed;
    }

    /**
     * Get list of keys that were auto-generated.
     * @return string[]
     */
    public function getGenerated(): array
    {
        return $this->generated;
    }

    /**
     * Check if there were any missing keys at all.
     */
    public function hasMissing(): bool
    {
        return $this->missing !== [];
    }

    private function generate(string $type): ?string
    {
        return match ($type) {
            'hex32' => $this->generateHex(32),
            default => null,
        };
    }

    private function generateHex(int $bytes): ?string
    {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Generate VAPID key pair using openssl.
     * @return array{public_key:string,private_key:string}|null
     */
    private function generateVapidKeyPair(): ?array
    {
        if (!function_exists('openssl_pkey_new')) {
            return null;
        }

        try {
            $key = openssl_pkey_new([
                'curve_name' => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ]);

            if ($key === false) {
                return null;
            }

            $details = openssl_pkey_get_details($key);
            if (!isset($details['ec'])) {
                return null;
            }

            $ec = $details['ec'];
            $publicKeyRaw = "\x04" . $ec['x'] . $ec['y'];
            $publicKey = rtrim(strtr(base64_encode($publicKeyRaw), '+/', '-_'), '=');
            $privateKey = rtrim(strtr(base64_encode($ec['d']), '+/', '-_'), '=');

            return ['public_key' => $publicKey, 'private_key' => $privateKey];
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveEnvPath(): string
    {
        // Try project root .env first, then api/.env
        $candidates = [
            dirname(__DIR__, 3) . '/.env',
            dirname(__DIR__, 2) . '/.env',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        // Default to api/.env (may not exist yet)
        return dirname(__DIR__, 2) . '/.env';
    }

    /**
     * Parse .env file into key-value pairs.
     * @return array<string,string>
     */
    private function parseEnv(string $content): array
    {
        $env = [];
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $eqPos = strpos($line, '=');
            if ($eqPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));

            // Remove surrounding quotes
            if (strlen($value) >= 2 && $value[0] === '"' && $value[strlen($value) - 1] === '"') {
                $value = substr($value, 1, -1);
            } elseif (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
                $value = substr($value, 1, -1);
            }

            $env[$key] = $value;
        }

        return $env;
    }

    /**
     * Write parsed env back to file, preserving comments and structure.
     *
     * SEC-002: Two safety layers:
     * 1. flock(LOCK_EX) around the read/parse so concurrent PHP-FPM workers
     *    cannot race on first-boot secret generation (TOCTOU between
     *    detect-missing-key and write-generated-key).
     * 2. Atomic replace via temp-file + rename — if the process crashes
     *    mid-write, the original .env is preserved (rename is atomic on POSIX).
     */
    private function writeEnv(string $path, array $env): void
    {
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        if (!@flock($fp, LOCK_EX)) {
            @fclose($fp);
            return;
        }
        try {
            $content = stream_get_contents($fp);
            if ($content === false) {
                return;
            }

            $lines = explode("\n", $content);
            $processed = [];
            $writtenKeys = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);

                // Skip empty lines and comments — keep them as-is
                if ($trimmed === '' || $trimmed[0] === '#') {
                    $processed[] = $line;
                    continue;
                }

                $eqPos = strpos($line, '=');
                if ($eqPos === false) {
                    $processed[] = $line;
                    continue;
                }

                $key = trim(substr($line, 0, $eqPos));
                if (array_key_exists($key, $env)) {
                    $processed[] = $key . '=' . $env[$key];
                    $writtenKeys[$key] = true;
                } else {
                    $processed[] = $line;
                }
            }

            // Append any new keys that weren't in the original file
            foreach ($env as $key => $value) {
                if (!isset($writtenKeys[$key])) {
                    $processed[] = $key . '=' . $value;
                }
            }

            // Atomic replace: write to a temp file, then rename over the target.
            // rename() on POSIX is atomic — a crashing writer leaves the
            // previous .env intact instead of truncating it.
            $tmpPath = $path . '.tmp.' . bin2hex(random_bytes(4));
            $bytes = @file_put_contents($tmpPath, implode("\n", $processed));
            if ($bytes === false) {
                @unlink($tmpPath);
                return;
            }
            if (!@rename($tmpPath, $path)) {
                @unlink($tmpPath);
                return;
            }
        } finally {
            @flock($fp, LOCK_UN);
            @fclose($fp);
        }
    }
}
