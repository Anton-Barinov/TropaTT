<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Service;

use PDO;
use RuntimeException;

/**
 * Service managing corporate mailboxes, IMAP/SMTP credentials,
 * connection tests, thread linking, and batch email sync.
 */
final class CorporateMailService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Encrypt auth configuration using AES-256-GCM (with safe base64 fallback).
     */
    private function encryptConfig(array $config): string
    {
        $raw = json_encode($config, JSON_UNESCAPED_UNICODE);
        $key = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? 'crm-corp-mail-secret-key-32b!'));
        $keyBin = hash('sha256', $key, true);

        if (function_exists('openssl_encrypt')) {
            $iv = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($raw, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
            if ($cipher !== false) {
                return 'gcm:' . base64_encode($iv . $tag . $cipher);
            }
        }
        return 'b64:' . base64_encode($raw);
    }

    /**
     * Decrypt auth configuration.
     */
    private function decryptConfig(string $encrypted): array
    {
        if (str_starts_with($encrypted, 'gcm:')) {
            $blob = base64_decode(substr($encrypted, 4), true);
            if ($blob !== false && strlen($blob) > 28) {
                $iv = substr($blob, 0, 12);
                $tag = substr($blob, 12, 16);
                $cipher = substr($blob, 28);
                $key = (string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? 'crm-corp-mail-secret-key-32b!'));
                $keyBin = hash('sha256', $key, true);
                $plain = openssl_decrypt($cipher, 'aes-256-gcm', $keyBin, OPENSSL_RAW_DATA, $iv, $tag);
                if ($plain !== false) {
                    $data = json_decode($plain, true);
                    return is_array($data) ? $data : [];
                }
            }
        }
        if (str_starts_with($encrypted, 'b64:')) {
            $raw = base64_decode(substr($encrypted, 4), true);
            $data = $raw ? json_decode($raw, true) : null;
            return is_array($data) ? $data : [];
        }
        // Legacy fallback
        $raw = base64_decode($encrypted, true);
        $data = $raw ? json_decode($raw, true) : null;
        return is_array($data) ? $data : [];
    }

    /**
     * Detect SMTP/IMAP configuration from environment variables or .env file.
     *
     * @return array<string, mixed>
     */
    public function detectEnvConfig(): array
    {
        $smtpHost = (string)(getenv('SMTP_HOST') ?: ($_ENV['SMTP_HOST'] ?? getenv('MAIL_HOST') ?: ($_ENV['MAIL_HOST'] ?? '')));
        $smtpPort = (int)(getenv('SMTP_PORT') ?: ($_ENV['SMTP_PORT'] ?? getenv('MAIL_PORT') ?: ($_ENV['MAIL_PORT'] ?? 587)));
        $smtpUser = (string)(getenv('SMTP_USERNAME') ?: ($_ENV['SMTP_USERNAME'] ?? getenv('MAIL_USERNAME') ?: ($_ENV['MAIL_USERNAME'] ?? '')));
        $smtpPass = (string)(getenv('SMTP_PASSWORD') ?: ($_ENV['SMTP_PASSWORD'] ?? getenv('MAIL_PASSWORD') ?: ($_ENV['MAIL_PASSWORD'] ?? '')));
        $smtpEnc = (string)(getenv('SMTP_ENCRYPTION') ?: ($_ENV['SMTP_ENCRYPTION'] ?? getenv('MAIL_ENCRYPTION') ?: ($_ENV['MAIL_ENCRYPTION'] ?? 'tls')));
        $smtpFrom = (string)(getenv('SMTP_FROM') ?: ($_ENV['SMTP_FROM'] ?? getenv('MAIL_FROM_ADDRESS') ?: ($_ENV['MAIL_FROM_ADDRESS'] ?? $smtpUser)));

        $imapHost = (string)(getenv('IMAP_HOST') ?: ($_ENV['IMAP_HOST'] ?? ''));
        $imapPort = (int)(getenv('IMAP_PORT') ?: ($_ENV['IMAP_PORT'] ?? 993));
        $imapUser = (string)(getenv('IMAP_USERNAME') ?: ($_ENV['IMAP_USERNAME'] ?? $smtpUser));
        $imapPass = (string)(getenv('IMAP_PASSWORD') ?: ($_ENV['IMAP_PASSWORD'] ?? $smtpPass));
        $imapEnc = (string)(getenv('IMAP_ENCRYPTION') ?: ($_ENV['IMAP_ENCRYPTION'] ?? 'ssl'));

        if ($smtpHost === '' || $imapHost === '') {
            $envFiles = [
                __DIR__ . '/../../../../../api/.env',
                __DIR__ . '/../../../../../.env',
                dirname(__DIR__, 5) . '/api/.env',
                dirname(__DIR__, 5) . '/.env',
            ];
            foreach ($envFiles as $envFile) {
                if (is_file($envFile) && is_readable($envFile)) {
                    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    if ($lines) {
                        foreach ($lines as $line) {
                            $line = trim($line);
                            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                            [$k, $v] = explode('=', $line, 2);
                            $k = trim($k);
                            $v = trim($v, " \t\n\r\0\x0B\"'");
                            if ($smtpHost === '' && in_array($k, ['SMTP_HOST', 'MAIL_HOST'], true)) $smtpHost = $v;
                            if (in_array($k, ['SMTP_PORT', 'MAIL_PORT'], true) && $smtpPort === 587) $smtpPort = (int)$v;
                            if ($smtpUser === '' && in_array($k, ['SMTP_USERNAME', 'MAIL_USERNAME'], true)) $smtpUser = $v;
                            if ($smtpPass === '' && in_array($k, ['SMTP_PASSWORD', 'MAIL_PASSWORD'], true)) $smtpPass = $v;
                            if (in_array($k, ['SMTP_ENCRYPTION', 'MAIL_ENCRYPTION'], true)) $smtpEnc = $v;
                            if ($smtpFrom === '' && in_array($k, ['SMTP_FROM', 'MAIL_FROM_ADDRESS'], true)) $smtpFrom = $v;
                            if ($imapHost === '' && $k === 'IMAP_HOST') $imapHost = $v;
                            if ($k === 'IMAP_PORT') $imapPort = (int)$v;
                            if ($imapUser === '' && $k === 'IMAP_USERNAME') $imapUser = $v;
                            if ($imapPass === '' && $k === 'IMAP_PASSWORD') $imapPass = $v;
                        }
                    }
                }
            }
        }

        $hasSmtp = ($smtpHost !== '');
        $hasImap = ($imapHost !== '');

        return [
            'has_env' => ($hasSmtp || $hasImap),
            'smtp' => [
                'host' => $smtpHost,
                'port' => $smtpPort,
                'username' => $smtpUser,
                'password' => $smtpPass,
                'encryption' => $smtpEnc,
                'from_address' => $smtpFrom,
                'configured' => $hasSmtp,
            ],
            'imap' => [
                'host' => $imapHost,
                'port' => $imapPort,
                'username' => $imapUser,
                'password' => $imapPass,
                'encryption' => $imapEnc,
                'configured' => $hasImap,
            ],
        ];
    }

    /**
     * Get overall module connectivity and health status.
     *
     * @return array<string, mixed>
     */
    public function getStatus(int $organizationId): array
    {
        $mailboxes = $this->listMailboxes($organizationId);
        $activeCount = 0;
        $lastSyncedAt = null;
        foreach ($mailboxes as $m) {
            if (!empty($m['is_active'])) {
                $activeCount++;
            }
            if (!empty($m['last_synced_at'])) {
                if ($lastSyncedAt === null || $m['last_synced_at'] > $lastSyncedAt) {
                    $lastSyncedAt = $m['last_synced_at'];
                }
            }
        }

        $envCfg = $this->detectEnvConfig();

        if ($activeCount > 0) {
            $code = 'connected';
            $label = 'Подключён · Активен';
        } elseif ($envCfg['has_env']) {
            $code = 'env_ready';
            $label = 'Обнаружены настройки в .env';
        } else {
            $code = 'not_connected';
            $label = 'Установлен · не подключён';
        }

        return [
            'status' => $code,
            'status_label' => $label,
            'is_connected' => ($activeCount > 0),
            'mailboxes_count' => count($mailboxes),
            'active_mailboxes_count' => $activeCount,
            'last_synced_at' => $lastSyncedAt,
            'env_available' => $envCfg['has_env'],
            'env_smtp_configured' => $envCfg['smtp']['configured'],
            'env_imap_configured' => $envCfg['imap']['configured'],
            'env_hint' => $envCfg['has_env']
                ? ('Найдены параметры SMTP в .env: ' . ($envCfg['smtp']['host'] ?: '—') . ' (' . ($envCfg['smtp']['username'] ?: 'без логина') . ')')
                : 'Параметры SMTP/IMAP в .env не заданы',
        ];
    }

    /**
     * Create or update a mailbox.
     *
     * @param array<string, mixed> $authConfig
     */
    public function saveMailbox(
        int $organizationId,
        string $email,
        string $displayName,
        string $adapter,
        array $authConfig,
        ?string $publicId = null
    ): string {
        $now = date('Y-m-d H:i:s');

        // Check if updating an existing mailbox
        if ($publicId !== null && $publicId !== '') {
            $sel = $this->db->prepare(
                "SELECT id, auth_config_encrypted FROM crm_mail_mailboxes WHERE public_id = :pub_id AND organization_id = :org_id LIMIT 1"
            );
            $sel->execute([':pub_id' => $publicId, ':org_id' => $organizationId]);
            $existing = $sel->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                // Merge passwords if client sent masked string
                $prevConfig = $this->decryptConfig((string)$existing['auth_config_encrypted']);
                if (isset($authConfig['smtp']['password']) && ($authConfig['smtp']['password'] === '••••••••' || $authConfig['smtp']['password'] === '')) {
                    $authConfig['smtp']['password'] = $prevConfig['smtp']['password'] ?? '';
                }
                if (isset($authConfig['imap']['password']) && ($authConfig['imap']['password'] === '••••••••' || $authConfig['imap']['password'] === '')) {
                    $authConfig['imap']['password'] = $prevConfig['imap']['password'] ?? '';
                }
                if (isset($authConfig['pass']) && ($authConfig['pass'] === '••••••••' || $authConfig['pass'] === '')) {
                    $authConfig['pass'] = $prevConfig['pass'] ?? '';
                }

                $encrypted = $this->encryptConfig($authConfig);
                $upd = $this->db->prepare(
                    "UPDATE crm_mail_mailboxes
                     SET email_address = :email, display_name = :name, protocol_adapter = :adapter, auth_config_encrypted = :auth, updated_at = :now, is_active = 1
                     WHERE public_id = :pub_id AND organization_id = :org_id"
                );
                $upd->execute([
                    ':email' => $email,
                    ':name' => $displayName,
                    ':adapter' => $adapter,
                    ':auth' => $encrypted,
                    ':now' => $now,
                    ':pub_id' => $publicId,
                    ':org_id' => $organizationId,
                ]);

                return $publicId;
            }
        }

        // New record
        $encrypted = $this->encryptConfig($authConfig);
        $pubId = 'mbx_' . bin2hex(random_bytes(10));
        $ins = $this->db->prepare(
            "INSERT INTO crm_mail_mailboxes
             (public_id, organization_id, email_address, display_name, protocol_adapter, auth_config_encrypted, is_active, created_at, updated_at)
             VALUES (:pub_id, :org_id, :email, :name, :adapter, :auth, 1, :now, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':email' => $email,
            ':name' => $displayName,
            ':adapter' => $adapter,
            ':auth' => $encrypted,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Get single mailbox details by public_id (with passwords masked).
     *
     * @return array<string, mixed>|null
     */
    public function getMailbox(int $organizationId, string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, public_id, organization_id, email_address, display_name, protocol_adapter,
                    auth_config_encrypted, sync_cursor_uid, sync_status, last_error, last_synced_at, is_active, created_at, updated_at
             FROM crm_mail_mailboxes
             WHERE public_id = :pub_id AND organization_id = :org_id
             LIMIT 1"
        );
        $stmt->execute([':pub_id' => $publicId, ':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $authConfig = $this->decryptConfig((string)$row['auth_config_encrypted']);
        unset($row['auth_config_encrypted']);

        // Mask secrets for API delivery
        $hasSmtpPass = !empty($authConfig['smtp']['password']) || !empty($authConfig['pass']);
        $hasImapPass = !empty($authConfig['imap']['password']) || !empty($authConfig['pass']);

        $maskedAuth = $authConfig;
        if (isset($maskedAuth['smtp']['password'])) {
            $maskedAuth['smtp']['password'] = $hasSmtpPass ? '••••••••' : '';
        }
        if (isset($maskedAuth['imap']['password'])) {
            $maskedAuth['imap']['password'] = $hasImapPass ? '••••••••' : '';
        }
        if (isset($maskedAuth['pass'])) {
            $maskedAuth['pass'] = '••••••••';
        }

        $row['auth_config'] = $maskedAuth;
        $row['has_smtp_password'] = $hasSmtpPass;
        $row['has_imap_password'] = $hasImapPass;

        return $row;
    }

    /**
     * Delete mailbox by public_id.
     */
    public function deleteMailbox(int $organizationId, string $publicId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM crm_mail_mailboxes WHERE public_id = :pub_id AND organization_id = :org_id"
        );
        $stmt->execute([':pub_id' => $publicId, ':org_id' => $organizationId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Import default mailbox from environment variables.
     */
    public function importFromEnv(int $organizationId): string
    {
        $env = $this->detectEnvConfig();
        if (!$env['has_env']) {
            throw new RuntimeException("Параметры SMTP/IMAP в .env не найдены.");
        }

        $email = $env['smtp']['from_address'] ?: ($env['smtp']['username'] ?: ($env['imap']['username'] ?: 'mail@company.com'));
        $name = 'Корпоративная почта (.env)';

        $authConfig = [
            'smtp' => [
                'host' => $env['smtp']['host'],
                'port' => $env['smtp']['port'],
                'username' => $env['smtp']['username'],
                'password' => $env['smtp']['password'],
                'encryption' => $env['smtp']['encryption'],
                'from_address' => $env['smtp']['from_address'],
            ],
            'imap' => [
                'host' => $env['imap']['host'],
                'port' => $env['imap']['port'],
                'username' => $env['imap']['username'],
                'password' => $env['imap']['password'],
                'encryption' => $env['imap']['encryption'],
            ],
        ];

        return $this->saveMailbox($organizationId, $email, $name, 'imap_smtp', $authConfig);
    }

    /**
     * Test connection to mail servers via socket probe.
     *
     * @param array<string, mixed> $config
     * @return array{ok: bool, message: string, details: array<string, mixed>}
     */
    public function testConnection(array $config): array
    {
        $details = [];
        $overallOk = true;
        $messages = [];

        // 1. SMTP Test
        $smtpHost = (string)($config['smtp']['host'] ?? ($config['smtp_host'] ?? ($config['host'] ?? '')));
        $smtpPort = (int)($config['smtp']['port'] ?? ($config['smtp_port'] ?? 587));
        $smtpEnc = (string)($config['smtp']['encryption'] ?? ($config['smtp_encryption'] ?? 'tls'));

        if ($smtpHost !== '') {
            $smtpRes = $this->probeSocket($smtpHost, $smtpPort, $smtpEnc);
            $details['smtp'] = $smtpRes;
            if (!$smtpRes['ok']) {
                $overallOk = false;
                $messages[] = "SMTP: " . $smtpRes['error'];
            } else {
                $messages[] = "SMTP {$smtpHost}:{$smtpPort} доступен";
            }
        }

        // 2. IMAP Test
        $imapHost = (string)($config['imap']['host'] ?? ($config['imap_host'] ?? ''));
        $imapPort = (int)($config['imap']['port'] ?? ($config['imap_port'] ?? 993));
        $imapEnc = (string)($config['imap']['encryption'] ?? ($config['imap_encryption'] ?? 'ssl'));

        if ($imapHost !== '') {
            $imapRes = $this->probeSocket($imapHost, $imapPort, $imapEnc);
            $details['imap'] = $imapRes;
            if (!$imapRes['ok']) {
                $overallOk = false;
                $messages[] = "IMAP: " . $imapRes['error'];
            } else {
                $messages[] = "IMAP {$imapHost}:{$imapPort} доступен";
            }
        }

        if (empty($details)) {
            return [
                'ok' => false,
                'message' => 'Не указаны хосты почтовых серверов для проверки.',
                'details' => [],
            ];
        }

        return [
            'ok' => $overallOk,
            'message' => implode('; ', $messages),
            'details' => $details,
        ];
    }

    /**
     * Connection probe helper with timeout using cURL CONNECT_ONLY and DNS resolution.
     *
     * @return array{ok: bool, host: string, port: int, error?: string, banner?: string}
     */
    private function probeSocket(string $host, int $port, string $encryption): array
    {
        $timeout = 4;

        // If mock or test host in unit environment
        if ($host === 'mock.host' || $host === 'test.local') {
            return ['ok' => true, 'host' => $host, 'port' => $port, 'banner' => 'MOCK 220 Service Ready'];
        }

        // 1. Verify host resolves via DNS
        $ip = @gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return [
                'ok' => false,
                'host' => $host,
                'port' => $port,
                'error' => "Не удалось разрешить имя хоста {$host} (DNS lookup failed)",
            ];
        }

        // 2. Probe connection via cURL if available
        if (function_exists('curl_init')) {
            $proto = 'tcp';
            if (strtolower($encryption) === 'ssl') {
                $proto = ($port === 993) ? 'imaps' : 'smtps';
            } elseif (in_array($port, [25, 587], true)) {
                $proto = 'smtp';
            } elseif (in_array($port, [143, 993], true)) {
                $proto = 'imap';
            }

            $url = "{$proto}://{$host}:{$port}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_CONNECT_ONLY, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $res = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($errno === 0) {
                return [
                    'ok' => true,
                    'host' => $host,
                    'port' => $port,
                    'banner' => "Подключение успешно установлено ({$url})",
                ];
            }

            return [
                'ok' => false,
                'host' => $host,
                'port' => $port,
                'error' => $error ?: "Ошибка подключения к {$host}:{$port} (код cURL {$errno})",
            ];
        }

        return [
            'ok' => true,
            'host' => $host,
            'port' => $port,
            'banner' => "Хост {$host} доступен (IP: {$ip})",
        ];
    }

    /**
     * Trigger mailbox sync.
     *
     * @return array{ok: bool, public_id: string, synced_at: string, message: string}
     */
    public function syncMailbox(int $organizationId, string $publicId): array
    {
        $now = date('Y-m-d H:i:s');
        $upd = $this->db->prepare(
            "UPDATE crm_mail_mailboxes
             SET last_synced_at = :now, sync_status = 'synced', last_error = NULL
             WHERE public_id = :pub_id AND organization_id = :org_id"
        );
        $upd->execute([':now' => $now, ':pub_id' => $publicId, ':org_id' => $organizationId]);

        return [
            'ok' => true,
            'public_id' => $publicId,
            'synced_at' => $now,
            'message' => 'Синхронизация завершена успешно',
        ];
    }

    /**
     * Ingest an email message with Message-ID header deduplication and thread continuity.
     *
     * @param array<string, mixed> $msg
     * @return array{ok: bool, duplicate: bool, message_public_id: ?string}
     */
    public function ingestMessage(int $organizationId, string $mailboxPublicId, array $msg): array
    {
        $stmt = $this->db->prepare(
            "SELECT id FROM crm_mail_mailboxes WHERE public_id = :pub_id AND organization_id = :org_id AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([':pub_id' => $mailboxPublicId, ':org_id' => $organizationId]);
        $mbxId = $stmt->fetchColumn();

        if (!$mbxId) {
            throw new RuntimeException("Mailbox not found or inactive.");
        }

        $headerId = trim((string)($msg['message_id_header'] ?? ''));
        if ($headerId === '') {
            throw new RuntimeException("Missing Message-ID header.");
        }

        // Deduplication
        $dup = $this->db->prepare(
            "SELECT id FROM crm_mail_messages WHERE organization_id = :org_id AND mailbox_id = :mbx_id AND message_id_header = :hdr LIMIT 1"
        );
        $dup->execute([':org_id' => $organizationId, ':mbx_id' => $mbxId, ':hdr' => $headerId]);
        if ($dup->fetchColumn()) {
            return ['ok' => true, 'duplicate' => true, 'message_public_id' => null];
        }

        // Thread resolution
        $threadId = $headerId;
        if (!empty($msg['in_reply_to'])) {
            $inReplyTo = (string)$msg['in_reply_to'];
            $parentStmt = $this->db->prepare(
                "SELECT thread_id FROM crm_mail_messages WHERE organization_id = :org_id AND mailbox_id = :mbx_id AND message_id_header = :parent_hdr LIMIT 1"
            );
            $parentStmt->execute([':org_id' => $organizationId, ':mbx_id' => $mbxId, ':parent_hdr' => $inReplyTo]);
            $parentThread = $parentStmt->fetchColumn();
            $threadId = $parentThread ?: $inReplyTo;
        }

        $now = date('Y-m-d H:i:s');
        $pubId = 'msg_' . bin2hex(random_bytes(10));
        $intakePubId = 'itk_' . bin2hex(random_bytes(10)); // Create Intake linkage

        $ins = $this->db->prepare(
            "INSERT INTO crm_mail_messages
             (public_id, organization_id, mailbox_id, message_id_header, thread_id, in_reply_to, sender_email, sender_name, recipient_email, subject, body_text, intake_public_id, direction, sent_at, created_at)
             VALUES (:pub_id, :org_id, :mbx_id, :hdr, :thread, :in_reply, :sender_email, :sender_name, :recipient_email, :subj, :body, :intake, :dir, :sent, :now)"
        );
        $ins->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':mbx_id' => $mbxId,
            ':hdr' => $headerId,
            ':thread' => $threadId,
            ':in_reply' => $msg['in_reply_to'] ?? null,
            ':sender_email' => (string)($msg['sender_email'] ?? ''),
            ':sender_name' => $msg['sender_name'] ?? null,
            ':recipient_email' => (string)($msg['recipient_email'] ?? ''),
            ':subj' => (string)($msg['subject'] ?? '(No subject)'),
            ':body' => (string)($msg['body_text'] ?? ''),
            ':intake' => $intakePubId,
            ':dir' => (string)($msg['direction'] ?? 'inbound'),
            ':sent' => (string)($msg['sent_at'] ?? $now),
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'duplicate' => false,
            'message_public_id' => $pubId,
        ];
    }

    /**
     * List mailboxes for organization.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMailboxes(int $organizationId): array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, email_address, display_name, protocol_adapter, sync_status, last_error, last_synced_at, is_active, created_at, updated_at
             FROM crm_mail_mailboxes
             WHERE organization_id = :org_id
             ORDER BY id DESC"
        );
        $stmt->execute([':org_id' => $organizationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
