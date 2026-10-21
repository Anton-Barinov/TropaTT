<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Service;

use Api\System\Library\Security\KeyGuard;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Service managing Telegram Bot webhook/polling processing, staff binding,
 * customer intake creation, and outbound notifications for TropaTT CRM.
 */
final class TelegramBotService
{
    private PDO $db;
    private bool $schemaEnsured = false;

    public function __construct(PDO $db)
    {
        $this->db = $db;
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
            }
        } catch (Throwable) {
            // Ignore schema alter errors if already present or restricted
        }
    }

    /**
     * Encrypt bot token using AES-256-GCM (with KeyGuard or safe openssl fallback).
     */
    private function encryptToken(string $token): string
    {
        if (class_exists(KeyGuard::class) && method_exists(KeyGuard::class, 'encrypt')) {
            try {
                return KeyGuard::encrypt($token);
            } catch (Throwable) {}
        }

        $key = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? 'crm-telegram-secret-key-32bytes!'));
        $keyBin = hash('sha256', $key, true);

        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($token, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($cipher !== false) {
                return 'gcm:' . base64_encode($iv . $tag . $cipher);
            }
        }
        return 'b64:' . base64_encode($token);
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

        // Already plain bot token (e.g. 123456789:AA...)
        if (preg_match('/^[0-9]{8,12}:[a-zA-Z0-9_-]{30,50}$/', $encrypted)) {
            return $encrypted;
        }

        if (str_starts_with($encrypted, 'gcm:')) {
            $blob = base64_decode(substr($encrypted, 4), true);
            if ($blob !== false && strlen($blob) > 28) {
                $iv = substr($blob, 0, 12);
                $tag = substr($blob, 12, 16);
                $cipher = substr($blob, 28);
                $candidateKeys = array_unique(array_filter([
                    (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')),
                    'crm-telegram-secret-key-32bytes!',
                ]));
                foreach ($candidateKeys as $key) {
                    $keyBin = hash('sha256', $key, true);
                    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
                    if ($plain !== false && is_string($plain) && $plain !== '') {
                        return trim($plain);
                    }
                }
            }
            return '';
        }

        if (class_exists(KeyGuard::class) && method_exists(KeyGuard::class, 'decrypt')) {
            try {
                $plain = KeyGuard::decrypt($encrypted);
                if ($plain !== false && is_string($plain) && $plain !== '') {
                    return trim($plain);
                }
            } catch (Throwable) {}
        }

        if (str_starts_with($encrypted, 'b64:')) {
            $raw = base64_decode(substr($encrypted, 4), true);
            return $raw !== false && is_string($raw) ? trim($raw) : '';
        }

        $raw = base64_decode($encrypted, true);
        if ($raw !== false && is_string($raw) && preg_match('/^[0-9]{8,12}:[a-zA-Z0-9_-]{30,50}$/', $raw)) {
            return trim($raw);
        }

        return '';
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
        $this->ensureSchema();
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            try {
                $anyStmt = $this->db->query("SELECT * FROM crm_telegram_configs WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
                $row = $anyStmt ? $anyStmt->fetch(PDO::FETCH_ASSOC) : null;
            } catch (Throwable) {}
        }

        if (!$row) {
            // Check if available from .env as fallback
            $env = $this->detectEnvConfig();
            if ($env['has_env']) {
                return [
                    'public_id' => null,
                    'organization_id' => $organizationId,
                    'bot_token' => $env['bot_token'],
                    'bot_token_masked' => $env['masked_token'],
                    'webhook_secret_token' => $env['webhook_secret'],
                    'webhook_url' => null,
                    'bot_username' => $env['bot_username'],
                    'auto_create_intake' => true,
                    'welcome_message' => "Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами.",
                    'default_project_public_id' => null,
                    'default_assignee_user_public_id' => null,
                    'is_active' => true,
                    'is_from_env' => true,
                ];
            }
            return null;
        }

        $decryptedToken = $this->decryptToken((string)$row['bot_token_encrypted']);
        if ($decryptedToken === '') {
            try {
                $anyStmt = $this->db->prepare("SELECT * FROM crm_telegram_configs WHERE is_active = 1 AND id != :curr_id ORDER BY updated_at DESC LIMIT 1");
                $anyStmt->execute([':curr_id' => (int)($row['id'] ?? 0)]);
                $fallbackRow = $anyStmt->fetch(PDO::FETCH_ASSOC);
                if ($fallbackRow) {
                    $fallbackToken = $this->decryptToken((string)$fallbackRow['bot_token_encrypted']);
                    if ($fallbackToken !== '') {
                        $row = $fallbackRow;
                        $decryptedToken = $fallbackToken;
                    }
                }
            } catch (Throwable) {}
        }

        return [
            'public_id' => (string)$row['public_id'],
            'organization_id' => (int)$row['organization_id'],
            'bot_token' => $decryptedToken,
            'bot_token_masked' => $this->maskToken($decryptedToken),
            'webhook_secret_token' => (string)$row['webhook_secret_token'],
            'webhook_url' => $row['webhook_url'] ?? null,
            'bot_username' => $row['bot_username'] ?? null,
            'auto_create_intake' => !empty($row['auto_create_intake'] ?? 1),
            'welcome_message' => $row['welcome_message'] ?? "Здравствуйте! Я официальный бот TropaTT CRM. Напишите ваш вопрос или заявку, и наша команда свяжется с вами.",
            'default_project_public_id' => $row['default_project_public_id'] ?? null,
            'default_assignee_user_public_id' => $row['default_assignee_user_public_id'] ?? null,
            'is_active' => !empty($row['is_active']),
            'is_from_env' => false,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
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
        ?string $webhookUrl = null
    ): string {
        $this->ensureSchema();
        $now = date('Y-m-d H:i:s');

        // Check if existing for current org
        $stmt = $this->db->prepare(
            "SELECT * FROM crm_telegram_configs WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            try {
                $anyStmt = $this->db->query("SELECT * FROM crm_telegram_configs WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 1");
                $existing = $anyStmt ? $anyStmt->fetch(PDO::FETCH_ASSOC) : null;
            } catch (Throwable) {}
        }

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
            $webhookSecretToken = $existing['webhook_secret_token'] ?? bin2hex(random_bytes(16));
        }

        // Auto-discover bot username if empty
        if ($botUsername === null || trim($botUsername) === '') {
            try {
                $plain = $this->decryptToken($encryptedToken);
                if ($plain !== '') {
                    $me = $this->callTelegramApi('getMe', [], $plain, $organizationId);
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
                     is_active = 1,
                     updated_at = :now
                 WHERE id = :id"
            );
            $upd->execute([
                ':tok' => $encryptedToken,
                ':sec' => $webhookSecretToken,
                ':url' => $webhookUrl ?? $existing['webhook_url'],
                ':uname' => $botUsername ?? $existing['bot_username'],
                ':intake' => $autoIntakeVal,
                ':welcome' => $welcomeVal,
                ':proj' => $defaultProject,
                ':usr' => $defaultAssignee,
                ':now' => $now,
                ':id' => $existing['id'],
            ]);
            return (string)$existing['public_id'];
        }

        $pubId = 'tgc_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_telegram_configs
             (public_id, organization_id, bot_token_encrypted, webhook_secret_token, webhook_url, bot_username, auto_create_intake, welcome_message, default_project_public_id, default_assignee_user_public_id, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :tok, :sec, :url, :uname, :intake, :welcome, :proj, :usr, 1, :created_at, :updated_at)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':tok' => $encryptedToken,
            ':sec' => $webhookSecretToken,
            ':url' => $webhookUrl,
            ':uname' => $botUsername,
            ':intake' => $autoIntakeVal,
            ':welcome' => $welcomeVal,
            ':proj' => $defaultProject,
            ':usr' => $defaultAssignee,
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
    public function callTelegramApi(string $method, array $params = [], ?string $token = null, int $organizationId = 1): array
    {
        if ($token === null || $token === '' || str_contains($token, '••••') || str_starts_with($token, 'gcm:') || str_starts_with($token, 'b64:')) {
            $cfg = $this->getConfig($organizationId);
            $token = $cfg['bot_token'] ?? '';
            if ($token === '' || str_contains($token, '••••') || str_starts_with($token, 'gcm:')) {
                try {
                    $any = $this->db->query("SELECT bot_token_encrypted FROM crm_telegram_configs WHERE is_active = 1 AND bot_token_encrypted != '' ORDER BY id DESC LIMIT 1");
                    $enc = $any ? (string)$any->fetchColumn() : '';
                    if ($enc !== '') {
                        $token = $this->decryptToken($enc);
                    }
                } catch (Throwable) {}
            }
        }

        if ($token === '') {
            return [
                'ok' => false,
                'description' => 'Telegram Bot Token не настроен.',
            ];
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
            ];
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'description' => "Некорректный ответ от Telegram API (HTTP {$httpCode})",
            ];
        }

        return $decoded;
    }

    /**
     * Test connection to Telegram API via getMe.
     *
     * @return array{ok: bool, bot?: array<string, mixed>, error?: string}
     */
    public function testConnection(?string $token = null, int $organizationId = 1): array
    {
        if ($token !== null && (str_contains($token, '••••') || str_starts_with($token, 'gcm:') || str_starts_with($token, 'b64:'))) {
            $token = null;
        }

        $res = $this->callTelegramApi('getMe', [], $token, $organizationId);
        if (!empty($res['ok']) && !empty($res['result'])) {
            $bot = $res['result'];
            $username = (string)($bot['username'] ?? '');

            // Update bot username in DB for this org and any active config
            if ($username !== '') {
                $formattedUname = '@' . ltrim($username, '@');
                try {
                    $this->db->prepare(
                        "UPDATE crm_telegram_configs SET bot_username = :uname WHERE organization_id = :org_id OR is_active = 1"
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
        $cfg = $this->getConfig($organizationId);
        if (!$cfg || empty($cfg['bot_token'])) {
            throw new RuntimeException("Бот не настроен.");
        }

        $secret = $secretToken ?: ($cfg['webhook_secret_token'] ?: bin2hex(random_bytes(16)));

        $res = $this->callTelegramApi('setWebhook', [
            'url' => $webhookUrl,
            'secret_token' => $secret,
            'drop_pending_updates' => false,
        ], $cfg['bot_token'], $organizationId);

        if (!empty($res['ok'])) {
            try {
                $this->db->prepare(
                    "UPDATE crm_telegram_configs
                     SET webhook_url = :url, webhook_secret_token = :sec, updated_at = :now
                     WHERE organization_id = :org_id"
                )->execute([
                    ':url' => $webhookUrl,
                    ':sec' => $secret,
                    ':now' => date('Y-m-d H:i:s'),
                    ':org_id' => $organizationId,
                ]);
            } catch (Throwable) {}
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

        foreach ($updates as $upd) {
            if (!is_array($upd)) continue;
            $uid = (int)($upd['update_id'] ?? 0);
            if ($uid > $maxUpdateId) {
                $maxUpdateId = $uid;
            }

            try {
                $handled = $this->handleUpdate($organizationId, '', $upd, true);
                if (!empty($handled['ok']) && empty($handled['duplicate'])) {
                    $processed++;
                }
            } catch (Throwable) {
                // Continue processing other updates
            }
        }

        if ($maxUpdateId > $lastUpdateId) {
            try {
                $this->db->prepare(
                    "UPDATE crm_telegram_configs SET polling_last_update_id = :uid WHERE organization_id = :org_id"
                )->execute([':uid' => $maxUpdateId, ':org_id' => $organizationId]);
            } catch (Throwable) {}
        }

        return [
            'ok' => true,
            'processed_count' => $processed,
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

        // Validate if organizationId exists in organizations table
        try {
            $checkOrg = (int)$this->db->query("SELECT id FROM organizations WHERE id = " . (int)$organizationId)->fetchColumn();
            if ($checkOrg <= 0) {
                $activeConfigOrg = (int)$this->db->query("SELECT organization_id FROM crm_telegram_configs WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
                if ($activeConfigOrg > 0) {
                    $organizationId = $activeConfigOrg;
                } else {
                    $primaryOrg = (int)$this->db->query("SELECT id FROM organizations ORDER BY id ASC LIMIT 1")->fetchColumn();
                    if ($primaryOrg > 0) {
                        $organizationId = $primaryOrg;
                    }
                }
            }
        } catch (Throwable) {}

        $cfg = $this->getConfig($organizationId);

        if (!$cfg || empty($cfg['bot_token'])) {
            try {
                $anyStmt = $this->db->query("SELECT organization_id FROM crm_telegram_configs WHERE is_active = 1 LIMIT 1");
                $anyOrgId = $anyStmt ? (int)$anyStmt->fetchColumn() : 0;
                if ($anyOrgId > 0) {
                    $organizationId = $anyOrgId;
                    $cfg = $this->getConfig($organizationId);
                }
            } catch (Throwable) {}
        }

        if (!$cfg || empty($cfg['bot_token'])) {
            throw new RuntimeException("Telegram bot configuration not found or inactive.");
        }

        // Secret token validation (unless polling)
        if (!$skipSecretCheck) {
            $expectedSecret = (string)($cfg['webhook_secret_token'] ?? '');
            if ($expectedSecret !== '' && !hash_equals($expectedSecret, $receivedSecretToken)) {
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
            return [
                'ok' => true,
                'duplicate' => true,
                'action' => 'ignored_duplicate',
            ];
        }

        $message = $update['message'] ?? ($update['edited_message'] ?? []);
        $chatId = (int)($message['chat']['id'] ?? 0);
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
                    $this->sendMessage($organizationId, $chatId, $welcome);
                }
                $action = 'start_greeted';
            } elseif (str_starts_with($text, '/bind ')) {
                $code = trim(substr($text, 6));
                $bindResult = $this->verifyUserBinding($organizationId, $chatId, $fromUsername, $code);
                if ($bindResult === 'bind_success') {
                    if ($chatId !== 0) {
                        $this->sendMessage(
                            $organizationId,
                            $chatId,
                            "✅ <b>Аккаунт успешно привязан!</b>\nТеперь вы будете получать персональные уведомления CRM в этот чат."
                        );
                    }
                    $action = 'bind_success';
                } else {
                    if ($chatId !== 0) {
                        $this->sendMessage(
                            $organizationId,
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
                                $this->sendMessage(
                                    $organizationId,
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

        // Record update log
        $now = date('Y-m-d H:i:s');
        $logPubId = 'tgu_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_telegram_updates_log
             (public_id, organization_id, update_id, chat_id, action_type, payload_json, created_at)
             VALUES (:pub_id, :org_id, :uid, :cid, :action, :payload, :now)"
        );
        $ins->execute([
            ':pub_id' => $logPubId,
            ':org_id' => $organizationId,
            ':uid' => $updateId,
            ':cid' => $chatId,
            ':action' => $action,
            ':payload' => json_encode($update, JSON_UNESCAPED_UNICODE),
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'duplicate' => false,
            'action' => $action,
            'message_id' => $messageId,
        ];
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

            // Check if organization_id column exists
            $hasOrgIdCol = false;
            try {
                $colCheck = $this->db->query("SELECT organization_id FROM intake_items LIMIT 0");
                $hasOrgIdCol = ($colCheck !== false);
            } catch (Throwable) {}

            // Validate that organizationId exists in organizations table
            try {
                $validOrg = (int)$this->db->query("SELECT id FROM organizations WHERE id = " . (int)$organizationId)->fetchColumn();
                if ($validOrg <= 0) {
                    $primaryOrg = (int)$this->db->query("SELECT id FROM organizations ORDER BY id ASC LIMIT 1")->fetchColumn();
                    if ($primaryOrg > 0) {
                        $organizationId = $primaryOrg;
                    }
                }
            } catch (Throwable) {}

            if ($hasOrgIdCol) {
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
                    ':org_id' => $organizationId > 0 ? $organizationId : 1,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            } else {
                $ins = $this->db->prepare(
                    "INSERT INTO intake_items
                     (public_id, title, description, status, source_type, source_ref, external_source, external_id, creator_user_id, created_at, updated_at)
                     VALUES (:pub_id, :title, :desc, 'pending', 'webhook', :sref, 'telegram', :ext_id, 1, :created_at, :updated_at)"
                );
                $ins->execute([
                    ':pub_id' => $publicId,
                    ':title' => $title,
                    ':desc' => $description,
                    ':sref' => $sourceRef,
                    ':ext_id' => $externalId,
                    ':created_at' => $now,
                    ':updated_at' => $now,
                ]);
            }

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
            "SELECT public_id, update_id, chat_id, action_type, payload_json, created_at
             FROM crm_telegram_updates_log
             WHERE organization_id = :org_id
             ORDER BY id DESC
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

        // Validate if organizationId exists in organizations table
        try {
            $checkOrg = (int)$this->db->query("SELECT id FROM organizations WHERE id = " . (int)$organizationId)->fetchColumn();
            if ($checkOrg <= 0) {
                $activeConfigOrg = (int)$this->db->query("SELECT organization_id FROM crm_telegram_configs WHERE is_active = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();
                if ($activeConfigOrg > 0) {
                    $organizationId = $activeConfigOrg;
                } else {
                    $primaryOrg = (int)$this->db->query("SELECT id FROM organizations ORDER BY id ASC LIMIT 1")->fetchColumn();
                    if ($primaryOrg > 0) {
                        $organizationId = $primaryOrg;
                    }
                }
            }
        } catch (Throwable) {}

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
            $raw = [];
            if ($isRoot || $userId <= 0) {
                $stmt = $this->db->query("SELECT * FROM organizations ORDER BY id ASC LIMIT 50");
                $raw = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
            } else {
                $stmt = $this->db->prepare(
                    "SELECT o.*
                     FROM organization_memberships om
                     JOIN organizations o ON o.id = om.organization_id
                     WHERE om.user_id = :uid
                     ORDER BY o.id ASC"
                );
                $stmt->execute([':uid' => $userId]);
                $raw = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                if (empty($raw)) {
                    $fallback = $this->db->query("SELECT * FROM organizations ORDER BY id ASC LIMIT 50");
                    $raw = $fallback ? ($fallback->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
                }
            }

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
        try {
            $stmt = $this->db->query("SELECT * FROM projects ORDER BY id ASC LIMIT 50");
            $raw = $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
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
