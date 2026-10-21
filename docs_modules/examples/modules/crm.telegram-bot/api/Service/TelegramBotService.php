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
        } elseif ($text !== '') {
            // Customer intake creation
            $autoIntake = !empty($cfg['auto_create_intake']);
            if ($autoIntake) {
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
            'verified_bindings' => $verifiedBindings,
            'pending_bindings' => $pendingBindings,
            'public_id' => $cfg['public_id'] ?? null,
        ];
    }
}
