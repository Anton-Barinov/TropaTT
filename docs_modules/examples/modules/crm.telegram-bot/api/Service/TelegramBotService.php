<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Service;

use PDO;
use RuntimeException;
use Throwable;
use Closure;

/**
 * Service managing Telegram Bot webhook/polling processing, staff binding,
 * customer intake creation, and outbound notifications for TropaTT CRM.
 */
final class TelegramBotService
{
    private PDO $db;
    private bool $schemaEnsured = false;
    /** @var Closure(string, array<string, mixed>, string, int):array<string, mixed>|null */
    private ?Closure $telegramApiTransport;

    public function __construct(PDO $db, ?callable $telegramApiTransport = null)
    {
        $this->db = $db;
        $this->telegramApiTransport = $telegramApiTransport !== null
            ? Closure::fromCallable($telegramApiTransport)
            : null;
    }

    /**
     * Ensure database columns exist (safe for sqlite/mysql/pgsql).
     */
    public function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }
        $this->schemaEnsured = true;

        try {
            // Check driver
            $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
            if ($driver === 'sqlite') {
                $this->db->exec("CREATE TABLE IF NOT EXISTS crm_telegram_dialogs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    public_id VARCHAR(32) UNIQUE,
                    organization_id INTEGER NOT NULL,
                    chat_id INTEGER NOT NULL,
                    first_name TEXT,
                    last_name TEXT,
                    username TEXT,
                    title TEXT,
                    last_message_text TEXT,
                    last_message_at TEXT,
                    unread_count INTEGER DEFAULT 0,
                    status TEXT DEFAULT 'open',
                    linked_task_id INTEGER,
                    linked_intake_id INTEGER,
                    created_at TEXT,
                    updated_at TEXT,
                    UNIQUE(organization_id, chat_id)
                )");

                $this->db->exec("CREATE TABLE IF NOT EXISTS crm_telegram_messages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    public_id VARCHAR(32) UNIQUE,
                    organization_id INTEGER NOT NULL,
                    dialog_id INTEGER NOT NULL,
                    chat_id INTEGER NOT NULL,
                    direction TEXT DEFAULT 'inbound',
                    sender_type TEXT DEFAULT 'client',
                    sender_user_id INTEGER,
                    sender_name TEXT,
                    telegram_message_id INTEGER,
                    message_text TEXT NOT NULL,
                    delivery_status TEXT DEFAULT 'delivered',
                    created_at TEXT
                )");

                // SQLite table_info check
                $cols = [];
                $q = $this->db->query("PRAGMA table_info(crm_telegram_configs)");
                if ($q) {
                    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                        $cols[] = strtolower((string)$row['name']);
                    }
                }
                if (!in_array('bot_username', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN bot_username TEXT");
                }
                if (!in_array('auto_create_intake', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN auto_create_intake INTEGER NOT NULL DEFAULT 1");
                }
                if (!in_array('welcome_message', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN welcome_message TEXT");
                }
                if (!in_array('proxy_type', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_type VARCHAR(16) NOT NULL DEFAULT 'none'");
                }
                if (!in_array('proxy_host', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_host TEXT");
                }
                if (!in_array('proxy_port', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_port INTEGER");
                }
                if (!in_array('proxy_username', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_username TEXT");
                }
                if (!in_array('proxy_password_encrypted', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_password_encrypted TEXT");
                }
            } else {
                // MySQL / MariaDB
                $this->db->exec("CREATE TABLE IF NOT EXISTS crm_telegram_dialogs (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    public_id VARCHAR(32) NOT NULL UNIQUE,
                    organization_id INT UNSIGNED NOT NULL,
                    chat_id BIGINT NOT NULL,
                    first_name VARCHAR(128) NULL,
                    last_name VARCHAR(128) NULL,
                    username VARCHAR(128) NULL,
                    title VARCHAR(255) NOT NULL,
                    last_message_text TEXT NULL,
                    last_message_at DATETIME NOT NULL,
                    unread_count INT UNSIGNED NOT NULL DEFAULT 0,
                    status VARCHAR(32) NOT NULL DEFAULT 'open',
                    linked_task_id INT UNSIGNED NULL,
                    linked_intake_id INT UNSIGNED NULL,
                    created_at DATETIME NOT NULL,
                    updated_at DATETIME NOT NULL,
                    UNIQUE KEY uq_tg_org_chat (organization_id, chat_id),
                    INDEX idx_tg_dlg_org_status (organization_id, status),
                    INDEX idx_tg_dlg_last_msg (organization_id, last_message_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $this->db->exec("CREATE TABLE IF NOT EXISTS crm_telegram_messages (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    public_id VARCHAR(32) NOT NULL UNIQUE,
                    organization_id INT UNSIGNED NOT NULL,
                    dialog_id INT UNSIGNED NOT NULL,
                    chat_id BIGINT NOT NULL,
                    direction VARCHAR(16) NOT NULL DEFAULT 'inbound',
                    sender_type VARCHAR(32) NOT NULL DEFAULT 'client',
                    sender_user_id INT UNSIGNED NULL,
                    sender_name VARCHAR(128) NULL,
                    telegram_message_id BIGINT NULL,
                    message_text TEXT NOT NULL,
                    delivery_status VARCHAR(32) NOT NULL DEFAULT 'delivered',
                    created_at DATETIME NOT NULL,
                    INDEX idx_tg_msg_dialog (dialog_id, id),
                    INDEX idx_tg_msg_org_chat (organization_id, chat_id),
                    INDEX idx_tg_msg_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

                $cols = [];
                $q = $this->db->query("SHOW COLUMNS FROM crm_telegram_configs");
                if ($q) {
                    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
                        $cols[] = strtolower((string)($row['Field'] ?? ''));
                    }
                }
                if (!in_array('bot_username', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN bot_username VARCHAR(128) NULL");
                }
                if (!in_array('auto_create_intake', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN auto_create_intake TINYINT(1) NOT NULL DEFAULT 1");
                }
                if (!in_array('welcome_message', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN welcome_message TEXT NULL");
                }
                if (!in_array('proxy_type', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_type VARCHAR(16) NOT NULL DEFAULT 'none'");
                }
                if (!in_array('proxy_host', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_host VARCHAR(255) NULL");
                }
                if (!in_array('proxy_port', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_port SMALLINT UNSIGNED NULL");
                }
                if (!in_array('proxy_username', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_username VARCHAR(255) NULL");
                }
                if (!in_array('proxy_password_encrypted', $cols, true)) {
                    $this->db->exec("ALTER TABLE crm_telegram_configs ADD COLUMN proxy_password_encrypted TEXT NULL");
                }
            }
        } catch (Throwable) {
            // Ignore schema alter errors if already present or restricted
        }
    }

    /** Encrypt bot credentials with authenticated encryption; never fall back to plaintext. */
    private function encryptToken(string $token): string
    {
        if ($token === '') {
            return '';
        }

        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Secure Telegram credential storage requires OpenSSL.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($token, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Failed to encrypt Telegram credentials.');
        }

        return 'gcm:' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt bot token.
     */
    private function decryptToken(string $encrypted): string
    {
        $encrypted = trim($encrypted);
        if ($encrypted === '') {
            return '';
        }

        if (str_starts_with($encrypted, 'gcm:')) {
            if (!function_exists('openssl_decrypt')) {
                throw new RuntimeException('Secure Telegram credential storage requires OpenSSL.');
            }
            $blob = base64_decode(substr($encrypted, 4), true);
            if ($blob !== false && strlen($blob) > 28) {
                $iv = substr($blob, 0, 12);
                $tag = substr($blob, 12, 16);
                $cipher = substr($blob, 28);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag);
                if ($plain !== false && is_string($plain) && $plain !== '') {
                    return trim($plain);
                }
            }
            throw new RuntimeException('Stored Telegram credentials are invalid or cannot be authenticated; re-enter the bot token.');
        }

        // Plaintext and Base64 records are legacy obfuscation, not encryption.
        // Do not silently recover them; require the workspace administrator to
        // re-enter the credential through the authenticated settings screen.
        if (str_starts_with($encrypted, 'b64:') || preg_match('/^[0-9]{8,12}:[a-zA-Z0-9_-]{30,50}$/', $encrypted)) {
            throw new RuntimeException('Stored Telegram credentials use an insecure legacy format; re-enter the bot token.');
        }

        throw new RuntimeException('Stored Telegram credentials are invalid; re-enter the bot token.');
    }

    /** Derive a per-installation key; public defaults are never accepted. */
    private function credentialKey(): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? '')));
        if ($key === '' || strlen($key) < 32 || hash_equals('crm-telegram-secret-key-32bytes!', $key)) {
            throw new RuntimeException('Secure Telegram credential storage is unavailable; configure a strong APP_KEY of at least 32 characters.');
        }
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Secure Telegram credential storage requires OpenSSL.');
        }

        return hash('sha256', $key, true);
    }

    private function encryptProxyPassword(string $password): string
    {
        if ($password === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($password, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag, 'crm.telegram.proxy-password.v1', 16);
        if (!is_string($cipher) || strlen($tag) !== 16) {
            throw new RuntimeException('Proxy password could not be stored securely.');
        }
        return 'tgproxy:v1:' . base64_encode($iv . $tag . $cipher);
    }

    private function decryptProxyPassword(string $stored): string
    {
        if ($stored === '') {
            return '';
        }
        if (!str_starts_with($stored, 'tgproxy:v1:')) {
            throw new RuntimeException('Stored Telegram proxy credentials are not encrypted. Re-enter the proxy password.');
        }
        $packed = base64_decode(substr($stored, 11), true);
        if (!is_string($packed) || strlen($packed) < 29) {
            throw new RuntimeException('Stored Telegram proxy credentials are invalid. Re-enter the proxy password.');
        }
        $plain = openssl_decrypt(substr($packed, 28), 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, substr($packed, 0, 12), substr($packed, 12, 16), 'crm.telegram.proxy-password.v1');
        if (!is_string($plain)) {
            throw new RuntimeException('Stored Telegram proxy credentials cannot be authenticated. Re-enter the proxy password.');
        }
        return $plain;
    }

    /** @return array{proxy_type:string,proxy_host:?string,proxy_port:?int,proxy_username:?string,proxy_password_encrypted:?string} */
    private function normalizeProxySettings(array $input, ?array $existing = null): array
    {
        if ($input === [] && $existing !== null) {
            return [
                'proxy_type' => (string)($existing['proxy_type'] ?? 'none'),
                'proxy_host' => $existing['proxy_host'] ?? null,
                'proxy_port' => isset($existing['proxy_port']) ? (int)$existing['proxy_port'] : null,
                'proxy_username' => $existing['proxy_username'] ?? null,
                'proxy_password_encrypted' => $existing['proxy_password_encrypted'] ?? null,
            ];
        }

        $type = strtolower(trim((string)($input['proxy_type'] ?? 'none')));
        if (!in_array($type, ['none', 'http', 'https', 'socks5'], true)) {
            throw new RuntimeException('Choose HTTP, HTTPS, SOCKS5, or no proxy.');
        }
        if ($type === 'none') {
            return ['proxy_type' => 'none', 'proxy_host' => null, 'proxy_port' => null, 'proxy_username' => null, 'proxy_password_encrypted' => null];
        }

        $host = trim((string)($input['proxy_host'] ?? ''));
        $portRaw = trim((string)($input['proxy_port'] ?? ''));
        $username = trim((string)($input['proxy_username'] ?? ''));
        $password = (string)($input['proxy_password'] ?? '');
        $clearPassword = !empty($input['proxy_clear_password']);
        $hostIsIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $hostIsName = strlen($host) <= 253 && preg_match('/^(?=.{1,253}$)(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)(?:\.(?:[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?))*\.?$/D', $host) === 1;
        if ($host === '' || (!$hostIsIp && !$hostIsName)) {
            throw new RuntimeException('Enter a proxy hostname or IP address without a scheme, path, or credentials.');
        }
        if ($portRaw === '' || !ctype_digit($portRaw) || (int)$portRaw < 1 || (int)$portRaw > 65535) {
            throw new RuntimeException('Proxy port must be a number from 1 to 65535.');
        }
        if (strlen($username) > 255 || strlen($password) > 1024) {
            throw new RuntimeException('Proxy username or password is too long.');
        }
        if ($password !== '' && $username === '') {
            throw new RuntimeException('Enter a proxy username before setting its password.');
        }

        $sameEndpoint = $existing !== null
            && (string)($existing['proxy_type'] ?? 'none') === $type
            && strcasecmp((string)($existing['proxy_host'] ?? ''), $host) === 0
            && (int)($existing['proxy_port'] ?? 0) === (int)$portRaw
            && (string)($existing['proxy_username'] ?? '') === $username;
        $encryptedPassword = null;
        if ($password !== '') {
            $encryptedPassword = $this->encryptProxyPassword($password);
        } elseif (!$clearPassword && $sameEndpoint) {
            $encryptedPassword = ($existing['proxy_password_encrypted'] ?? null) ?: null;
        }

        return [
            'proxy_type' => $type,
            'proxy_host' => $host,
            'proxy_port' => (int)$portRaw,
            'proxy_username' => $username !== '' ? $username : null,
            'proxy_password_encrypted' => $encryptedPassword,
        ];
    }

    /** Store Telegram webhook credentials with authenticated encryption. */
    private function encryptWebhookSecret(string $secret, bool $validateTelegramFormat = true): string
    {
        if ($secret === '' || strlen($secret) > 256
            || ($validateTelegramFormat && preg_match('/[^A-Za-z0-9_-]/', $secret))) {
            throw new RuntimeException('Telegram webhook secret must contain 1–256 letters, digits, underscores, or hyphens.');
        }
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Secure Telegram credential storage requires OpenSSL.');
        }

        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $secret,
            'aes-256-gcm',
            $this->credentialKey(),
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            'crm.telegram.webhook.v1',
            16
        );
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Telegram webhook secret could not be encrypted.');
        }
        return 'tgsec:v1:' . base64_encode($nonce . $tag . $ciphertext);
    }

    /**
     * Decrypt stored webhook credentials and lazily upgrade pre-encryption values.
     * The cleartext value is returned only to internal callers that must verify
     * Telegram requests or configure Telegram's webhook; API responses mask it.
     */
    private function decryptWebhookSecret(string $stored, int $configId): string
    {
        if (str_starts_with($stored, 'tgsec:v1:')) {
            if (!function_exists('openssl_decrypt')) {
                throw new RuntimeException('Secure Telegram credential storage requires OpenSSL.');
            }
            $packed = base64_decode(substr($stored, 9), true);
            if (!is_string($packed) || strlen($packed) < 29) {
                throw new RuntimeException('Stored Telegram webhook secret is invalid.');
            }
            $plain = openssl_decrypt(
                substr($packed, 28),
                'aes-256-gcm',
                $this->credentialKey(),
                OPENSSL_RAW_DATA,
                substr($packed, 0, 12),
                substr($packed, 12, 16),
                'crm.telegram.webhook.v1'
            );
            if (!is_string($plain) || $plain === '' || strlen($plain) > 256) {
                throw new RuntimeException('Stored Telegram webhook secret cannot be authenticated. Re-enter the secret in module settings.');
            }
            return $plain;
        }

        // One-time, in-place upgrade for legacy plaintext records. Keep the
        // exact secret so Telegram's already-registered webhook remains valid.
        if ($stored === '' || strlen($stored) > 256 || $configId <= 0) {
            throw new RuntimeException('Stored Telegram webhook secret is invalid.');
        }
        $encrypted = $this->encryptWebhookSecret($stored, false);
        $stmt = $this->db->prepare(
            'UPDATE crm_telegram_configs SET webhook_secret_token = :secret WHERE id = :id AND webhook_secret_token = :legacy'
        );
        $stmt->execute([':secret' => $encrypted, ':id' => $configId, ':legacy' => $stored]);
        return $stored;
    }

    /**
     * Mask token for display (show first 6 chars, bullets, last 4 chars).
     */
    public function maskToken(string $token): string
    {
        $token = trim($token);
        if ($token === '' || str_starts_with($token, 'gcm:') || str_starts_with($token, 'b64:')) {
            return '';
        }
        $len = strlen($token);
        if ($len <= 10) {
            return str_repeat('•', max(6, $len));
        }
        $prefix = substr($token, 0, 6);
        $suffix = substr($token, -4);
        return $prefix . '••••••••••••••••' . $suffix;
    }

    /**
     * Detect Telegram Bot configuration from environment variables or .env file.
     *
     * @return array{has_env: bool, bot_token: string, masked_token: string, bot_username: string, webhook_secret: string}
     */
    public function detectEnvConfig(): array
    {
        $token = (string)(getenv('TELEGRAM_BOT_TOKEN') ?: ($_ENV['TELEGRAM_BOT_TOKEN'] ?? ''));
        $username = (string)(getenv('TELEGRAM_BOT_USERNAME') ?: ($_ENV['TELEGRAM_BOT_USERNAME'] ?? ''));
        $secret = (string)(getenv('TELEGRAM_WEBHOOK_SECRET') ?: ($_ENV['TELEGRAM_WEBHOOK_SECRET'] ?? ''));

        if ($token === '') {
            $root = dirname(__DIR__, 3);
            $envPaths = [
                $root . '/api/.env',
                $root . '/.env',
                dirname($root) . '/api/.env',
                dirname($root) . '/.env',
            ];
            foreach ($envPaths as $p) {
                try {
                    if (@is_file($p) && @is_readable($p)) {
                        $lines = @file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if ($lines) {
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if (str_starts_with($line, '#')) continue;
                                if (preg_match('/^TELEGRAM_BOT_TOKEN\s*=\s*(.*)$/i', $line, $m)) {
                                    $token = trim($m[1], " \t\n\r\0\x0B\"'");
                                } elseif (preg_match('/^TELEGRAM_BOT_USERNAME\s*=\s*(.*)$/i', $line, $m)) {
                                    $username = trim($m[1], " \t\n\r\0\x0B\"'");
                                } elseif (preg_match('/^TELEGRAM_WEBHOOK_SECRET\s*=\s*(.*)$/i', $line, $m)) {
                                    $secret = trim($m[1], " \t\n\r\0\x0B\"'");
                                }
                            }
                        }
                        if ($token !== '') {
                            break;
                        }
                    }
                } catch (Throwable) {}
            }
        }

        $hasEnv = ($token !== '');
        return [
            'has_env' => $hasEnv,
            'bot_token' => $token,
            'masked_token' => $hasEnv ? $this->maskToken($token) : '',
            'bot_username' => $username,
            'webhook_secret' => $secret,
        ];
    }

    /**
     * Import Telegram credentials from .env.
     */
    public function importFromEnv(int $organizationId): array
    {
        $env = $this->detectEnvConfig();
        if (!$env['has_env']) {
            throw new RuntimeException("В файле .env не обнаружена переменная TELEGRAM_BOT_TOKEN.");
        }

        $secret = $env['webhook_secret'] !== '' ? $env['webhook_secret'] : bin2hex(random_bytes(16));
        $this->saveConfig(
            $organizationId,
            $env['bot_token'],
            $secret,
            $env['bot_username'] !== '' ? $env['bot_username'] : null,
            true
        );

        return $this->getStatus($organizationId);
    }

    /**
     * Get active bot configuration for organization.
     */
    public function getConfig(int $organizationId): ?array
    {
        if ($organizationId <= 0) {
            return null;
        }
        $this->ensureSchema();
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $decryptedToken = $this->decryptToken((string)$row['bot_token_encrypted']);
        $webhookSecret = $this->decryptWebhookSecret((string)$row['webhook_secret_token'], (int)$row['id']);
        $this->migrateLegacyWebhookSecrets(10);

        return [
            'public_id' => (string)$row['public_id'],
            'organization_id' => (int)$row['organization_id'],
            'bot_token' => $decryptedToken,
            'bot_token_masked' => $this->maskToken($decryptedToken),
            'webhook_secret_token' => $webhookSecret,
            'webhook_url' => $row['webhook_url'] ?? null,
            'bot_username' => $row['bot_username'] ?? null,
            'auto_create_intake' => !empty($row['auto_create_intake'] ?? 1),
            'welcome_message' => $row['welcome_message'] ?? "Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами.",
            'default_project_public_id' => $row['default_project_public_id'] ?? null,
            'default_assignee_user_public_id' => $row['default_assignee_user_public_id'] ?? null,
            'proxy_type' => (string)($row['proxy_type'] ?? 'none'),
            'proxy_host' => $row['proxy_host'] ?? null,
            'proxy_port' => isset($row['proxy_port']) ? (int)$row['proxy_port'] : null,
            'proxy_username' => $row['proxy_username'] ?? null,
            'proxy_password' => $this->decryptProxyPassword((string)($row['proxy_password_encrypted'] ?? '')),
            'proxy_password_set' => !empty($row['proxy_password_encrypted']),
            'is_active' => !empty($row['is_active']),
            'is_from_env' => false,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** Upgrade a small bounded batch of legacy plaintext secrets per config read. */
    private function migrateLegacyWebhookSecrets(int $limit): void
    {
        $limit = max(1, min(20, $limit));
        $stmt = $this->db->query(
            "SELECT id, webhook_secret_token FROM crm_telegram_configs
             WHERE webhook_secret_token NOT LIKE 'tgsec:v1:%'
               AND LENGTH(webhook_secret_token) BETWEEN 1 AND 256
             ORDER BY id ASC LIMIT {$limit}"
        );
        if (!$stmt) {
            return;
        }
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $legacy) {
            $legacyValue = (string)($legacy['webhook_secret_token'] ?? '');
            $id = (int)($legacy['id'] ?? 0);
            if ($id <= 0 || $legacyValue === '') {
                continue;
            }
            $encrypted = $this->encryptWebhookSecret($legacyValue, false);
            $update = $this->db->prepare(
                'UPDATE crm_telegram_configs SET webhook_secret_token = :secret WHERE id = :id AND webhook_secret_token = :legacy'
            );
            $update->execute([':secret' => $encrypted, ':id' => $id, ':legacy' => $legacyValue]);
        }
    }

    /**
     * Save/Update bot config for organization.
     */
    public function saveConfig(
        int $organizationId,
        string $botToken,
        string $webhookSecretToken,
        ?string $botUsername = null,
        bool $autoCreateIntake = true,
        ?string $welcomeMessage = null,
        ?string $defaultProject = null,
        ?string $defaultAssignee = null,
        ?string $webhookUrl = null,
        array $proxySettings = []
    ): string {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');

        // Check if existing for current org
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $proxy = $this->normalizeProxySettings($proxySettings, is_array($existing) ? $existing : null);

        // If botToken contains bullets, ciphertext or is empty and existing exists, keep existing token
        if ($existing && ($botToken === '' || str_contains($botToken, '••••') || str_starts_with($botToken, 'gcm:') || str_starts_with($botToken, 'b64:'))) {
            $encryptedToken = (string)$existing['bot_token_encrypted'];
        } else {
            if ($botToken === '') {
                throw new RuntimeException("Bot Token не может быть пустым.");
            }
            $encryptedToken = $this->encryptToken($botToken);
        }

        if ($webhookSecretToken === '') {
            // Preserve an already encrypted secret as-is. Older installations
            // have plaintext values; decryptWebhookSecret upgrades those on read.
            $storedSecret = (string)($existing['webhook_secret_token'] ?? '');
            $webhookSecretToken = $storedSecret !== ''
                ? $this->decryptWebhookSecret($storedSecret, (int)($existing['id'] ?? 0))
                : bin2hex(random_bytes(32));
        }
        $encryptedWebhookSecret = $this->encryptWebhookSecret($webhookSecretToken);

        // Auto-discover bot username if empty
        if ($botUsername === null || trim($botUsername) === '') {
            try {
                $plain = $this->decryptToken($encryptedToken);
                if ($plain !== '') {
                    $me = $this->callTelegramApi('getMe', [], $plain, $organizationId, $proxySettings !== [] ? $proxySettings : null);
                    if (!empty($me['ok']) && !empty($me['result']['username'])) {
                        $botUsername = '@' . ltrim((string)$me['result']['username'], '@');
                    }
                }
            } catch (Throwable) {}
        }

        $autoIntakeVal = $autoCreateIntake ? 1 : 0;
        $defaultWelcome = "Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами.";
        $welcomeVal = $welcomeMessage !== null && trim($welcomeMessage) !== '' ? trim($welcomeMessage) : $defaultWelcome;

        if ($existing) {
            $upd = $this->db->prepare(
                "UPDATE crm_telegram_configs
                 SET bot_token_encrypted = :tok,
                     webhook_secret_token = :sec,
                     webhook_url = :url,
                     bot_username = :uname,
                     auto_create_intake = :intake,
                     welcome_message = :welcome,
                     default_project_public_id = :proj,
                     default_assignee_user_public_id = :usr,
                     proxy_type = :proxy_type,
                     proxy_host = :proxy_host,
                     proxy_port = :proxy_port,
                     proxy_username = :proxy_username,
                     proxy_password_encrypted = :proxy_password_encrypted,
                     is_active = 1,
                     updated_at = :now
                 WHERE id = :id"
            );
            $upd->execute([
                ':tok' => $encryptedToken,
                ':sec' => $encryptedWebhookSecret,
                ':url' => $webhookUrl ?? $existing['webhook_url'],
                ':uname' => $botUsername ?? $existing['bot_username'],
                ':intake' => $autoIntakeVal,
                ':welcome' => $welcomeVal,
                ':proj' => $defaultProject,
                ':usr' => $defaultAssignee,
                ':proxy_type' => $proxy['proxy_type'],
                ':proxy_host' => $proxy['proxy_host'],
                ':proxy_port' => $proxy['proxy_port'],
                ':proxy_username' => $proxy['proxy_username'],
                ':proxy_password_encrypted' => $proxy['proxy_password_encrypted'],
                ':now' => $now,
                ':id' => $existing['id'],
            ]);
            return (string)$existing['public_id'];
        }

        $pubId = 'tgc_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_telegram_configs
             (public_id, organization_id, bot_token_encrypted, webhook_secret_token, webhook_url, bot_username, auto_create_intake, welcome_message, default_project_public_id, default_assignee_user_public_id, proxy_type, proxy_host, proxy_port, proxy_username, proxy_password_encrypted, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :tok, :sec, :url, :uname, :intake, :welcome, :proj, :usr, :proxy_type, :proxy_host, :proxy_port, :proxy_username, :proxy_password_encrypted, 1, :created_at, :updated_at)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':tok' => $encryptedToken,
            ':sec' => $encryptedWebhookSecret,
            ':url' => $webhookUrl,
            ':uname' => $botUsername,
            ':intake' => $autoIntakeVal,
            ':welcome' => $welcomeVal,
            ':proj' => $defaultProject,
            ':usr' => $defaultAssignee,
            ':proxy_type' => $proxy['proxy_type'],
            ':proxy_host' => $proxy['proxy_host'],
            ':proxy_port' => $proxy['proxy_port'],
            ':proxy_username' => $proxy['proxy_username'],
            ':proxy_password_encrypted' => $proxy['proxy_password_encrypted'],
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return $pubId;
    }

    /**
     * Execute an HTTP call to Telegram Bot API using cURL (strictly complies with SecurityLinter).
     *
     * @param array<string, mixed> $params
     * @return array{ok: bool, result?: mixed, description?: string, error_code?: int}
     */
    public function callTelegramApi(string $method, array $params = [], ?string $token = null, int $organizationId = 0, ?array $proxySettings = null): array
    {
        if ($organizationId <= 0) {
            return ['ok' => false, 'description' => 'A valid workspace is required.'];
        }
        $cfg = $this->getConfig($organizationId);
        if ($token === null || $token === '' || str_contains($token, '••••') || str_starts_with($token, 'gcm:') || str_starts_with($token, 'b64:')) {
            $token = $cfg['bot_token'] ?? '';
        }

        if ($token === '') {
            return [
                'ok' => false,
                'description' => 'Telegram Bot Token не настроен.',
            ];
        }

        if ($proxySettings === null) {
            $proxy = [
                'proxy_type' => (string)($cfg['proxy_type'] ?? 'none'),
                'proxy_host' => (string)($cfg['proxy_host'] ?? ''),
                'proxy_port' => (int)($cfg['proxy_port'] ?? 0),
                'proxy_username' => (string)($cfg['proxy_username'] ?? ''),
                'proxy_password' => (string)($cfg['proxy_password'] ?? ''),
            ];
        } else {
            $normalizedProxy = $this->normalizeProxySettings($proxySettings);
            $proxy = [
                'proxy_type' => $normalizedProxy['proxy_type'],
                'proxy_host' => (string)($normalizedProxy['proxy_host'] ?? ''),
                'proxy_port' => (int)($normalizedProxy['proxy_port'] ?? 0),
                'proxy_username' => (string)($normalizedProxy['proxy_username'] ?? ''),
                'proxy_password' => (string)($proxySettings['proxy_password'] ?? ''),
            ];
        }

        if ($this->telegramApiTransport !== null) {
            $result = ($this->telegramApiTransport)($method, $params, $token, $organizationId, $proxy);
            return is_array($result) ? $result : ['ok' => false, 'description' => 'Invalid Telegram transport response.'];
        }

        if ($proxy['proxy_type'] !== 'none') {
            $host = (string)$proxy['proxy_host'];
            $proxyHost = str_contains($host, ':') && !str_starts_with($host, '[') ? '[' . $host . ']' : $host;
            $scheme = match ($proxy['proxy_type']) {
                'http' => 'http://',
                'https' => 'https://',
                'socks5' => 'socks5h://',
                default => '',
            };
            $proxy['url'] = $scheme . $proxyHost . ':' . (int)$proxy['proxy_port'];
            if ($proxy['proxy_type'] === 'https') {
                $curlVersion = curl_version();
                if ((int)($curlVersion['version_number'] ?? 0) < 0x073400
                    || !defined('CURLOPT_PROXY_SSL_VERIFYPEER')
                    || !defined('CURLOPT_PROXY_SSL_VERIFYHOST')) {
                    return ['ok' => false, 'description' => 'HTTPS proxy requires cURL/libcurl 7.52.0 or later with proxy TLS verification options.'];
                }
            }
        }

        $url = "https://api.telegram.org/bot{$token}/{$method}";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'TropaTT-CRM-TelegramBot/1.0',
        ]);

        if ($proxy['proxy_type'] !== 'none') {
            $proxyOptions = [CURLOPT_PROXY => (string)$proxy['url']];
            if ($proxy['proxy_username'] !== '') {
                $proxyOptions[CURLOPT_PROXYUSERNAME] = (string)$proxy['proxy_username'];
                $proxyOptions[CURLOPT_PROXYPASSWORD] = (string)$proxy['proxy_password'];
                if (in_array($proxy['proxy_type'], ['http', 'https'], true)) {
                    $proxyOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
                }
            }
            if ($proxy['proxy_type'] === 'https') {
                $proxyOptions[CURLOPT_PROXY_SSL_VERIFYPEER] = true;
                $proxyOptions[CURLOPT_PROXY_SSL_VERIFYHOST] = 2;
            }
            if (!curl_setopt_array($ch, $proxyOptions)) {
                curl_close($ch);
                return ['ok' => false, 'description' => 'This PHP/cURL build does not support the selected Telegram proxy configuration.'];
            }
        }

        if (!empty($params)) {
            $json = json_encode($params, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen((string)$json),
            ]);
        }

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || $curlError !== '') {
            return [
                'ok' => false,
                'description' => 'Сетевая ошибка обращения к Telegram API: ' . ($curlError ?: "HTTP {$httpCode}"),
                '_delivery_unknown' => true,
            ];
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'description' => "Некорректный ответ от Telegram API (HTTP {$httpCode})",
                '_delivery_unknown' => true,
            ];
        }

        $decoded['_http_code'] = $httpCode;
        return $decoded;
    }

    /**
     * Test connection to Telegram API via getMe.
     *
     * @return array{ok: bool, bot?: array<string, mixed>, error?: string}
     */
    public function testConnection(?string $token = null, int $organizationId = 0, ?array $proxySettings = null): array
    {
        if ($token !== null && (str_contains($token, '••••') || str_starts_with($token, 'gcm:') || str_starts_with($token, 'b64:'))) {
            $token = null;
        }

        $res = $this->callTelegramApi('getMe', [], $token, $organizationId, $proxySettings);
        if (!empty($res['ok']) && !empty($res['result'])) {
            $bot = $res['result'];
            $username = (string)($bot['username'] ?? '');

            // Update bot username in DB for this org and any active config
            if ($username !== '') {
                $formattedUname = '@' . ltrim($username, '@');
                try {
                    $this->db->prepare(
                        "UPDATE crm_telegram_configs SET bot_username = :uname WHERE organization_id = :org_id"
                    )->execute([':uname' => $formattedUname, ':org_id' => $organizationId]);
                } catch (Throwable) {}
            }

            return [
                'ok' => true,
                'bot' => $bot,
            ];
        }

        return [
            'ok' => false,
            'error' => (string)($res['description'] ?? 'Ошибка проверки токена бота'),
        ];
    }

    /**
     * Set Webhook URL in Telegram.
     */
    public function setWebhook(int $organizationId, string $webhookUrl, ?string $secretToken = null): array
    {
        if ($organizationId <= 0 || strlen($webhookUrl) > 255 || preg_match('/[\x00-\x20\\\\]/', $webhookUrl)) {
            throw new RuntimeException('A valid workspace and webhook URL are required.');
        }
        $urlParts = parse_url($webhookUrl);
        if (!is_array($urlParts) || strtolower((string)($urlParts['scheme'] ?? '')) !== 'https'
            || empty($urlParts['host']) || isset($urlParts['user']) || isset($urlParts['pass'])
            || isset($urlParts['fragment']) || (int)($urlParts['port'] ?? 443) !== 443) {
            throw new RuntimeException('Telegram webhook URL must be an HTTPS URL on port 443 without embedded credentials or fragments.');
        }

        $cfg = $this->getConfig($organizationId);
        if (!$cfg || empty($cfg['bot_token'])) {
            throw new RuntimeException("Бот не настроен.");
        }

        $secret = $secretToken ?: ($cfg['webhook_secret_token'] ?: bin2hex(random_bytes(32)));
        // Validate/encrypt and persist locally before changing Telegram's remote
        // configuration. This prevents a successful remote setWebhook from
        // leaving the CRM unable to authenticate subsequent updates.
        $encryptedSecret = $this->encryptWebhookSecret($secret);
        $previous = $this->db->prepare(
            'SELECT webhook_url, webhook_secret_token FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1'
        );
        $previous->execute([':org_id' => $organizationId]);
        $oldConfig = $previous->fetch(PDO::FETCH_ASSOC);
        if (!is_array($oldConfig)) {
            throw new RuntimeException('Telegram bot configuration was not found for the active workspace.');
        }
        $persist = $this->db->prepare(
            'UPDATE crm_telegram_configs SET webhook_url = :url, webhook_secret_token = :secret, updated_at = :now WHERE organization_id = :org_id'
        );
        $persist->execute([
            ':url' => $webhookUrl,
            ':secret' => $encryptedSecret,
            ':now' => date('Y-m-d H:i:s'),
            ':org_id' => $organizationId,
        ]);
        if ($persist->rowCount() < 1) {
            throw new RuntimeException('The Telegram webhook configuration could not be saved locally.');
        }

        $res = $this->callTelegramApi('setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            'drop_pending_updates' => false,
        ], $cfg['bot_token'], $organizationId);

        if (empty($res['ok']) && isset($res['error_code'])) {
            // Telegram explicitly rejected the request, so restore the exact
            // previous local values. Transport errors without an error_code
            // are ambiguous: keep the new expected secret so a successful but
            // response-lost remote update remains verifiable; the caller can retry.
            $restore = $this->db->prepare(
                'UPDATE crm_telegram_configs SET webhook_url = :url, webhook_secret_token = :secret, updated_at = :now WHERE organization_id = :org_id AND webhook_secret_token = :expected'
            );
            $restore->execute([
                ':url' => $oldConfig['webhook_url'],
                ':secret' => $oldConfig['webhook_secret_token'],
                ':now' => date('Y-m-d H:i:s'),
                ':org_id' => $organizationId,
                ':expected' => $encryptedSecret,
            ]);
            if ($restore->rowCount() < 1) {
                throw new RuntimeException('Telegram rejected the webhook and CRM could not restore the previous local configuration.');
            }
        }

        return $res;
    }

    /**
     * Delete Webhook in Telegram (switch to polling mode).
     */
    public function deleteWebhook(int $organizationId): array
    {
        $res = $this->callTelegramApi('deleteWebhook', ['drop_pending_updates' => false], null, $organizationId);
        if (!empty($res['ok'])) {
            try {
                $this->db->prepare(
                    "UPDATE crm_telegram_configs SET webhook_url = NULL, updated_at = :now WHERE organization_id = :org_id"
                )->execute([':now' => date('Y-m-d H:i:s'), ':org_id' => $organizationId]);
            } catch (Throwable) {}
        }
        return $res;
    }

    /**
     * Get webhook info from Telegram.
     */
    public function getWebhookInfo(int $organizationId): array
    {
        return $this->callTelegramApi('getWebhookInfo', [], null, $organizationId);
    }

    /**
     * Send a Telegram text message to chat_id.
     */
    public function sendMessage(int $organizationId, int|string $chatId, string $text, string $parseMode = 'HTML'): array
    {
        return $this->callTelegramApi('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode,
        ], null, $organizationId);
    }

    /**
     * Poll updates from Telegram (long polling / manual trigger for shared hosting).
     *
     * @return array{ok: bool, processed_count: int, updates: list<array<string, mixed>>, error?: string}
     */
    public function pollUpdates(int $organizationId, int $limit = 10): array
    {
        $this->ensureSchema();
        $stmt = $this->db->prepare(
            "SELECT polling_last_update_id FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $lastUpdateId = (int)$stmt->fetchColumn();

        $params = [
            'offset' => $lastUpdateId > 0 ? $lastUpdateId + 1 : 0,
            'limit' => max(1, min(50, $limit)),
            'timeout' => 0,
        ];

        $res = $this->callTelegramApi('getUpdates', $params, null, $organizationId);
        if (empty($res['ok'])) {
            return [
                'ok' => false,
                'processed_count' => 0,
                'updates' => [],
                'error' => (string)($res['description'] ?? 'Ошибка получения обновлений'),
            ];
        }

        $updates = (array)($res['result'] ?? []);
        $processed = 0;
        $maxUpdateId = $lastUpdateId;
        $retryPending = false;
        $failedUpdateId = null;

        foreach ($updates as $upd) {
            if (!is_array($upd)) {
                // Do not move the provider cursor past an update we cannot identify.
                $retryPending = true;
                break;
            }
            $uid = (int)($upd['update_id'] ?? 0);
            if ($uid <= 0) {
                $retryPending = true;
                break;
            }
            if ($uid <= $lastUpdateId) {
                continue;
            }
            if ($uid <= $maxUpdateId) {
                // The provider sequence should be increasing. Stop instead of
                // advancing over an out-of-order or repeated unacknowledged ID.
                $retryPending = true;
                $failedUpdateId = $uid;
                break;
            }

            try {
                $handled = $this->handleUpdate($organizationId, '', $upd, true);
                if (empty($handled['ok'])) {
                    $retryPending = true;
                    $failedUpdateId = $uid;
                    break;
                }
                if (empty($handled['duplicate'])) {
                    $processed++;
                }
                // Commit/known duplicate completed successfully; only now may
                // this update ID become the persisted getUpdates offset.
                $maxUpdateId = $uid;
            } catch (Throwable) {
                // Keep this ID and all later IDs available for the next poll.
                $retryPending = true;
                $failedUpdateId = $uid;
                break;
            }
        }

        if ($maxUpdateId > $lastUpdateId) {
            try {
                $this->db->prepare(
                    "UPDATE crm_telegram_configs SET polling_last_update_id = :uid WHERE organization_id = :org_id"
                )->execute([':uid' => $maxUpdateId, ':org_id' => $organizationId]);
            } catch (Throwable) {}
        }

        // Drain durable, retryable replies even when getUpdates returned no new events.
        try { $this->flushReplyOutbox($organizationId, null, 10); } catch (Throwable) {}

        return [
            'ok' => true,
            'processed_count' => $processed,
            'retry_pending' => $retryPending,
            'failed_update_id' => $failedUpdateId,
            'updates' => $updates,
        ];
    }

    /**
     * Process an incoming Telegram update with deduplication on update_id and secret token validation.
     *
     * @param array<string, mixed> $update
     * @return array{ok: bool, duplicate: bool, action: string, message_id?: int}
     */
    public function handleUpdate(
        int $organizationId,
        string $receivedSecretToken,
        array $update,
        bool $skipSecretCheck = false
    ): array {
        $this->ensureSchema();
        if ($organizationId <= 0) {
            throw new RuntimeException('A valid workspace is required for Telegram webhook updates.');
        }
        $cfg = $this->getConfig($organizationId);

        if (!$cfg || empty($cfg['bot_token']) || empty($cfg['is_active'])) {
            throw new RuntimeException("Telegram bot configuration not found or inactive.");
        }

        // Secret token validation (unless polling)
        if (!$skipSecretCheck) {
            $expectedSecret = (string)($cfg['webhook_secret_token'] ?? '');
            if ($expectedSecret === '' || $receivedSecretToken === '' || !hash_equals($expectedSecret, $receivedSecretToken)) {
                throw new RuntimeException("Invalid Telegram webhook secret token.");
            }
        }

        $updateId = (int)($update['update_id'] ?? 0);
        if ($updateId <= 0) {
            throw new RuntimeException("Missing or invalid update_id.");
        }

        // Deduplication check
        $dupCheck = $this->db->prepare(
            "SELECT id FROM crm_telegram_updates_log WHERE organization_id = :org_id AND update_id = :uid LIMIT 1"
        );
        $dupCheck->execute([':org_id' => $organizationId, ':uid' => $updateId]);
        if ($dupCheck->fetchColumn()) {
            // Retry delivery of committed pending replies for this update.
            // Ambiguous attempts are quarantined and never auto-replayed.
            $this->flushReplyOutbox($organizationId, $updateId, 1);
            return [
                'ok' => true,
                'duplicate' => true,
                'action' => 'ignored_duplicate',
            ];
        }

        // Reserve update_id and all of its database-side effects in the same
        // transaction. The unique index serializes concurrent webhook retries:
        // a competing request either waits for this transaction and observes
        // the completed row, or can take over after this transaction rolls back.
        // A committed "processing" marker is never left behind on shared hosting.
        $this->db->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $message = $update['message'] ?? ($update['edited_message'] ?? []);
            $reservedChatId = (int)($message['chat']['id'] ?? 0);
            $payloadJson = json_encode($update, JSON_UNESCAPED_UNICODE);
            if (!is_string($payloadJson)) {
                throw new RuntimeException('Telegram update could not be encoded for the processing log.');
            }
            $reservation = $this->db->prepare(
                "INSERT INTO crm_telegram_updates_log
                 (public_id, organization_id, update_id, chat_id, action_type, payload_json, created_at)
                 VALUES (:pub_id, :org_id, :uid, :cid, 'processing', :payload, :now)"
            );
            try {
                $reservation->execute([
                    ':pub_id' => 'tgu_' . bin2hex(random_bytes(10)),
                    ':org_id' => $organizationId,
                    ':uid' => $updateId,
                    ':cid' => $reservedChatId,
                    ':payload' => $payloadJson,
                    ':now' => $now,
                ]);
            } catch (\PDOException $reservationError) {
                $this->db->rollBack();
                // Re-check the exact update after rollback. Do not misclassify
                // another integrity failure (for example a random public_id
                // collision) as a duplicate webhook.
                $duplicate = $this->db->prepare(
                    'SELECT id FROM crm_telegram_updates_log WHERE organization_id = :org_id AND update_id = :uid LIMIT 1'
                );
                $duplicate->execute([':org_id' => $organizationId, ':uid' => $updateId]);
                if ($duplicate->fetchColumn()) {
                    // This path covers a concurrent request that lost the unique
                    // reservation race after the winning transaction committed.
                    $this->flushReplyOutbox($organizationId, $updateId, 1);
                    return ['ok' => true, 'duplicate' => true, 'action' => 'ignored_duplicate'];
                }
                throw $reservationError;
            }

        $chatId = $reservedChatId;
        $messageId = (int)($message['message_id'] ?? 0);
        $text = trim((string)($message['text'] ?? ''));
        $from = (array)($message['from'] ?? []);
        $fromUsername = trim((string)($from['username'] ?? ''));
        $fromFirstName = trim((string)($from['first_name'] ?? ''));
        $fromLastName = trim((string)($from['last_name'] ?? ''));
        $senderName = trim($fromFirstName . ' ' . $fromLastName) ?: ($fromUsername ? '@' . $fromUsername : "ID {$chatId}");

        $action = 'message_received';

        if ($text !== '') {
            $dialogResult = $this->recordIncomingMessageToDialog(
                $organizationId,
                $chatId,
                $senderName,
                $fromUsername,
                $fromFirstName,
                $fromLastName,
                $text,
                $messageId
            );

            if (str_starts_with($text, '/start')) {
                $welcome = (string)($cfg['welcome_message'] ?: "Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами.");
                if ($chatId !== 0) {
                    $this->queueReply($organizationId, $updateId, $chatId, $welcome);
                }
                $action = 'start_greeted';
            } elseif (str_starts_with($text, '/bind ')) {
                $code = trim(substr($text, 6));
                $bindResult = $this->verifyUserBinding($organizationId, $chatId, $fromUsername, $code);
                if ($bindResult === 'bind_success') {
                    if ($chatId !== 0) {
                        $this->queueReply(
                            $organizationId,
                            $updateId,
                            $chatId,
                            "✅ <b>Аккаунт успешно привязан!</b>\nТеперь вы будете получать персональные уведомления CRM в этот чат."
                        );
                    }
                    $action = 'bind_success';
                } else {
                    if ($chatId !== 0) {
                        $this->queueReply(
                            $organizationId,
                            $updateId,
                            $chatId,
                            "❌ <b>Код не распознан.</b>\nПроверьте правильность одноразового кода или сгенерируйте новый в CRM."
                        );
                    }
                    $action = 'bind_failed';
                }
            } else {
                $action = 'dialog_message_received';
                $autoIntake = !empty($cfg['auto_create_intake']);

                if ($dialogResult['is_new_dialog'] && $autoIntake) {
                    $intakeCreated = $this->createIntakeItemFromMessage(
                        $organizationId,
                        $chatId,
                        $senderName,
                        $fromUsername,
                        $text,
                        $updateId,
                        $messageId,
                        $cfg['default_project_public_id'] ?? null,
                        $cfg['default_assignee_user_public_id'] ?? null
                    );
                    if ($intakeCreated) {
                        $action = 'intake_created';
                        if ($chatId !== 0) {
                            try {
                                $this->queueReply(
                                    $organizationId,
                                    $updateId,
                                    $chatId,
                                    "Спасибо, <b>" . htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8') . "</b>!\nВаше обращение принято в CRM. Наш менеджер скоро свяжется с вами."
                                );
                            } catch (Throwable) {}
                        }
                    }
                } else {
                    $this->appendMessageToIntakeItem($organizationId, (int)$dialogResult['dialog_id'], $senderName, $text);
                }
            }
        }

        // Finalize the reserved log row only after all database effects are
        // ready to commit. Any exception rolls back both receipt and CRM data.
        $complete = $this->db->prepare(
            "UPDATE crm_telegram_updates_log
             SET chat_id = :cid, action_type = :action, payload_json = :payload, created_at = :now
             WHERE organization_id = :org_id AND update_id = :uid AND action_type = 'processing'"
        );
        $complete->execute([
            ':cid' => $chatId,
            ':action' => $action,
            ':payload' => $payloadJson,
            ':now' => $now,
            ':org_id' => $organizationId,
            ':uid' => $updateId,
        ]);
        if ($complete->rowCount() !== 1) {
            throw new RuntimeException('Telegram update reservation could not be finalized.');
        }
        $this->db->commit();

        // Outbound provider calls happen only after the CRM transaction commits.
        // A failed/ambiguous delivery is retained in the outbox for safe review.
        $this->flushReplyOutbox($organizationId, $updateId, 1);

        return [
            'ok' => true,
            'duplicate' => false,
            'action' => $action,
            'message_id' => $messageId,
        ];
        } catch (Throwable $processingError) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $processingError;
        }
    }

    /** Queue a reply in the same transaction as the inbound update effects. */
    private function queueReply(int $organizationId, int $updateId, int $chatId, string $text): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO crm_telegram_reply_outbox
             (public_id, organization_id, update_id, chat_id, message_text, status, attempts, created_at, updated_at)
             VALUES (:public_id, :org_id, :update_id, :chat_id, :message_text, 'pending', 0, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)"
        );
        $stmt->execute([
            ':public_id' => 'tgo_' . bin2hex(random_bytes(10)),
            ':org_id' => $organizationId,
            ':update_id' => $updateId,
            ':chat_id' => $chatId,
            ':message_text' => $text,
        ]);
    }

    /**
     * Deliver pending replies at least once. A lost response after provider
     * acceptance cannot be distinguished from non-delivery: sendMessage has no
     * provider idempotency key. Such rows become uncertain and are not retried.
     */
    public function flushReplyOutbox(int $organizationId, ?int $updateId = null, int $limit = 10): array
    {
        if ($organizationId <= 0) throw new RuntimeException('A valid workspace is required to flush Telegram replies.');
        $limit = max(1, min(25, $limit));
        // A process can stop while a row is claimed. Never silently resend an
        // expired claim: it may already have reached Telegram. Quarantine it.
        $driver = (string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $staleCutoff = match ($driver) {
            'mysql' => 'DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 2 MINUTE)',
            'sqlite' => "datetime(CURRENT_TIMESTAMP, '-2 minutes')",
            default => throw new RuntimeException('Unsupported database driver for Telegram outbox recovery.'),
        };
        $this->db->prepare("UPDATE crm_telegram_reply_outbox SET status='uncertain', last_error='Delivery outcome is unknown after worker interruption; verify Telegram before retrying.', updated_at=CURRENT_TIMESTAMP WHERE organization_id=:org_id AND status='sending' AND attempt_started_at < {$staleCutoff}")->execute([':org_id' => $organizationId]);
        $where = "organization_id = :org_id AND status = 'pending'";
        $params = [':org_id' => $organizationId];
        if ($updateId !== null) {
            $where .= ' AND update_id = :update_id';
            $params[':update_id'] = $updateId;
        }
        $stmt = $this->db->prepare("SELECT id, chat_id, message_text FROM crm_telegram_reply_outbox WHERE {$where} ORDER BY id ASC LIMIT {$limit}");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sent = 0;
        $uncertain = 0;
        foreach ($rows as $row) {
            $claim = $this->db->prepare("UPDATE crm_telegram_reply_outbox SET status='sending', attempts=attempts+1, attempt_started_at=CURRENT_TIMESTAMP, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND organization_id=:org_id AND status='pending'");
            $claim->execute([':id' => (int)$row['id'], ':org_id' => $organizationId]);
            if ($claim->rowCount() !== 1) continue;

            $result = $this->sendMessage($organizationId, (int)$row['chat_id'], (string)$row['message_text']);
            if (!empty($result['ok'])) {
                $done = $this->db->prepare("UPDATE crm_telegram_reply_outbox SET status='sent', telegram_message_id=:message_id, last_error=NULL, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND organization_id=:org_id AND status='sending'");
                $done->execute([':message_id' => (int)($result['result']['message_id'] ?? 0) ?: null, ':id' => (int)$row['id'], ':org_id' => $organizationId]);
                $sent++;
                continue;
            }

            $http = (int)($result['_http_code'] ?? 0);
            $errorCode = (int)($result['error_code'] ?? 0);
            $unknown = !empty($result['_delivery_unknown']) || $http >= 500 || $http === 0;
            if ($unknown) {
                $state = 'uncertain';
                $uncertain++;
            } elseif ($errorCode === 429 || $http === 429) {
                $state = 'pending';
            } else {
                $state = 'failed';
            }
            $failed = $this->db->prepare("UPDATE crm_telegram_reply_outbox SET status=:status, last_error=:error, updated_at=CURRENT_TIMESTAMP WHERE id=:id AND organization_id=:org_id AND status='sending'");
            $failed->execute([
                ':status' => $state,
                ':error' => $unknown ? 'Provider delivery outcome is unknown; review before retrying.' : 'Telegram rejected the message; verify the chat and bot permissions.',
                ':id' => (int)$row['id'],
                ':org_id' => $organizationId,
            ]);
        }
        return ['sent' => $sent, 'uncertain' => $uncertain];
    }

    /**
     * Create an intake item from incoming Telegram message.
     */
    private function createIntakeItemFromMessage(
        int $organizationId,
        int $chatId,
        string $senderName,
        string $username,
        string $text,
        int $updateId,
        int $messageId,
        ?string $defaultProjectPubId,
        ?string $defaultAssigneePubId
    ): bool {
        try {
            // External messages must never be assigned to a guessed/default
            // workspace. A missing or stale bot workspace fails closed.
            if ($organizationId <= 0) {
                return false;
            }

            $orgStmt = $this->db->prepare('SELECT id FROM organizations WHERE id = :org_id LIMIT 1');
            $orgStmt->execute([':org_id' => $organizationId]);
            if ((int)$orgStmt->fetchColumn() !== $organizationId) {
                return false;
            }

            // Check if intake_items table exists
            $check = $this->db->query("SELECT 1 FROM intake_items LIMIT 1");
            if ($check === false) {
                return false;
            }

            $preview = mb_substr($text, 0, 80);
            $title = "Telegram: " . $senderName . " — " . $preview;
            if (mb_strlen($text) > 80) {
                $title .= '…';
            }

            $userHandle = $username ? '@' . $username : 'нет';
            $description = "Входящее обращение из Telegram-бота:\n"
                . "Отправитель: {$senderName} ({$userHandle})\n"
                . "Chat ID: {$chatId}\n"
                . "Message ID: {$messageId}\n\n"
                . "Сообщение:\n" . $text;

            $publicId = 'iin_' . bin2hex(random_bytes(10));
            $now = gmdate('Y-m-d H:i:s');
            $externalId = "tg_{$chatId}_{$messageId}";
            $sourceRef = "telegram:{$chatId}";

            // Without tenant ownership storage, do not create an unscoped item.
            try {
                $this->db->query("SELECT organization_id FROM intake_items LIMIT 0");
            } catch (Throwable) {
                return false;
            }

            $ins = $this->db->prepare(
                "INSERT INTO intake_items
                 (public_id, title, description, status, source_type, source_ref, external_source, external_id, creator_user_id, organization_id, created_at, updated_at)
                 VALUES (:pub_id, :title, :desc, 'pending', 'webhook', :sref, 'telegram', :ext_id, 1, :org_id, :created_at, :updated_at)"
            );
            $ins->execute([
                ':pub_id' => $publicId,
                ':title' => $title,
                ':desc' => $description,
                ':sref' => $sourceRef,
                ':ext_id' => $externalId,
                ':org_id' => $organizationId,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            return true;
        } catch (Throwable $e) {
            error_log('[TelegramBot] createIntakeItem failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verify staff user binding code.
     */
    private function verifyUserBinding(int $organizationId, int $chatId, string $username, string $code): string
    {
        $code = strtoupper(trim($code));
        $stmt = $this->db->prepare(
            "SELECT id FROM crm_telegram_user_bindings
             WHERE organization_id = :org_id AND verification_code = :code AND is_verified = 0 LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':code' => $code]);
        $bindingId = $stmt->fetchColumn();

        if (!$bindingId) {
            return 'bind_failed_invalid_code';
        }

        $now = date('Y-m-d H:i:s');
        $upd = $this->db->prepare(
            "UPDATE crm_telegram_user_bindings
             SET is_verified = 1, telegram_chat_id = :cid, telegram_username = :uname, verification_code = NULL, updated_at = :now
             WHERE id = :id"
        );
        $upd->execute([
            ':cid' => $chatId,
            ':uname' => $username ? '@' . ltrim($username, '@') : null,
            ':now' => $now,
            ':id' => $bindingId,
        ]);

        return 'bind_success';
    }

    /**
     * Generate binding code for a staff member.
     */
    public function generateBindingCode(int $organizationId, string $userPublicId): string
    {
        $code = strtoupper(bin2hex(random_bytes(4)));
        $now = date('Y-m-d H:i:s');
        $pubId = 'tgb_' . bin2hex(random_bytes(10));

        // Delete any existing unverified codes for this user
        $del = $this->db->prepare(
            "DELETE FROM crm_telegram_user_bindings
             WHERE organization_id = :org_id AND user_public_id = :uid AND is_verified = 0"
        );
        $del->execute([':org_id' => $organizationId, ':uid' => $userPublicId]);

        $stmt = $this->db->prepare(
            "INSERT INTO crm_telegram_user_bindings
             (public_id, organization_id, user_public_id, telegram_chat_id, verification_code, is_verified, created_at, updated_at)
             VALUES (:pub_id, :org_id, :uid, 0, :code, 0, :created_at, :updated_at)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':uid' => $userPublicId,
            ':code' => $code,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        return $code;
    }

    /**
     * List user bindings for organization.
     */
    public function listBindings(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, user_public_id, telegram_chat_id, telegram_username, is_verified, created_at, updated_at
             FROM crm_telegram_user_bindings
             WHERE organization_id = :org_id
             ORDER BY id DESC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $r): array {
            return [
                'public_id' => (string)$r['public_id'],
                'user_public_id' => (string)$r['user_public_id'],
                'telegram_chat_id' => (int)$r['telegram_chat_id'],
                'telegram_username' => $r['telegram_username'] ?? null,
                'is_verified' => !empty($r['is_verified']),
                'created_at' => $r['created_at'],
                'updated_at' => $r['updated_at'],
            ];
        }, $rows);
    }

    /**
     * Delete user binding.
     */
    public function deleteBinding(int $organizationId, string $publicId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM crm_telegram_user_bindings WHERE organization_id = :org_id AND public_id = :pub_id"
        );
        $stmt->execute([':org_id' => $organizationId, ':pub_id' => $publicId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Send notification to a staff member by user public ID.
     */
    public function notifyUser(int $organizationId, string $userPublicId, string $message): array
    {
        $stmt = $this->db->prepare(
            "SELECT telegram_chat_id FROM crm_telegram_user_bindings
             WHERE organization_id = :org_id AND user_public_id = :uid AND is_verified = 1 LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':uid' => $userPublicId]);
        $chatId = (int)$stmt->fetchColumn();

        if ($chatId === 0) {
            return [
                'ok' => false,
                'description' => 'У сотрудника не привязан подтверждённый Telegram аккаунт.',
            ];
        }

        return $this->sendMessage($organizationId, $chatId, $message);
    }

    /**
     * Get recent update logs.
     */
    public function getRecentUpdates(int $organizationId, int $limit = 15): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.public_id, u.update_id, u.chat_id, u.action_type, u.payload_json, u.created_at,
                    (SELECT o.status FROM crm_telegram_reply_outbox o WHERE o.organization_id=u.organization_id AND o.update_id=u.update_id LIMIT 1) AS reply_status
             FROM crm_telegram_updates_log u
             WHERE u.organization_id = :org_id
             ORDER BY u.id DESC
             LIMIT " . max(1, min(100, $limit))
        );
        $stmt->execute([':org_id' => $organizationId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function (array $r): array {
            $payload = json_decode((string)$r['payload_json'], true);
            $msg = is_array($payload) ? ($payload['message'] ?? ($payload['edited_message'] ?? [])) : [];
            $text = is_array($msg) ? (string)($msg['text'] ?? '') : '';
            return [
                'public_id' => (string)$r['public_id'],
                'update_id' => (int)$r['update_id'],
                'chat_id' => (int)$r['chat_id'],
                'action_type' => (string)$r['action_type'],
                'reply_status' => isset($r['reply_status']) ? (string)$r['reply_status'] : null,
                'message_preview' => mb_substr($text, 0, 100),
                'created_at' => (string)$r['created_at'],
                'payload' => $payload,
            ];
        }, $rows);
    }

    /**
     * Get comprehensive operational status for organization.
     */
    public function getStatus(int $organizationId): array
    {
        $this->ensureSchema();

        if ($organizationId <= 0) {
            throw new RuntimeException('A valid workspace is required to read Telegram status.');
        }

        $cfg = $this->getConfig($organizationId);
        $env = $this->detectEnvConfig();

        $configured = ($cfg !== null && !empty($cfg['bot_token']));
        $isActive = $configured && !empty($cfg['is_active']);

        // Stats
        $totalUpdates = 0;
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM crm_telegram_updates_log WHERE organization_id = :org_id");
        if ($stmt->execute([':org_id' => $organizationId])) {
            $totalUpdates = (int)$stmt->fetchColumn();
        }

        $verifiedBindings = 0;
        $pendingBindings = 0;
        $stmt = $this->db->prepare(
            "SELECT is_verified, COUNT(*) as cnt FROM crm_telegram_user_bindings WHERE organization_id = :org_id GROUP BY is_verified"
        );
        if ($stmt->execute([':org_id' => $organizationId])) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($row['is_verified'])) {
                    $verifiedBindings = (int)$row['cnt'];
                } else {
                    $pendingBindings = (int)$row['cnt'];
                }
            }
        }

        $botUsername = $cfg['bot_username'] ?? ($env['bot_username'] ?: null);
        $statusLabel = $isActive ? 'Подключён · Активен' : 'Установлен · не подключён';

        $totalDialogs = 0;
        $unreadDialogs = 0;
        try {
            $dlgStmt = $this->db->prepare("SELECT COUNT(*), SUM(CASE WHEN unread_count > 0 THEN 1 ELSE 0 END) FROM crm_telegram_dialogs WHERE organization_id = :org_id");
            if ($dlgStmt->execute([':org_id' => $organizationId])) {
                $r = $dlgStmt->fetch(PDO::FETCH_NUM);
                $totalDialogs = (int)($r[0] ?? 0);
                $unreadDialogs = (int)($r[1] ?? 0);
            }
        } catch (Throwable) {}

        return [
            'configured' => $configured,
            'is_active' => $isActive,
            'organization_id' => $organizationId,
            'status_label' => $statusLabel,
            'status_color' => $isActive ? 'success' : 'warning',
            'bot_username' => $botUsername,
            'bot_token_masked' => $cfg['bot_token_masked'] ?? '',
            'webhook_url' => $cfg['webhook_url'] ?? null,
            'proxy_type' => $cfg['proxy_type'] ?? 'none',
            'proxy_host' => $cfg['proxy_host'] ?? null,
            'proxy_port' => $cfg['proxy_port'] ?? null,
            'proxy_username' => $cfg['proxy_username'] ?? null,
            'proxy_password_set' => !empty($cfg['proxy_password_set']),
            'webhook_secret_set' => !empty($cfg['webhook_secret_token']),
            'auto_create_intake' => $cfg ? !empty($cfg['auto_create_intake']) : true,
            'welcome_message' => $cfg['welcome_message'] ?? "Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами.",
            'has_env_token' => $env['has_env'],
            'total_updates' => $totalUpdates,
            'total_dialogs' => $totalDialogs,
            'unread_dialogs' => $unreadDialogs,
            'verified_bindings' => $verifiedBindings,
            'pending_bindings' => $pendingBindings,
            'public_id' => $cfg['public_id'] ?? null,
        ];
    }

    /**
     * Record incoming customer message to the persistent dialog thread.
     */
    public function recordIncomingMessageToDialog(
        int $organizationId,
        int $chatId,
        string $senderName,
        string $username,
        string $firstName,
        string $lastName,
        string $text,
        int $messageId
    ): array {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');

        // Check if dialog exists for this org and chat
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_dialogs WHERE organization_id = :org_id AND chat_id = :cid LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':cid' => $chatId]);
        $dialog = $stmt->fetch(PDO::FETCH_ASSOC);

        $isNew = false;
        if (!$dialog) {
            $isNew = true;
            $dialogPubId = 'tgd_' . bin2hex(random_bytes(10));
            $insDlg = $this->db->prepare(
                "INSERT INTO crm_telegram_dialogs
                 (public_id, organization_id, chat_id, first_name, last_name, username, title, last_message_text, last_message_at, unread_count, status, created_at, updated_at)
                 VALUES (:pub_id, :org_id, :cid, :fn, :ln, :un, :title, :lmsg, :lmsg_at, 1, 'open', :created_at, :updated_at)"
            );
            $insDlg->execute([
                ':pub_id' => $dialogPubId,
                ':org_id' => $organizationId,
                ':cid' => $chatId,
                ':fn' => $firstName ?: null,
                ':ln' => $lastName ?: null,
                ':un' => $username ?: null,
                ':title' => $senderName,
                ':lmsg' => mb_substr($text, 0, 255),
                ':lmsg_at' => $now,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);
            $dialogId = (int)$this->db->lastInsertId();
        } else {
            $dialogId = (int)$dialog['id'];
            $dialogPubId = (string)$dialog['public_id'];
            $newStatus = in_array((string)$dialog['status'], ['resolved', 'archived'], true) ? 'open' : (string)$dialog['status'];
            $updDlg = $this->db->prepare(
                "UPDATE crm_telegram_dialogs
                 SET last_message_text = :lmsg,
                     last_message_at = :lmsg_at,
                     unread_count = unread_count + 1,
                     status = :status,
                     first_name = COALESCE(:fn, first_name),
                     last_name = COALESCE(:ln, last_name),
                     username = COALESCE(:un, username),
                     updated_at = :now
                 WHERE id = :id"
            );
            $updDlg->execute([
                ':lmsg' => mb_substr($text, 0, 255),
                ':lmsg_at' => $now,
                ':status' => $newStatus,
                ':fn' => $firstName ?: null,
                ':ln' => $lastName ?: null,
                ':un' => $username ?: null,
                ':now' => $now,
                ':id' => $dialogId,
            ]);
            if (in_array((string)$dialog['status'], ['resolved', 'archived'], true)) {
                $isNew = true;
            }
        }

        // Insert message record
        $msgPubId = 'tgm_' . bin2hex(random_bytes(10));
        $insMsg = $this->db->prepare(
            "INSERT INTO crm_telegram_messages
             (public_id, organization_id, dialog_id, chat_id, direction, sender_type, sender_name, telegram_message_id, message_text, delivery_status, created_at)
             VALUES (:pub_id, :org_id, :did, :cid, 'inbound', 'client', :sname, :mid, :text, 'delivered', :now)"
        );
        $insMsg->execute([
            ':pub_id' => $msgPubId,
            ':org_id' => $organizationId,
            ':did' => $dialogId,
            ':cid' => $chatId,
            ':sname' => $senderName,
            ':mid' => $messageId ?: null,
            ':text' => $text,
            ':now' => $now,
        ]);

        return [
            'dialog_id' => $dialogId,
            'dialog_public_id' => $dialogPubId,
            'message_public_id' => $msgPubId,
            'is_new_dialog' => $isNew,
        ];
    }

    /**
     * Append incoming follow-up message to active Intake item.
     */
    private function appendMessageToIntakeItem(int $organizationId, int $dialogId, string $senderName, string $text): void
    {
        try {
            $dStmt = $this->db->prepare("SELECT linked_intake_id FROM crm_telegram_dialogs WHERE id = :did LIMIT 1");
            $dStmt->execute([':did' => $dialogId]);
            $intakeId = (int)$dStmt->fetchColumn();
            if ($intakeId > 0) {
                $iStmt = $this->db->prepare("SELECT description FROM intake_items WHERE id = :id LIMIT 1");
                $iStmt->execute([':id' => $intakeId]);
                $curDesc = (string)$iStmt->fetchColumn();
                $time = date('H:i');
                $newDesc = $curDesc . "\n\n[{$time}] {$senderName}:\n" . $text;
                $now = date('Y-m-d H:i:s');
                $this->db->prepare(
                    "UPDATE intake_items SET description = :desc, updated_at = :now WHERE id = :id"
                )->execute([':desc' => $newDesc, ':now' => $now, ':id' => $intakeId]);
            }
        } catch (Throwable) {}
    }

    /**
     * List client dialogs.
     */
    public function listDialogs(
        int $organizationId,
        string $status = 'all',
        string $search = '',
        int $limit = 50,
        int $offset = 0
    ): array {
        $this->ensureSchema();
        $this->backfillExistingUpdates($organizationId);

        $where = ["d.organization_id = :org_id"];
        $params = [':org_id' => $organizationId];

        if ($status !== 'all' && in_array($status, ['open', 'in_progress', 'resolved', 'archived'], true)) {
            $where[] = "d.status = :status";
            $params[':status'] = $status;
        }

        if ($search !== '') {
            $where[] = "(d.title LIKE :search OR d.username LIKE :search OR d.last_message_text LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT d.*, t.public_id AS linked_task_public_id, t.title AS linked_task_title
                FROM crm_telegram_dialogs d
                LEFT JOIN tasks t ON t.id = d.linked_task_id
                WHERE " . implode(' AND ', $where) . "
                ORDER BY d.last_message_at DESC
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$it) {
            $it['chat_number'] = (string)($it['chat_id'] ?? '');
            $it['chat_ref'] = (string)($it['chat_id'] ?? '');
            $it['task_ref'] = (string)($it['linked_task_id'] ?? '');
            $it['intake_ref'] = (string)($it['linked_intake_id'] ?? '');
        }
        unset($it);

        $unreadStmt = $this->db->prepare("SELECT SUM(unread_count) FROM crm_telegram_dialogs WHERE organization_id = :org_id");
        $unreadStmt->execute([':org_id' => $organizationId]);
        $totalUnread = (int)$unreadStmt->fetchColumn();

        return [
            'dialogs' => $items,
            'total_unread' => $totalUnread,
        ];
    }

    /**
     * Get single dialog with message thread.
     */
    public function getDialog(int $organizationId, string $dialogPubId): ?array
    {
        $this->ensureSchema();

        $stmt = $this->db->prepare(
            "SELECT d.*, t.public_id AS linked_task_public_id, t.title AS linked_task_title
             FROM crm_telegram_dialogs d
             LEFT JOIN tasks t ON t.id = d.linked_task_id
             WHERE d.organization_id = :org_id AND d.public_id = :pub_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':pub_id' => $dialogPubId]);
        $dialog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dialog) {
            return null;
        }

        $dialog['chat_number'] = (string)($dialog['chat_id'] ?? '');
        $dialog['chat_ref'] = (string)($dialog['chat_id'] ?? '');
        $dialog['task_ref'] = (string)($dialog['linked_task_id'] ?? '');
        $dialog['intake_ref'] = (string)($dialog['linked_intake_id'] ?? '');

        if ((int)$dialog['unread_count'] > 0) {
            $this->db->prepare("UPDATE crm_telegram_dialogs SET unread_count = 0 WHERE id = :id")->execute([':id' => $dialog['id']]);
            $dialog['unread_count'] = 0;
        }

        $msgStmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_messages
             WHERE dialog_id = :did
             ORDER BY id ASC"
        );
        $msgStmt->execute([':did' => $dialog['id']]);
        $dialog['messages'] = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $dialog;
    }

    /**
     * Send outbound reply to Telegram client from CRM.
     */
    public function replyDialog(
        int $organizationId,
        string $dialogPubId,
        string $text,
        ?int $staffUserId = null,
        string $staffName = 'Менеджер CRM'
    ): array {
        $this->ensureSchema();
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException("Текст сообщения не может быть пустым.");
        }

        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_dialogs WHERE organization_id = :org_id AND public_id = :pub_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':pub_id' => $dialogPubId]);
        $dialog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dialog) {
            throw new RuntimeException("Диалог не найден.");
        }

        $chatId = (int)$dialog['chat_id'];
        $dialogId = (int)$dialog['id'];

        // Send via official Telegram Bot API (or record failure if API unreachable)
        $deliveryStatus = 'sent';
        $tgMsgId = null;
        $errorMsg = null;
        try {
            $res = $this->sendMessage($organizationId, $chatId, $text);
            $tgMsgId = (int)($res['result']['message_id'] ?? 0);
        } catch (\Throwable $e) {
            $deliveryStatus = 'failed';
            $errorMsg = $e->getMessage();
        }

        $now = date('Y-m-d H:i:s');
        $msgPubId = 'tgm_' . bin2hex(random_bytes(10));

        $insMsg = $this->db->prepare(
            "INSERT INTO crm_telegram_messages
             (public_id, organization_id, dialog_id, chat_id, direction, sender_type, sender_user_id, sender_name, telegram_message_id, message_text, delivery_status, created_at)
             VALUES (:pub_id, :org_id, :did, :cid, 'outbound', 'staff', :uid, :sname, :mid, :text, :deliv, :now)"
        );
        $insMsg->execute([
            ':pub_id' => $msgPubId,
            ':org_id' => $organizationId,
            ':did' => $dialogId,
            ':cid' => $chatId,
            ':uid' => $staffUserId,
            ':sname' => $staffName,
            ':mid' => $tgMsgId ?: null,
            ':text' => $text,
            ':deliv' => $deliveryStatus,
            ':now' => $now,
        ]);

        $this->db->prepare(
            "UPDATE crm_telegram_dialogs
             SET last_message_text = :lmsg,
                 last_message_at = :lmsg_at,
                 unread_count = 0,
                 status = CASE WHEN status = 'open' THEN 'in_progress' ELSE status END,
                 updated_at = :upd_at
             WHERE id = :id"
        )->execute([
            ':lmsg' => mb_substr($text, 0, 255),
            ':lmsg_at' => $now,
            ':upd_at' => $now,
            ':id' => $dialogId,
        ]);

        if ($deliveryStatus === 'failed') {
            throw new RuntimeException("Не удалось доставить сообщение в Telegram: " . $errorMsg);
        }

        return [
            'public_id' => $msgPubId,
            'direction' => 'outbound',
            'sender_type' => 'staff',
            'sender_name' => $staffName,
            'message_text' => $text,
            'delivery_status' => $deliveryStatus,
            'created_at' => $now,
        ];
    }

    /**
     * Update status of dialog.
     */
    public function updateDialogStatus(int $organizationId, string $dialogPubId, string $status): bool
    {
        $this->ensureSchema();
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'archived'], true)) {
            throw new RuntimeException("Недопустимый статус диалога: {$status}");
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            "UPDATE crm_telegram_dialogs
             SET status = :status, updated_at = :now
             WHERE organization_id = :org_id AND public_id = :pub_id"
        );
        return $stmt->execute([':status' => $status, ':now' => $now, ':org_id' => $organizationId, ':pub_id' => $dialogPubId]) && $stmt->rowCount() > 0;
    }

    /**
     * Create CRM Task from Telegram dialog.
     */
    public function createTaskFromDialog(
        int $organizationId,
        string $dialogPubId,
        string $title,
        string $description = '',
        ?int $projectId = null,
        string $priority = 'normal',
        ?int $assigneeId = null,
        ?int $creatorUserId = 1
    ): array {
        $this->ensureSchema();

        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_dialogs WHERE organization_id = :org_id AND public_id = :pub_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId, ':pub_id' => $dialogPubId]);
        $dialog = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$dialog) {
            throw new RuntimeException("Диалог не найден.");
        }

        if ($projectId !== null) {
            $projectStmt = $this->db->prepare(
                'SELECT id FROM projects WHERE id = :project_id AND organization_id = :org_id LIMIT 1'
            );
            $projectStmt->execute([':project_id' => $projectId, ':org_id' => $organizationId]);
            if (!$projectStmt->fetchColumn()) {
                throw new RuntimeException('Проект не найден в активном рабочем пространстве.');
            }
        }
        if ($assigneeId !== null) {
            $assigneeStmt = $this->db->prepare(
                'SELECT 1 FROM organization_memberships WHERE user_id = :user_id AND organization_id = :org_id LIMIT 1'
            );
            $assigneeStmt->execute([':user_id' => $assigneeId, ':org_id' => $organizationId]);
            if (!$assigneeStmt->fetchColumn()) {
                throw new RuntimeException('Исполнитель не входит в активное рабочее пространство.');
            }
        }

        $title = trim($title);
        if ($title === '') {
            $clientName = $dialog['title'] ?: ($dialog['username'] ? '@' . $dialog['username'] : 'Клиент');
            $title = "Обращение из Telegram: " . $clientName;
        }

        $msgStmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_messages WHERE dialog_id = :did ORDER BY id ASC LIMIT 50"
        );
        $msgStmt->execute([':did' => $dialog['id']]);
        $messages = $msgStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $transcriptLines = [];
        foreach ($messages as $m) {
            $sender = $m['direction'] === 'inbound' ? ($m['sender_name'] ?: 'Клиент') : ($m['sender_name'] ?: 'Менеджер CRM');
            $time = substr((string)$m['created_at'], 11, 5);
            $transcriptLines[] = "[{$time}] {$sender}: " . $m['message_text'];
        }
        $transcript = implode("\n", $transcriptLines);

        if ($description !== '') {
            $desc = $description;
        } else {
            $clientHandle = $dialog['username'] ? '@' . $dialog['username'] : 'нет';
            $desc = "### Обращение из Telegram-бота\n\n"
                  . "| Параметр | Значение |\n"
                  . "| --- | --- |\n"
                  . "| Клиент | " . ($dialog['title'] ?: 'Клиент') . " |\n"
                  . "| Telegram Username | {$clientHandle} |\n"
                  . "| Chat ID | `{$dialog['chat_id']}` |\n"
                  . "| Диалог | [Открыть в CRM](index.php?route=module-telegram-bot&dialog={$dialogPubId}) |\n\n"
                  . "#### История переписки:\n```\n" . $transcript . "\n```";
        }

        $now = date('Y-m-d H:i:s');
        $taskPubId = 'tsk_' . bin2hex(random_bytes(10));

        $ins = $this->db->prepare(
            "INSERT INTO tasks
             (public_id, project_id, title, description, status_code, priority_code, assignee_user_id, creator_user_id, organization_id, source_type, source_id, created_at, updated_at)
             VALUES (:pub, :pid, :title, :desc, 'todo', :priority, :assignee, :creator, :org_id, 'telegram', :src_id, :created_at, :updated_at)"
        );
        $ins->execute([
            ':pub' => $taskPubId,
            ':pid' => $projectId ?: null,
            ':title' => $title,
            ':desc' => $desc,
            ':priority' => in_array($priority, ['low', 'normal', 'high', 'urgent'], true) ? $priority : 'normal',
            ':assignee' => $assigneeId ?: null,
            ':creator' => $creatorUserId ?: 1,
            ':org_id' => $organizationId,
            ':src_id' => $dialogPubId,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
        $taskId = (int)$this->db->lastInsertId();

        $this->db->prepare(
            "UPDATE crm_telegram_dialogs SET linked_task_id = :tid, status = 'in_progress', updated_at = :now WHERE id = :did"
        )->execute([':tid' => $taskId, ':now' => $now, ':did' => $dialog['id']]);

        $sysPubId = 'tgm_' . bin2hex(random_bytes(10));
        $this->db->prepare(
            "INSERT INTO crm_telegram_messages
             (public_id, organization_id, dialog_id, chat_id, direction, sender_type, sender_name, message_text, delivery_status, created_at)
             VALUES (:pub, :org, :did, :cid, 'outbound', 'system', 'CRM', :text, 'delivered', :now)"
        )->execute([
            ':pub' => $sysPubId,
            ':org' => $organizationId,
            ':did' => $dialog['id'],
            ':cid' => $dialog['chat_id'],
            ':text' => "📋 Создана задача в CRM: {$title} (ID #{$taskId})",
            ':now' => $now,
        ]);

        return [
            'task_id' => $taskId,
            'task_public_id' => $taskPubId,
            'title' => $title,
            'status' => 'todo',
        ];
    }

    /**
     * List accessible organizations for staff user.
     */
    public function listOrganizationsForUser(int $userId, bool $isRoot = false): array
    {
        try {
            if ($isRoot) {
                $stmt = $this->db->query('SELECT id, public_id, title, slug FROM organizations ORDER BY id ASC LIMIT 50');
            } else {
                if ($userId <= 0) {
                    return [];
                }
                $stmt = $this->db->prepare(
                    "SELECT o.id, o.public_id, o.title, o.slug
                     FROM organization_memberships om
                     JOIN organizations o ON o.id = om.organization_id
                     WHERE om.user_id = :uid
                     ORDER BY o.id ASC"
                );
                $stmt->execute([':uid' => $userId]);
            }
            $raw = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];

            $items = [];
            foreach ($raw as $r) {
                $title = (string)($r['title'] ?? $r['name'] ?? ('Организация #' . ($r['id'] ?? '')));
                $items[] = [
                    'id' => (int)($r['id'] ?? 0),
                    'org_ref' => (string)($r['id'] ?? 0),
                    'public_id' => (string)($r['public_id'] ?? ''),
                    'organization_public_id' => (string)($r['public_id'] ?? ('org_' . ($r['id'] ?? ''))),
                    'title' => $title,
                    'name' => $title,
                    'slug' => (string)($r['slug'] ?? ''),
                ];
            }
            return $items;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * List projects in organization for task creation.
     */
    public function listProjectsForOrg(int $organizationId): array
    {
        if ($organizationId <= 0) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT id, public_id, title FROM projects WHERE organization_id = :org_id AND archived_at IS NULL ORDER BY id ASC LIMIT 50'
            );
            $stmt->execute([':org_id' => $organizationId]);
            $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $items = [];
            foreach ($raw as $r) {
                $title = (string)($r['title'] ?? $r['name'] ?? ('Проект #' . $r['id']));
                $items[] = [
                    'id' => (int)$r['id'],
                    'public_id' => (string)($r['public_id'] ?? ''),
                    'title' => $title,
                    'name' => $title,
                ];
            }
            return $items;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Backfill updates from crm_telegram_updates_log into dialogs and messages.
     */
    public function backfillExistingUpdates(int $organizationId): int
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM crm_telegram_updates_log
                 WHERE organization_id = :org_id
                 ORDER BY id ASC"
            );
            $stmt->execute([':org_id' => $organizationId]);
            $updates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $imported = 0;

            foreach ($updates as $u) {
                $payload = json_decode((string)$u['payload_json'], true);
                if (!is_array($payload)) {
                    continue;
                }
                $msg = $payload['message'] ?? ($payload['edited_message'] ?? []);
                $chatId = (int)($msg['chat']['id'] ?? $u['chat_id'] ?? 0);
                $text = trim((string)($msg['text'] ?? ''));
                if ($chatId === 0 || $text === '') {
                    continue;
                }

                $msgId = (int)($msg['message_id'] ?? 0);
                $check = $this->db->prepare(
                    "SELECT id FROM crm_telegram_messages WHERE organization_id = :org_id AND chat_id = :cid AND message_text = :txt LIMIT 1"
                );
                $check->execute([':org_id' => $organizationId, ':cid' => $chatId, ':txt' => $text]);
                if ($check->fetchColumn()) {
                    continue;
                }

                $from = (array)($msg['from'] ?? []);
                $fn = trim((string)($from['first_name'] ?? ''));
                $ln = trim((string)($from['last_name'] ?? ''));
                $un = trim((string)($from['username'] ?? ''));
                $senderName = trim($fn . ' ' . $ln) ?: ($un ? '@' . $un : "ID {$chatId}");

                $this->recordIncomingMessageToDialog(
                    $organizationId,
                    $chatId,
                    $senderName,
                    $un,
                    $fn,
                    $ln,
                    $text,
                    $msgId
                );
                $imported++;
            }
            return $imported;
        } catch (Throwable) {
            return 0;
        }
    }
}
