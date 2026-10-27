<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Service;

use PDO;
use RuntimeException;
use Closure;
use Throwable;

/**
 * Service managing corporate mailboxes, IMAP/SMTP credentials,
 * connection tests, thread linking, and batch email sync.
 */
final class CorporateMailService
{
    private const MAX_IMAP_MESSAGE_BYTES = 262144;
    private const MAX_IMAP_BATCH_BYTES = 1310720;
    private const MAX_IMAP_MESSAGES_PER_BATCH = 5;
    private const MAX_IMAP_UID_WINDOW = 1000;
    private const SYNC_LEASE_SECONDS = 60;
    private const MAX_MAIL_DNS_ADDRESSES = 8;
    private const MAX_MAIL_DNS_SECONDS = 2.0;

    private PDO $db;
    private ?Closure $connectionProbe;
    private ?Closure $messageFetcher;
    private ?Closure $smtpTransport;
    private ?Closure $imapRetryFetcher;

    public function __construct(PDO $db, ?callable $connectionProbe = null, ?callable $messageFetcher = null, ?callable $smtpTransport = null, ?callable $imapRetryFetcher = null)
    {
        $this->db = $db;
        $this->connectionProbe = $connectionProbe === null ? null : Closure::fromCallable($connectionProbe);
        $this->messageFetcher = $messageFetcher === null ? null : Closure::fromCallable($messageFetcher);
        $this->smtpTransport = $smtpTransport === null ? null : Closure::fromCallable($smtpTransport);
        $this->imapRetryFetcher = $imapRetryFetcher === null ? null : Closure::fromCallable($imapRetryFetcher);
    }

    /**
     * Encrypt auth configuration using authenticated AES-256-GCM.
     */
    private function encryptConfig(array $config): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new RuntimeException('Secure mailbox credential storage is unavailable; enable OpenSSL before saving credentials.');
        }

        $raw = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($raw, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false || strlen($tag) !== 16) {
            throw new RuntimeException('Failed to encrypt mailbox credentials.');
        }

        // Keep the established gcm: envelope so existing ciphertext encrypted with
        // the configured APP_KEY remains readable without a database migration.
        return 'gcm:' . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Decrypt auth configuration.
     */
    private function decryptConfig(string $encrypted): array
    {
        if (!str_starts_with($encrypted, 'gcm:')) {
            // Old b64: and unprefixed base64 values were plaintext encoding, not
            // encryption. They cannot be trusted or safely migrated automatically.
            throw new RuntimeException('Stored mailbox credentials use an insecure legacy format; re-enter the mailbox credentials to continue.');
        }

        if (!function_exists('openssl_decrypt')) {
            throw new RuntimeException('Secure mailbox credential storage is unavailable; enable OpenSSL to read credentials.');
        }

        $blob = base64_decode(substr($encrypted, 4), true);
        if ($blob === false || strlen($blob) <= 28) {
            throw new RuntimeException('Stored mailbox credentials are invalid; re-enter the mailbox credentials.');
        }

        $iv = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $cipher = substr($blob, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw new RuntimeException('Stored mailbox credentials could not be authenticated; re-enter the mailbox credentials.');
        }

        try {
            $data = json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new RuntimeException('Stored mailbox credentials are invalid; re-enter the mailbox credentials.', 0, $e);
        }
        if (!is_array($data)) {
            throw new RuntimeException('Stored mailbox credentials are invalid; re-enter the mailbox credentials.');
        }

        return $data;
    }

    /**
     * Derive the same encryption key used by the established gcm: format.
     * APP_KEY is required and the former publicly-known fallback is rejected.
     */
    private function credentialKey(): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if ($key === '' || strlen($key) < 32 || hash_equals('crm-corp-mail-secret-key-32b!', $key)) {
            throw new RuntimeException('Secure mailbox credential storage is unavailable; configure a strong APP_KEY of at least 32 characters.');
        }
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Secure mailbox credential storage is unavailable; enable OpenSSL before using mailbox credentials.');
        }

        // Preserve compatibility with the module's existing gcm: envelope.
        return hash('sha256', $key, true);
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
            $code = 'configured';
            $label = 'Настроен · доступна синхронизация';
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
            'is_connected' => false,
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

                $this->assertMailTransportConfig($authConfig);
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
        $this->assertMailTransportConfig($authConfig);
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

    /** @return array{status: string, message_public_id: string, updated_at: string}|null */
    public function getSendAttemptStatus(int $organizationId, string $mailboxPublicId, string $idempotencyKey): ?array
    {
        $idempotencyKey = trim($idempotencyKey);
        if (strlen($idempotencyKey) < 16 || strlen($idempotencyKey) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/D', $idempotencyKey) !== 1) {
            throw new RuntimeException('A valid idempotency key between 16 and 128 characters is required.');
        }
        $stmt = $this->db->prepare(
            'SELECT a.status, a.message_public_id, a.updated_at FROM crm_mail_send_attempts a INNER JOIN crm_mail_mailboxes m ON m.id = a.mailbox_id WHERE m.public_id = :mailbox_public_id AND a.organization_id = :organization_id AND a.mailbox_id = m.id AND a.idempotency_hash = :key_hash LIMIT 1'
        );
        $stmt->execute([':mailbox_public_id' => $mailboxPublicId, ':organization_id' => $organizationId, ':key_hash' => hash('sha256', $idempotencyKey)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Delete mailbox by public_id.
     */
    public function deleteMailbox(int $organizationId, string $publicId): bool
    {
        $lookup = $this->db->prepare('SELECT id FROM crm_mail_mailboxes WHERE public_id=:pub_id AND organization_id=:org_id LIMIT 1');
        $lookup->execute([':pub_id' => $publicId, ':org_id' => $organizationId]);
        $mailboxId = $lookup->fetchColumn();
        if ($mailboxId === false) return false;
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM crm_mail_sync_failures WHERE mailbox_id=:mailbox_id AND organization_id=:org_id')->execute([':mailbox_id' => (int)$mailboxId, ':org_id' => $organizationId]);
            $stmt = $this->db->prepare('DELETE FROM crm_mail_mailboxes WHERE id=:id AND public_id=:pub_id AND organization_id=:org_id');
            $stmt->execute([':id' => (int)$mailboxId, ':pub_id' => $publicId, ':org_id' => $organizationId]);
            $deleted = $stmt->rowCount() > 0;
            $this->db->commit();
            return $deleted;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
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
            $smtpRes = $this->probeSocket($smtpHost, $smtpPort, $smtpEnc, 'smtp');
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
            $imapRes = $this->probeSocket($imapHost, $imapPort, $imapEnc, 'imap');
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

    /** @return array{ok: bool, host: string, port: int, error?: string, banner?: string} */
    private function probeSocket(string $host, int $port, string $encryption, string $protocol): array
    {
        $mode = strtolower(trim($encryption));
        if (!self::isSupportedMailTransport($port, $mode, $protocol) || preg_match('/[\x00-\x20\x7f\/\\@?#]/', $host)) {
            return ['ok' => false, 'host' => $host, 'port' => $port, 'error' => 'Некорректный почтовый адрес сервера или порт.'];
        }
        if ($this->connectionProbe !== null) {
            return ($this->connectionProbe)($host, $port, $mode);
        }

        $deadline = hrtime(true) / 1e9 + 5.0;
        try {
            $this->performMailCurl($host, $port, $mode, $protocol, '', '', 'NOOP', null, 16384, $deadline, true);
            return ['ok' => true, 'host' => $host, 'port' => $port, 'banner' => 'TLS-канал установлен; сертификат сервера проверен.'];
        } catch (Throwable $e) {
            return ['ok' => false, 'host' => $host, 'port' => $port, 'error' => $e->getMessage()];
        }
    }

    /** @return array<int, string> */
    private function resolvePublicMailAddresses(string $host, ?float $deadline = null): array
    {
        $deadline ??= hrtime(true) / 1e9 + self::MAX_MAIL_DNS_SECONDS;
        if (hrtime(true) / 1e9 >= $deadline) return [];
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? [$host] : [];
        }
        if (strlen($host) > 253 || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?))*$/iD', $host)) return [];
        $addresses = [];
        // Native PHP DNS calls are synchronous; if they return after the shared deadline, fail closed.
        foreach ((array)@gethostbynamel($host) as $ip) {
            if (hrtime(true) / 1e9 >= $deadline) return [];
            $addresses[] = (string)$ip;
            if (count($addresses) >= self::MAX_MAIL_DNS_ADDRESSES) break;
        }
        if (count($addresses) < self::MAX_MAIL_DNS_ADDRESSES && hrtime(true) / 1e9 < $deadline && function_exists('dns_get_record') && defined('DNS_AAAA')) {
            foreach ((array)@dns_get_record($host, DNS_AAAA) as $record) {
                if (hrtime(true) / 1e9 >= $deadline) return [];
                if (isset($record['ipv6'])) $addresses[] = (string)$record['ipv6'];
                if (count($addresses) >= self::MAX_MAIL_DNS_ADDRESSES) break;
            }
        }
        return self::limitPublicMailAddresses($addresses, $deadline);
    }

    /** Bound and validate the exact addresses that may be contacted. */
    private static function limitPublicMailAddresses(array $addresses, float $deadline): array
    {
        if (hrtime(true) / 1e9 >= $deadline) return [];
        $safe = [];
        foreach (array_values(array_unique($addresses)) as $ip) {
            if (hrtime(true) / 1e9 >= $deadline) return [];
            if (!is_string($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return [];
            $safe[] = $ip;
            if (count($safe) >= self::MAX_MAIL_DNS_ADDRESSES) break;
        }
        return $safe;
    }

    /**
     * Trigger mailbox sync.
     *
     * @return array{ok: bool, public_id: string, synced_at: ?string, imported_count: int, message: string}
     */
    public function syncMailbox(int $organizationId, string $publicId): array
    {
        $stmt = $this->db->prepare("SELECT id,public_id,email_address,auth_config_encrypted,sync_cursor_uid,sync_uidvalidity FROM crm_mail_mailboxes WHERE public_id=:pub AND organization_id=:org AND is_active=1 LIMIT 1");
        $stmt->execute([':pub'=>$publicId, ':org'=>$organizationId]);
        $mailbox=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mailbox) return ['ok'=>false,'public_id'=>$publicId,'synced_at'=>null,'imported_count'=>0,'failed_count'=>0,'has_more'=>false,'message'=>'Почтовый ящик не найден или отключён.'];
        $token=bin2hex(random_bytes(24)); $now=gmdate('Y-m-d H:i:s');
        $claim=$this->db->prepare("UPDATE crm_mail_mailboxes SET sync_status='syncing',sync_lock_token=:token,sync_lock_until=:until,last_error=NULL,updated_at=:now WHERE id=:id AND organization_id=:org AND is_active=1 AND (sync_lock_token IS NULL OR sync_lock_until IS NULL OR sync_lock_until<:now)");
        $claim->execute([':token'=>$token, ':until'=>gmdate('Y-m-d H:i:s',time()+self::SYNC_LEASE_SECONDS), ':now'=>$now, ':id'=>(int)$mailbox['id'], ':org'=>$organizationId]);
        if ($claim->rowCount()!==1) return ['ok'=>false,'public_id'=>$publicId,'synced_at'=>null,'imported_count'=>0,'failed_count'=>0,'has_more'=>true,'message'=>'Синхронизация уже выполняется. Повторите позже.'];
        try {
            $config=$this->decryptConfig((string)$mailbox['auth_config_encrypted']);
            $cursor=max(0,(int)$mailbox['sync_cursor_uid']);
            $batch=$this->messageFetcher !== null ? ($this->messageFetcher)((array)($config['imap']??[]),$cursor,self::MAX_IMAP_MESSAGES_PER_BATCH) : $this->fetchImapMessages((array)($config['imap']??[]),(string)$mailbox['email_address'],$cursor,self::MAX_IMAP_MESSAGES_PER_BATCH,(int)($mailbox['sync_uidvalidity']??0));
            if (!is_array($batch)||!is_array($batch['messages']??null)||!is_array($batch['failures']??[])) throw new RuntimeException('IMAP provider returned an invalid batch.');
            $validity=(int)($batch['uidvalidity']??($mailbox['sync_uidvalidity']??0));
            if ($validity<=0) throw new RuntimeException('IMAP server omitted UIDVALIDITY; cursor was not advanced.');
            $oldValidity=(int)($mailbox['sync_uidvalidity']??0);
            if ($oldValidity>0 && $oldValidity!==$validity) $cursor=0;
            $maxUid=max($cursor,(int)($batch['max_uid']??$cursor));
            if ($maxUid<$cursor) throw new RuntimeException('IMAP provider returned an invalid cursor.');
            $imported=0; $failed=0;
            $this->db->beginTransaction();
            foreach ($batch['messages'] as $message) {
                if (!is_array($message)) throw new RuntimeException('IMAP provider returned an invalid message.');
                $uid=(int)($message['uid']??0);
                if ($uid<=$cursor||$uid>$maxUid) throw new RuntimeException('IMAP provider returned UID outside the fetched range.');
                unset($message['uid']);
                try { $result=$this->ingestMessage($organizationId,$publicId,$message); if (empty($result['duplicate'])) $imported++; }
                catch (Throwable $e) { $this->recordImapFailure($organizationId,(int)$mailbox['id'],$validity,$uid,'INGEST_FAILED'); $failed++; }
            }
            foreach ($batch['failures']??[] as $failure) {
                if (!is_array($failure)) throw new RuntimeException('IMAP provider returned an invalid failure.');
                $uid=(int)($failure['uid']??0);
                if ($uid<=$cursor||$uid>$maxUid) throw new RuntimeException('IMAP provider returned failure UID outside the fetched range.');
                $this->recordImapFailure($organizationId,(int)$mailbox['id'],$validity,$uid,(string)($failure['code']??'FETCH_FAILED')); $failed++;
            }
            $pendingStmt=$this->db->prepare('SELECT COUNT(*) FROM crm_mail_sync_failures WHERE organization_id=:org AND mailbox_id=:mid AND uidvalidity=:validity'); $pendingStmt->execute([':org'=>$organizationId,':mid'=>(int)$mailbox['id'],':validity'=>$validity]); $pendingCount=(int)$pendingStmt->fetchColumn();
            $now=gmdate('Y-m-d H:i:s');
            $update=$this->db->prepare("UPDATE crm_mail_mailboxes SET sync_cursor_uid=:cursor,sync_uidvalidity=:validity,last_synced_at=:now,sync_status=:status,last_error=:error,sync_lock_token=NULL,sync_lock_until=NULL,updated_at=:now WHERE id=:id AND organization_id=:org AND sync_lock_token=:token AND sync_lock_until>=:lease_now");
            $update->execute([':cursor'=>$maxUid,':validity'=>$validity,':now'=>$now,':lease_now'=>$now,':status'=>$pendingCount?'error':'synced',':error'=>$pendingCount?"{$pendingCount} писем требуют повтора.":null,':id'=>(int)$mailbox['id'],':org'=>$organizationId,':token'=>$token]);
            if ($update->rowCount()!==1) throw new RuntimeException('IMAP lease expired; batch was not committed.');
            $this->db->commit();
            $more=!empty($batch['has_more']);
            return ['ok'=>true,'public_id'=>$publicId,'synced_at'=>$now,'imported_count'=>$imported,'failed_count'=>$failed,'has_more'=>$more,'message'=>sprintf('Новых писем: %d; проблемных UID: %d%s.',$imported,$failed,$more?' — запустите следующую ограниченную партию':'')];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $safe=$this->safeProviderError($e->getMessage());
            $this->db->prepare("UPDATE crm_mail_mailboxes SET sync_status='error',last_error=:error,sync_lock_token=NULL,sync_lock_until=NULL,updated_at=:now WHERE id=:id AND organization_id=:org AND sync_lock_token=:token AND sync_lock_until>=:lease_now")->execute([':error'=>$safe,':now'=>gmdate('Y-m-d H:i:s'),':lease_now'=>gmdate('Y-m-d H:i:s'),':id'=>(int)$mailbox['id'],':org'=>$organizationId,':token'=>$token]);
            error_log('[CorporateMailService::syncMailbox] IMAP sync failed.');
            return ['ok'=>false,'public_id'=>$publicId,'synced_at'=>null,'imported_count'=>0,'failed_count'=>0,'has_more'=>false,'message'=>'Синхронизация не выполнена: '.$safe];
        }
    }

    /** Fetch a bounded UID batch. A failed/truncated FETCH returns that UID as a durable
     * failure; later UIDs are fetched on the next request with a fresh cURL connection. */
    private function fetchImapMessages(array $imap,string $mailboxEmail,int $cursorUid,int $limit,int $expectedUidValidity=0): array
    {
        $deadline=hrtime(true)/1e9+20.0; $session=$this->openImapSession($imap,$deadline);
        if ($expectedUidValidity>0 && $expectedUidValidity!==$session['uidvalidity']) $cursorUid=0; // UID values only have meaning within their UIDVALIDITY namespace.
        $high=max(0,$session['uidnext']-1); $end=min($high,$cursorUid+self::MAX_IMAP_UID_WINDOW);
            if ($end<=$cursorUid) return ['messages'=>[],'failures'=>[],'max_uid'=>$cursorUid,'uidvalidity'=>$session['uidvalidity'],'has_more'=>false];
            $search=$this->imapCommand($session['imap'],'UID SEARCH UID '.($cursorUid+1).':'.$end,65536,$deadline);
            $all = self::parseImapSearchResponse($search, $cursorUid + 1, $end);
            $uids=array_slice($all,0,max(1,min(self::MAX_IMAP_MESSAGES_PER_BATCH,$limit))); $msgs=[]; $fails=[]; $max=$cursorUid; $bytes=0;
            foreach ($uids as $i=>$uid) {
                try {
                    $resp=$this->imapCommand($session['imap'],'UID FETCH '.$uid.' (UID BODY.PEEK[HEADER.FIELDS (MESSAGE-ID SUBJECT FROM TO DATE IN-REPLY-TO)] BODY.PEEK[TEXT])',self::MAX_IMAP_MESSAGE_BYTES,$deadline);
                    $bytes+=strlen($resp); if ($bytes>self::MAX_IMAP_BATCH_BYTES) throw new RuntimeException('IMAP batch exceeds size limit.');
                    if (!preg_match('/\bUID\s+'.preg_quote((string)$uid,'/').'\b/i',$resp)) throw new RuntimeException('IMAP FETCH UID mismatch.');
                    $msgs[]=$this->parseImapMessage($resp,$uid,$mailboxEmail,(string)$imap['host'],$session['uidvalidity']); $max=$uid;
                } catch (Throwable $e) {
                    $fails[]=['uid'=>$uid,'code'=>$this->imapFailureCode($e)]; $max=$uid;
                    break; // malformed/truncated literal may have desynchronized the stream
                }
            }
            $processed=count($msgs)+count($fails); $hasMore=count($all)>$processed || $end<$high || $fails!==[];
            if ($fails===[] && $processed===count($all)) $max=$end; // safe to skip gaps only after complete bounded SEARCH window
            if ($all===[]) $max=$end;
        return ['messages'=>$msgs,'failures'=>$fails,'max_uid'=>$max,'uidvalidity'=>$session['uidvalidity'],'has_more'=>$hasMore];
    }

    /** @return list<int> */
    private static function parseImapSearchResponse(string $response, int $startUid, int $endUid): array
    {
        preg_match_all('/^\* SEARCH(?:[ \t]+([^\r\n]*))?\r?$/mi', $response, $matches, PREG_SET_ORDER);
        if (count($matches) !== 1) throw new RuntimeException('IMAP SEARCH response was missing or malformed; cursor was not advanced.');
        $payload = trim((string)($matches[0][1] ?? ''));
        if ($payload === '') return [];
        $uids = [];
        foreach (preg_split('/[ \t]+/', $payload) as $token) {
            if (preg_match('/^[1-9][0-9]{0,9}$/D', $token) !== 1) throw new RuntimeException('IMAP SEARCH returned a malformed UID; cursor was not advanced.');
            $uid = (int)$token;
            if ($uid < $startUid || $uid > $endUid) throw new RuntimeException('IMAP SEARCH returned a UID outside the requested range; cursor was not advanced.');
            $uids[] = $uid;
        }
        if (count($uids) !== count(array_unique($uids))) throw new RuntimeException('IMAP SEARCH returned duplicate UIDs; cursor was not advanced.');
        sort($uids, SORT_NUMERIC);
        return $uids;
    }

    private function openImapSession(array $imap, ?float $deadline = null): array
    {
        $host=trim((string)($imap['host']??'')); $mode=strtolower(trim((string)($imap['encryption']??'ssl'))); $port=(int)($imap['port']??993);
        $user=(string)($imap['username']??''); $pass=(string)($imap['password']??'');
        if ($host===''||$user===''||$pass==='') throw new RuntimeException('IMAP configuration is incomplete.');
        $imap['host']=$host; $imap['port']=$port; $imap['encryption']=$mode; $imap['username']=$user; $imap['password']=$pass;
        $select=$this->imapCommand($imap,'STATUS INBOX (UIDVALIDITY UIDNEXT)',65536,$deadline);
        if (!preg_match('/\bUIDVALIDITY\s+(\d+)\b/i',$select,$v)||!preg_match('/\bUIDNEXT\s+(\d+)\b/i',$select,$n)) throw new RuntimeException('IMAP STATUS omitted UIDVALIDITY or UIDNEXT.');
        return ['imap'=>$imap,'uidvalidity'=>(int)$v[1],'uidnext'=>(int)$n[1]];
    }

    private function imapFailureCode(Throwable $e): string
    {
        $m=strtolower($e->getMessage());
        if (str_contains($m,'exceeds')||str_contains($m,'size limit')) return 'MESSAGE_TOO_LARGE';
        if (str_contains($m,'malformed')||str_contains($m,'mismatch')||str_contains($m,'truncated')) return 'MESSAGE_MALFORMED';
        if (str_contains($m,'timed out')||str_contains($m,'timeout')) return 'FETCH_TIMEOUT';
        return 'FETCH_FAILED';
    }

    private function recordImapFailure(int $org,int $mailbox,int $validity,int $uid,string $code): void
    {
        $driver=(string)$this->db->getAttribute(PDO::ATTR_DRIVER_NAME); $now=gmdate('Y-m-d H:i:s'); $public='mif_'.bin2hex(random_bytes(10));
        $sql=$driver==='sqlite' ? 'INSERT INTO crm_mail_sync_failures(public_id,organization_id,mailbox_id,uidvalidity,uid,error_code,attempts,created_at,updated_at) VALUES(:p,:o,:m,:v,:u,:c,1,:n,:n) ON CONFLICT(organization_id,mailbox_id,uidvalidity,uid) DO UPDATE SET error_code=excluded.error_code,attempts=attempts+1,updated_at=excluded.updated_at' : 'INSERT INTO crm_mail_sync_failures(public_id,organization_id,mailbox_id,uidvalidity,uid,error_code,attempts,created_at,updated_at) VALUES(:p,:o,:m,:v,:u,:c,1,:n,:n) ON DUPLICATE KEY UPDATE error_code=VALUES(error_code),attempts=attempts+1,updated_at=VALUES(updated_at)';
        $this->db->prepare($sql)->execute([':p'=>$public,':o'=>$org,':m'=>$mailbox,':v'=>$validity,':u'=>$uid,':c'=>$code,':n'=>$now]);
    }

    public function listImapFailures(int $org,string $publicId,int $limit=100): array
    {
        $limit=max(1,min(100,$limit)); $st=$this->db->prepare('SELECT f.uid,f.uidvalidity,f.error_code,f.attempts,f.updated_at FROM crm_mail_sync_failures f INNER JOIN crm_mail_mailboxes m ON m.id=f.mailbox_id AND m.organization_id=f.organization_id WHERE f.organization_id=:org AND m.public_id=:pub AND f.uidvalidity=m.sync_uidvalidity ORDER BY f.uid LIMIT :lim');
        $st->bindValue(':org',$org,PDO::PARAM_INT); $st->bindValue(':pub',$publicId); $st->bindValue(':lim',$limit,PDO::PARAM_INT); $st->execute(); return $st->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    public function retryImapFailure(int $org,string $publicId,int $validity,int $uid): array
    {
        if ($org<=0||$validity<=0||$uid<=0) throw new RuntimeException('UID and UIDVALIDITY are required.');
        $st=$this->db->prepare('SELECT id,email_address,auth_config_encrypted,sync_uidvalidity FROM crm_mail_mailboxes WHERE organization_id=:org AND public_id=:pub AND is_active=1 LIMIT 1'); $st->execute([':org'=>$org,':pub'=>$publicId]); $m=$st->fetch(PDO::FETCH_ASSOC);
        if (!$m||(int)$m['sync_uidvalidity']!==$validity) throw new RuntimeException('Mailbox or current UIDVALIDITY was not found in this workspace.');
        $f=$this->db->prepare('SELECT 1 FROM crm_mail_sync_failures WHERE organization_id=:org AND mailbox_id=:mid AND uidvalidity=:v AND uid=:uid'); $f->execute([':org'=>$org,':mid'=>(int)$m['id'],':v'=>$validity,':uid'=>$uid]); if (!$f->fetchColumn()) throw new RuntimeException('UID has no pending failure.');
        $token=bin2hex(random_bytes(24)); $now=gmdate('Y-m-d H:i:s'); $claim=$this->db->prepare("UPDATE crm_mail_mailboxes SET sync_status='syncing',sync_lock_token=:t,sync_lock_until=:until,updated_at=:n WHERE id=:id AND organization_id=:org AND (sync_lock_token IS NULL OR sync_lock_until IS NULL OR sync_lock_until<:n)"); $claim->execute([':t'=>$token,':until'=>gmdate('Y-m-d H:i:s',time()+self::SYNC_LEASE_SECONDS),':n'=>$now,':id'=>(int)$m['id'],':org'=>$org]); if ($claim->rowCount()!==1) throw new RuntimeException('Mailbox sync is already running.');
        try {
            $cfg=$this->decryptConfig((string)$m['auth_config_encrypted']);
            $message=$this->imapRetryFetcher!==null ? ($this->imapRetryFetcher)((array)($cfg['imap']??[]),(string)$m['email_address'],$uid,$validity) : $this->fetchSingleImapMessage((array)($cfg['imap']??[]),(string)$m['email_address'],$uid,$validity);
            unset($message['uid']); $this->db->beginTransaction(); $this->ingestMessage($org,$publicId,$message);
            $this->db->prepare('DELETE FROM crm_mail_sync_failures WHERE organization_id=:org AND mailbox_id=:mid AND uidvalidity=:v AND uid=:uid')->execute([':org'=>$org,':mid'=>(int)$m['id'],':v'=>$validity,':uid'=>$uid]);
            $remain=$this->db->prepare('SELECT COUNT(*) FROM crm_mail_sync_failures WHERE organization_id=:org AND mailbox_id=:mid AND uidvalidity=:v'); $remain->execute([':org'=>$org,':mid'=>(int)$m['id'],':v'=>$validity]); $hasFailures=(int)$remain->fetchColumn()>0;
            $release=$this->db->prepare("UPDATE crm_mail_mailboxes SET sync_status=:status,last_error=:error,sync_lock_token=NULL,sync_lock_until=NULL,updated_at=:n WHERE id=:id AND organization_id=:org AND sync_lock_token=:t AND sync_lock_until>=:lease_now"); $release->execute([':status'=>$hasFailures?'error':'synced',':error'=>$hasFailures?'Есть другие UID для повтора.':null,':n'=>gmdate('Y-m-d H:i:s'),':lease_now'=>gmdate('Y-m-d H:i:s'),':id'=>(int)$m['id'],':org'=>$org,':t'=>$token]); if ($release->rowCount()!==1) throw new RuntimeException('Sync lease expired.'); $this->db->commit();
            return ['ok'=>true,'uid'=>$uid,'message'=>'Письмо обработано, проблема UID снята.'];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            try {
                $this->db->beginTransaction();
                $release = $this->db->prepare("UPDATE crm_mail_mailboxes SET sync_status='error',last_error='UID retry failed; retry remains available.',sync_lock_token=NULL,sync_lock_until=NULL,updated_at=:n WHERE id=:id AND organization_id=:org AND sync_lock_token=:t AND sync_lock_until>=:lease_now");
                $release->execute([':n'=>gmdate('Y-m-d H:i:s'),':lease_now'=>gmdate('Y-m-d H:i:s'),':id'=>(int)$m['id'],':org'=>$org,':t'=>$token]);
                if ($release->rowCount() === 1) {
                    $this->recordImapFailure($org,(int)$m['id'],$validity,$uid,$this->imapFailureCode($e));
                    $this->db->commit();
                } else {
                    $this->db->rollBack(); // newer/expired lease: preserve mailbox and ledger
                }
            } catch (Throwable) { if ($this->db->inTransaction()) $this->db->rollBack(); }
            return ['ok'=>false,'uid'=>$uid,'message'=>'Не удалось повторить UID; ошибка сохраняется только при действующей блокировке.'];
        }
    }

    private function fetchSingleImapMessage(array $imap,string $email,int $uid,int $validity): array
    {
        $deadline=hrtime(true)/1e9+20.0; $session=$this->openImapSession($imap,$deadline);
        if ($session['uidvalidity']!==$validity) throw new RuntimeException('UIDVALIDITY changed; run sync first.');
        $resp=$this->imapCommand($session['imap'],'UID FETCH '.$uid.' (UID BODY.PEEK[HEADER.FIELDS (MESSAGE-ID SUBJECT FROM TO DATE IN-REPLY-TO)] BODY.PEEK[TEXT])',self::MAX_IMAP_MESSAGE_BYTES,$deadline);
        if (!preg_match('/\bUID\s+'.preg_quote((string)$uid,'/').'\b/i',$resp)) throw new RuntimeException('IMAP UID mismatch.');
        return $this->parseImapMessage($resp,$uid,$email,(string)$imap['host'],$session['uidvalidity']);
    }

    public static function isSafeHeaderValue(string $value): bool
    {
        return $value !== '' && preg_match('/[\x00-\x1F\x7F]/', $value) !== 1;
    }

    public static function isSafeMessageId(string $value): bool
    {
        return strlen($value) <= 254
            && preg_match('/\A<[^<>@\s\x00-\x1F\x7F]+@[^<>@\s\x00-\x1F\x7F]+>\z/D', $value) === 1;
    }

    public static function isSupportedMailTransport(int $port, string $mode, string $protocol): bool
    {
        $mode = strtolower(trim($mode));
        $protocol = strtolower(trim($protocol));
        if ($protocol === 'smtp') {
            return ($port === 465 && $mode === 'ssl')
                || (in_array($port, [25, 587], true) && in_array($mode, ['tls', 'starttls'], true));
        }
        if ($protocol === 'imap') {
            return ($port === 993 && $mode === 'ssl')
                || ($port === 143 && in_array($mode, ['tls', 'starttls'], true));
        }
        return false;
    }

    /** @param array<string,mixed> $config */
    private function assertMailTransportConfig(array $config): void
    {
        foreach (['smtp', 'imap'] as $protocol) {
            $settings = $config[$protocol] ?? null;
            if (!is_array($settings) || trim((string)($settings['host'] ?? '')) === '') {
                continue;
            }
            $defaultPort = $protocol === 'smtp' ? 587 : 993;
            $defaultMode = $protocol === 'smtp' ? 'tls' : 'ssl';
            $port = (int)($settings['port'] ?? $defaultPort);
            $mode = strtolower(trim((string)($settings['encryption'] ?? $defaultMode)));
            if (!self::isSupportedMailTransport($port, $mode, $protocol)) {
                throw new RuntimeException(strtoupper($protocol) . ' port and encryption mode are incompatible.');
            }
        }
    }

    /** @return resource */
    /**
     * Send one bounded IMAP/SMTP operation through libcurl. The original host
     * remains in the URL for TLS SNI/certificate verification; CURLOPT_RESOLVE
     * pins the TCP connection to a previously validated public IP.
     *
     * @return string bounded protocol response
     */
    private function performMailCurl(
        string $host,
        int $port,
        string $mode,
        string $protocol,
        string $username,
        string $password,
        ?string $command,
        ?array $smtpTransfer,
        int $maxResponseBytes,
        ?float $deadline = null,
        bool $probe = false
    ): string {
        $protocol = strtolower($protocol);
        $mode = strtolower($mode);
        if (!function_exists('curl_init')
            || !in_array($protocol, ['imap', 'smtp'], true)
            || !self::isSupportedMailTransport($port, $mode, $protocol)
            || preg_match('/[\x00-\x20\x7f\/\\@?#]/', $host)
        ) {
            throw new RuntimeException('Secure mail transport is unavailable or incorrectly configured.');
        }
        $curlProtocols = array_map('strtolower', (array)(curl_version()['protocols'] ?? []));
        if (!in_array($protocol, $curlProtocols, true)) {
            throw new RuntimeException('This PHP cURL build does not include the required secure mail protocol.');
        }
        if ($command !== null && preg_match('/^(?:NOOP|STATUS INBOX \(UIDVALIDITY UIDNEXT\)|UID SEARCH UID [1-9][0-9]{0,9}:[1-9][0-9]{0,9}|UID FETCH [1-9][0-9]{0,9} \(UID BODY\.PEEK\[HEADER\.FIELDS \(MESSAGE-ID SUBJECT FROM TO DATE IN-REPLY-TO\)\] BODY\.PEEK\[TEXT\]\))$/D', $command) !== 1) {
            throw new RuntimeException('IMAP command is not allowed.');
        }

        $deadline ??= hrtime(true) / 1e9 + 8.0;
        $addresses = $this->resolvePublicMailAddresses($host, $deadline);
        if ($addresses === []) {
            throw new RuntimeException('Mail server has no validated public address.');
        }
        $responseBody = '';
        $responseTooLarge = false;
        $dataStarted = false;
        $lastError = 'Secure mail transfer failed.';

        foreach ($addresses as $ip) {
            $remaining = $deadline - (hrtime(true) / 1e9);
            if ($remaining <= 0) {
                break;
            }
            $scheme = $protocol === 'imap'
                ? ($mode === 'ssl' ? 'imaps' : 'imap')
                : ($mode === 'ssl' ? 'smtps' : 'smtp');
            $urlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
            $url = $scheme . '://' . $urlHost . ':' . $port . ($protocol === 'imap' ? '/INBOX' : '/');
            $handle = curl_init($url);
            if ($handle === false) {
                throw new RuntimeException('Could not initialize secure mail transport.');
            }
            $protocolFlag = $protocol === 'imap'
                ? (CURLPROTO_IMAP | CURLPROTO_IMAPS)
                : (CURLPROTO_SMTP | CURLPROTO_SMTPS);
            $options = [
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_HEADER => false,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_MAXREDIRS => 0,
                CURLOPT_PROXY => '',
                CURLOPT_NOPROXY => '*',
                CURLOPT_PROTOCOLS => $protocolFlag,
                CURLOPT_CONNECTTIMEOUT_MS => (int)max(100, min(8000, $remaining * 1000)),
                CURLOPT_TIMEOUT_MS => (int)max(100, min(8000, $remaining * 1000)),
                CURLOPT_NOSIGNAL => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$responseBody, &$responseTooLarge, $maxResponseBytes): int {
                    if (strlen($responseBody) + strlen($chunk) > $maxResponseBytes) {
                        $responseTooLarge = true;
                        return 0;
                    }
                    $responseBody .= $chunk;
                    return strlen($chunk);
                },
            ];
            if (filter_var($host, FILTER_VALIDATE_IP) === false) {
                $resolveAddress = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;
                $options[CURLOPT_RESOLVE] = [$host . ':' . $port . ':' . $resolveAddress];
            }
            if ($username !== '' || $password !== '') {
                $options[CURLOPT_USERNAME] = $username;
                $options[CURLOPT_PASSWORD] = $password;
            }
            if ($mode !== 'ssl') {
                // CURLUSESSL_ALL requires STARTTLS and fails closed if it is not accepted.
                $options[CURLOPT_USE_SSL] = CURLUSESSL_ALL;
            }
            if ($command !== null) {
                $options[CURLOPT_CUSTOMREQUEST] = $command;
            }
            if ($probe) {
                $options[CURLOPT_NOBODY] = true;
            }
            if ($smtpTransfer !== null) {
                $message = (string)$smtpTransfer['message'];
                $options[CURLOPT_MAIL_FROM] = '<' . (string)$smtpTransfer['from'] . '>';
                $options[CURLOPT_MAIL_RCPT] = array_map(static fn(string $email): string => '<' . $email . '>', (array)$smtpTransfer['recipients']);
                $offset = 0;
                $options[CURLOPT_UPLOAD] = true;
                $options[CURLOPT_INFILESIZE] = strlen($message);
                $options[CURLOPT_READFUNCTION] = static function ($curl, $stream, int $length) use (&$offset, $message, &$dataStarted, $smtpTransfer): string {
                    if ($offset >= strlen($message)) {
                        return '';
                    }
                    $dataStarted = true;
                    if (isset($smtpTransfer['mark_data_started']) && is_callable($smtpTransfer['mark_data_started'])) {
                        ($smtpTransfer['mark_data_started'])();
                    }
                    $chunk = substr($message, $offset, $length);
                    $offset += strlen($chunk);
                    return $chunk;
                };
            }
            curl_setopt_array($handle, $options);
            $responseBody = '';
            $responseTooLarge = false;
            $ok = curl_exec($handle);
            $curlError = curl_error($handle);
            curl_close($handle);

            if ($responseTooLarge) {
                throw new RuntimeException('Mail server response exceeds the safety limit.');
            }
            if ($ok !== false) {
                return $responseBody;
            }
            $lastError = $probe
                ? 'Mail server connection, STARTTLS or authentication check failed.'
                : ($protocol === 'imap' ? 'IMAP command failed or timed out.' : 'SMTP transfer failed.');
            if ($dataStarted) {
                throw new RuntimeException('SMTP delivery is uncertain after DATA began; automatic retry is blocked.');
            }
            // Do not disclose provider error strings: they may include account details.
            if ($deadline - (hrtime(true) / 1e9) <= 0) {
                break;
            }
            unset($curlError);
        }

        throw new RuntimeException($lastError);
    }

    private function imapCommand(array $imap, string $command, int $maxResponseBytes = 65536, ?float $deadline = null): string
    {
        if ($deadline !== null && hrtime(true) / 1e9 >= $deadline) {
            throw new RuntimeException('IMAP batch time budget reached; remaining UID work is available in a later batch.');
        }
        return $this->performMailCurl(
            (string)$imap['host'], (int)$imap['port'], (string)$imap['encryption'], 'imap',
            (string)$imap['username'], (string)$imap['password'], $command, null,
            $maxResponseBytes, $deadline
        );
    }

    /** @return array<string, mixed> */
    private function parseImapMessage(string $response, int $uid, string $mailboxEmail, string $host, int $uidValidity = 1): array
    {
        $header = '';
        if (preg_match('/BODY\[HEADER\.FIELDS[^\]]*\]\s*\{\d+\}\r?\n(.*?)(?:\r?\n)BODY\[TEXT\]/is', $response, $match)) {
            $header = trim($match[1]);
        }
        $body = '';
        if (preg_match('/BODY\[TEXT\]\s*\{\d+\}\r?\n(.*?)(?:\r?\n)?(?:\*\s+\d+\s+FETCH|A\d{3}\s+OK)/is', $response, $match)) {
            $body = trim($match[1]);
        }
        if ($header === '') {
            throw new RuntimeException('IMAP FETCH response is malformed; the UID will be retried.');
        }
        $fields = [];
        foreach (preg_split('/\r?\n(?![ \t])/', $header) as $line) {
            if (preg_match('/^([A-Za-z-]+):\s*(.*)$/', $line, $field)) {
                $fields[strtolower($field[1])] = trim($field[2]);
            }
        }
        $messageId = trim((string)($fields['message-id'] ?? ''));
        if ($messageId === '') {
            $messageId = '<imap-' . $uid . '-' . $uidValidity . '-' . hash('sha256', $host . chr(0) . $mailboxEmail) . '@tropatt.local>';
        }
        $from = $this->parseMailAddress((string)($fields['from'] ?? ''));
        $to = $this->parseMailAddress((string)($fields['to'] ?? ''));
        return [
            'uid' => $uid,
            'message_id_header' => substr($messageId, 0, 255),
            'in_reply_to' => $fields['in-reply-to'] ?? null,
            'sender_email' => $from['email'],
            'sender_name' => $from['name'],
            'recipient_email' => $to['email'] ?: $mailboxEmail,
            'subject' => $this->decodeMailHeader((string)($fields['subject'] ?? '(No subject)')),
            'body_text' => substr($body, 0, 1000000),
            'sent_at' => $this->parseMailDate((string)($fields['date'] ?? '')),
            'direction' => 'inbound',
        ];
    }

    /** @return array{email: string, name: ?string} */
    private function parseMailAddress(string $value): array
    {
        $value = trim($value);
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $value, $match)) {
            return ['email' => trim($match[2]), 'name' => trim($match[1], " \t\"'") ?: null];
        }
        return ['email' => trim($value, " <>\t"), 'name' => null];
    }

    private function decodeMailHeader(string $value): string
    {
        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if ($decoded !== false) {
                return substr($decoded, 0, 500);
            }
        }
        return substr($value, 0, 500);
    }

    private function parseMailDate(string $value): string
    {
        $time = $value !== '' ? strtotime($value) : false;
        return $time === false ? gmdate('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', $time);
    }

    private function safeProviderError(string $message): string
    {
        $message = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $message) ?? '';
        $message = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', '[mailbox]', $message) ?? $message;
        return mb_substr(trim($message) ?: 'Ошибка подключения к почтовому серверу.', 0, 500);
    }

    /** @param array<int, string> $recipients @return array{ok: bool, message_public_id: string, message: string, delivery_status: string} */
    public function sendMessage(int $organizationId, string $publicId, array $recipients, string $subject, string $body, ?string $replyTo, string $idempotencyKey): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email_address, auth_config_encrypted FROM crm_mail_mailboxes WHERE public_id = :public_id AND organization_id = :organization_id AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([':public_id' => $publicId, ':organization_id' => $organizationId]);
        $mailbox = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$mailbox) {
            throw new RuntimeException('Mailbox not found or inactive.');
        }
        $config = $this->decryptConfig((string)$mailbox['auth_config_encrypted']);
        $smtp = $config['smtp'] ?? [];
        if (!is_array($smtp)) {
            throw new RuntimeException('SMTP configuration is missing.');
        }
        $recipients = array_values(array_unique(array_filter(array_map('trim', $recipients))));
        if ($recipients === [] || count($recipients) > 50 || count(array_filter($recipients, static fn(string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) === false)) > 0) {
            throw new RuntimeException('One to fifty valid recipient addresses are required.');
        }
        $subject = trim($subject);
        $body = trim($body);
        if ($subject === '' || strlen($subject) > 500 || !self::isSafeHeaderValue($subject) || $body === '' || strlen($body) > 1000000) {
            throw new RuntimeException('Subject and body are required and must be within size limits.');
        }
        if ($replyTo !== null && $replyTo !== '' && !self::isSafeMessageId($replyTo)) {
            throw new RuntimeException('Reply reference is invalid.');
        }
        $idempotencyKey = trim($idempotencyKey);
        if (strlen($idempotencyKey) < 16 || strlen($idempotencyKey) > 128 || preg_match('/^[A-Za-z0-9._:-]+$/D', $idempotencyKey) !== 1) {
            throw new RuntimeException('A valid idempotency key between 16 and 128 characters is required.');
        }
        $from = (string)($smtp['from_address'] ?? $mailbox['email_address']);
        if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('SMTP sender address is invalid.');
        }
        $host = (string)($smtp['host'] ?? '');
        $port = (int)($smtp['port'] ?? 587);
        $mode = strtolower((string)($smtp['encryption'] ?? 'tls'));
        if (!self::isSupportedMailTransport($port, $mode, 'smtp')) {
            throw new RuntimeException('SMTP port and encryption mode are incompatible.');
        }
        $username = (string)($smtp['username'] ?? '');
        $password = (string)($smtp['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeException('SMTP credentials are missing.');
        }
        $mailboxId = (int)$mailbox['id'];
        $idempotencyHash = hash('sha256', $idempotencyKey);
        $requestHash = hash('sha256', json_encode([
            'recipients' => $recipients,
            'subject' => $subject,
            'body' => $body,
            'reply_to' => $replyTo,
            'from' => $from,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        // Keep configuration-controlled SMTP host text out of RFC headers.
        $messageId = '<' . bin2hex(random_bytes(16)) . '@tropatt.local>';
        $threadId = $replyTo !== null && $replyTo !== '' ? $this->resolveReplyThreadId($organizationId, $mailboxId, $replyTo) : $messageId;
        $messagePublicId = 'msg_' . bin2hex(random_bytes(10));
        $reservation = $this->reserveSendAttempt($organizationId, $mailboxId, $idempotencyHash, $requestHash, $messageId, $messagePublicId);
        if ($reservation['replay'] === 'sent') {
            return ['ok' => true, 'message_public_id' => $reservation['message_public_id'], 'message' => 'Письмо уже было отправлено этим запросом.', 'delivery_status' => 'sent'];
        }
        if ($reservation['replay'] !== 'new' && $reservation['replay'] !== 'retryable') {
            throw new RuntimeException('Delivery status is ' . $reservation['replay'] . '; retry is blocked to prevent a duplicate email.');
        }

        $dataStarted = false;
        try {
            $subjectHeader = function_exists('iconv_mime_encode') && preg_match('/[^\x20-\x7E]/', $subject)
                ? (preg_replace('/^Subject:\s*/i', '', (string)iconv_mime_encode('Subject', $subject, ['scheme' => 'B', 'input-charset' => 'UTF-8', 'output-charset' => 'UTF-8'])) ?: $subject)
                : $subject;
            $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
            // Quoted-printable keeps the transport 7-bit clean even when the
            // SMTP server does not advertise the optional 8BITMIME extension.
            $encodedBody = quoted_printable_encode($normalizedBody);
            // PHP's encoder emits CRLF for soft line breaks. Normalize before
            // restoring SMTP CRLF so a line ending is never doubled.
            $encodedBody = str_replace(["\r\n", "\r"], "\n", $encodedBody);
            $headers = [
                'Date: ' . gmdate('r'), 'Message-ID: ' . $messageId, 'From: ' . $from,
                'To: ' . implode(', ', $recipients), 'Subject: ' . $subjectHeader,
                'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8',
                'Content-Transfer-Encoding: quoted-printable',
            ];
            if ($replyTo !== null && $replyTo !== '') $headers[] = 'In-Reply-To: ' . $replyTo;
            $headers[] = '';
            $headers[] = str_replace("\n", "\r\n", $encodedBody);
            $message = implode("\r\n", $headers) . "\r\n";

            $markDataStarted = static function () use (&$dataStarted): void { $dataStarted = true; };
            if ($this->smtpTransport !== null) {
                ($this->smtpTransport)(['host' => $host, 'port' => $port, 'mode' => $mode, 'username' => $username, 'password' => $password, 'from' => $from, 'recipients' => $recipients, 'message' => $message], $markDataStarted);
                // Test transports must declare when the remote SMTP server accepted DATA.
                if (!$dataStarted) {
                    throw new RuntimeException('SMTP transport returned without confirming DATA acceptance.');
                }
            } else {
                $this->transmitSmtp($host, $port, $mode, $username, $password, $from, $recipients, $message, $markDataStarted);
            }

            $now = gmdate('Y-m-d H:i:s');
            $this->db->beginTransaction();
            try {
                $insert = $this->db->prepare(
                    "INSERT INTO crm_mail_messages (public_id, organization_id, mailbox_id, message_id_header, thread_id, in_reply_to, sender_email, recipient_email, subject, body_text, direction, sent_at, created_at)
                     VALUES (:public_id, :organization_id, :mailbox_id, :message_id, :thread_id, :reply_to, :sender, :recipient, :subject, :body, 'outbound', :sent_at, :created_at)"
                );
                $insert->execute([':public_id' => $messagePublicId, ':organization_id' => $organizationId, ':mailbox_id' => $mailboxId, ':message_id' => $messageId, ':thread_id' => $threadId, ':reply_to' => $replyTo, ':sender' => $from, ':recipient' => implode(', ', $recipients), ':subject' => $subject, ':body' => $body, ':sent_at' => $now, ':created_at' => $now]);
                $this->updateSendAttempt($organizationId, $mailboxId, $idempotencyHash, 'sent', $messagePublicId, $messageId);
                $this->db->commit();
            } catch (Throwable $e) {
                if ($this->db->inTransaction()) $this->db->rollBack();
                throw $e;
            }
            return ['ok' => true, 'message_public_id' => $messagePublicId, 'message' => 'Письмо отправлено.', 'delivery_status' => 'sent'];
        } catch (Throwable $e) {
            // Once DATA may have reached the provider, never send the same request again blindly.
            $state = $dataStarted ? 'uncertain' : 'failed';
            try { $this->updateSendAttempt($organizationId, $mailboxId, $idempotencyHash, $state, $messagePublicId, $messageId); } catch (Throwable) { /* preserve original failure; a stuck sending row also fails closed */ }
            throw new RuntimeException($dataStarted
                ? 'The mail provider may have accepted this message; delivery is uncertain and retry is blocked to prevent duplicates.'
                : 'The message was not accepted by SMTP and can be retried with the same idempotency key.', 0, $e);
        }
    }

    /** @return array{replay: string, message_public_id: string} */
    private function reserveSendAttempt(int $organizationId, int $mailboxId, string $keyHash, string $requestHash, string $messageId, string $messagePublicId): array
    {
        $this->db->beginTransaction();
        try {
            try {
                $insert = $this->db->prepare("INSERT INTO crm_mail_send_attempts (organization_id, mailbox_id, idempotency_hash, request_hash, status, message_public_id, message_id_header, created_at, updated_at) VALUES (:org, :mailbox, :key_hash, :request_hash, 'sending', :public_id, :message_id, :now, :now)");
                $now = gmdate('Y-m-d H:i:s');
                $insert->execute([':org' => $organizationId, ':mailbox' => $mailboxId, ':key_hash' => $keyHash, ':request_hash' => $requestHash, ':public_id' => $messagePublicId, ':message_id' => $messageId, ':now' => $now]);
                $this->db->commit();
                return ['replay' => 'new', 'message_public_id' => $messagePublicId];
            } catch (\PDOException $e) {
                // Duplicate key means the idempotency record already exists. Keep it untouched.
                $existing = $this->db->prepare('SELECT request_hash, status, message_public_id FROM crm_mail_send_attempts WHERE organization_id = :org AND mailbox_id = :mailbox AND idempotency_hash = :key_hash LIMIT 1');
                $existing->execute([':org' => $organizationId, ':mailbox' => $mailboxId, ':key_hash' => $keyHash]);
                $row = $existing->fetch(PDO::FETCH_ASSOC);
                if (!$row) throw $e;
                $this->db->commit();
                if (!hash_equals((string)$row['request_hash'], $requestHash)) throw new RuntimeException('Idempotency key was already used for a different message.');
                $status = (string)$row['status'];
                if ($status === 'failed') {
                    $this->db->beginTransaction();
                    $retry = $this->db->prepare("UPDATE crm_mail_send_attempts SET status = 'sending', message_public_id = :public_id, message_id_header = :message_id, updated_at = :now WHERE organization_id = :org AND mailbox_id = :mailbox AND idempotency_hash = :key_hash AND status = 'failed'");
                    $retry->execute([':public_id' => $messagePublicId, ':message_id' => $messageId, ':now' => gmdate('Y-m-d H:i:s'), ':org' => $organizationId, ':mailbox' => $mailboxId, ':key_hash' => $keyHash]);
                    $this->db->commit();
                    if ($retry->rowCount() === 1) return ['replay' => 'retryable', 'message_public_id' => $messagePublicId];
                    return ['replay' => 'sending', 'message_public_id' => (string)$row['message_public_id']];
                }
                return ['replay' => $status, 'message_public_id' => (string)$row['message_public_id']];
            }
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    private function updateSendAttempt(int $organizationId, int $mailboxId, string $keyHash, string $status, string $messagePublicId, string $messageId): void
    {
        $stmt = $this->db->prepare('UPDATE crm_mail_send_attempts SET status = :status, message_public_id = :public_id, message_id_header = :message_id, updated_at = :now WHERE organization_id = :org AND mailbox_id = :mailbox AND idempotency_hash = :key_hash');
        $stmt->execute([':status' => $status, ':public_id' => $messagePublicId, ':message_id' => $messageId, ':now' => gmdate('Y-m-d H:i:s'), ':org' => $organizationId, ':mailbox' => $mailboxId, ':key_hash' => $keyHash]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Mail send attempt could not be updated.');
    }

    /** @param array<int, string> $recipients @param callable(): void $markDataStarted */
    private function transmitSmtp(string $host, int $port, string $mode, string $username, string $password, string $from, array $recipients, string $message, callable $markDataStarted): void
    {
        $deadline = hrtime(true) / 1e9 + 20.0;
        $dataStarted = false;
        $markAcceptedData = static function () use (&$dataStarted, $markDataStarted): void {
            $dataStarted = true;
            $markDataStarted();
        };
        $this->performMailCurl(
            $host, $port, $mode, 'smtp', $username, $password, null,
            ['from' => $from, 'recipients' => $recipients, 'message' => $message, 'mark_data_started' => $markAcceptedData],
            16384, $deadline
        );
        if (!$dataStarted) {
            throw new RuntimeException('SMTP upload did not begin after DATA was accepted.');
        }
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
        if ($headerId === '' || strlen($headerId) > 255 || preg_match('/[\x00-\x1F\x7F]/', $headerId)) {
            throw new RuntimeException("Missing Message-ID header.");
        }
        $inReplyTo = trim((string)($msg['in_reply_to'] ?? ''));
        if (strlen($inReplyTo) > 255 || preg_match('/[\x00-\x1F\x7F]/', $inReplyTo)) {
            throw new RuntimeException('Invalid In-Reply-To header.');
        }
        $senderEmail = trim((string)($msg['sender_email'] ?? ''));
        $recipientEmail = trim((string)($msg['recipient_email'] ?? ''));
        if (strlen($senderEmail) > 255 || ($senderEmail !== '' && !filter_var($senderEmail, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('Invalid sender email address.');
        }
        if (strlen($recipientEmail) > 255 || ($recipientEmail !== '' && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))) {
            throw new RuntimeException('Invalid recipient email address.');
        }
        $subject = trim((string)($msg['subject'] ?? '(No subject)'));
        $senderName = trim((string)($msg['sender_name'] ?? ''));
        $body = (string)($msg['body_text'] ?? '');
        if (strlen($subject) > 500 || strlen($senderName) > 128 || strlen($body) > 1000000) {
            throw new RuntimeException('Message exceeds the supported field size.');
        }

        // Deduplication
        $dup = $this->db->prepare(
            "SELECT id, public_id, intake_public_id, direction, message_id_header, sender_email, subject, body_text
             FROM crm_mail_messages WHERE organization_id = :org_id AND mailbox_id = :mbx_id AND message_id_header = :hdr LIMIT 1"
        );
        $dup->execute([':org_id' => $organizationId, ':mbx_id' => $mbxId, ':hdr' => $headerId]);
        $existingMessage = $dup->fetch(PDO::FETCH_ASSOC);
        if (is_array($existingMessage)) {
            $intakePublicId = trim((string)($existingMessage['intake_public_id'] ?? ''));
            if ($intakePublicId === '' && (string)($existingMessage['direction'] ?? '') === 'inbound') {
                $intakePublicId = $this->createIntakeItem(
                    $organizationId,
                    $headerId,
                    (string)($existingMessage['subject'] ?? ''),
                    (string)($existingMessage['sender_email'] ?? ''),
                    (string)($existingMessage['body_text'] ?? '')
                ) ?? '';
                if ($intakePublicId !== '') {
                    $this->db->prepare(
                        'UPDATE crm_mail_messages SET intake_public_id = :intake WHERE public_id = :public_id AND organization_id = :organization_id AND intake_public_id IS NULL'
                    )->execute([':intake' => $intakePublicId, ':public_id' => $existingMessage['public_id'], ':organization_id' => $organizationId]);
                }
            }
            return ['ok' => true, 'duplicate' => true, 'message_public_id' => null];
        }

        // Thread resolution
        $threadId = $headerId;
        if ($inReplyTo !== '') {
            $parentStmt = $this->db->prepare(
                "SELECT thread_id FROM crm_mail_messages WHERE organization_id = :org_id AND mailbox_id = :mbx_id AND message_id_header = :parent_hdr LIMIT 1"
            );
            $parentStmt->execute([':org_id' => $organizationId, ':mbx_id' => $mbxId, ':parent_hdr' => $inReplyTo]);
            $parentThread = $parentStmt->fetchColumn();
            $threadId = $parentThread ?: $inReplyTo;
        }
        if (strlen($threadId) > 128) {
            $threadId = 'thread_' . hash('sha256', $threadId);
        }
        $direction = (string)($msg['direction'] ?? 'inbound');
        if (!in_array($direction, ['inbound', 'outbound'], true)) {
            throw new RuntimeException('Invalid message direction.');
        }

        $now = date('Y-m-d H:i:s');
        $pubId = 'msg_' . bin2hex(random_bytes(10));
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
            ':in_reply' => $inReplyTo !== '' ? $inReplyTo : null,
            ':sender_email' => $senderEmail,
            ':sender_name' => $senderName !== '' ? $senderName : null,
            ':recipient_email' => $recipientEmail,
            ':subj' => $subject,
            ':body' => $body,
            ':intake' => null,
            ':dir' => $direction,
            ':sent' => (string)($msg['sent_at'] ?? $now),
            ':now' => $now,
        ]);

        if ($direction === 'inbound') {
            $intakePublicId = $this->createIntakeItem($organizationId, $headerId, $subject, $senderEmail, $body);
            if ($intakePublicId !== null) {
                $this->db->prepare(
                    'UPDATE crm_mail_messages SET intake_public_id = :intake WHERE public_id = :public_id AND organization_id = :organization_id'
                )->execute([':intake' => $intakePublicId, ':public_id' => $pubId, ':organization_id' => $organizationId]);
            }
        }

        return [
            'ok' => true,
            'duplicate' => false,
            'message_public_id' => $pubId,
        ];
    }

    private function createIntakeItem(int $organizationId, string $messageId, string $subject, string $sender, string $body): ?string
    {
        try {
            $this->db->query('SELECT 1 FROM intake_items LIMIT 1');
            $hasOrganization = $this->db->query('SELECT organization_id FROM intake_items LIMIT 0') !== false;
        } catch (Throwable) {
            return null;
        }
        $existing = $this->db->prepare(
            'SELECT public_id FROM intake_items WHERE external_source = :source AND external_id = :external_id'
            . ($hasOrganization ? ' AND organization_id = :organization_id' : '')
            . ' ORDER BY id ASC LIMIT 1'
        );
        $existingParams = [':source' => 'corporate_mail', ':external_id' => $messageId];
        if ($hasOrganization) {
            $existingParams[':organization_id'] = $organizationId;
        }
        $existing->execute($existingParams);
        $existingPublicId = trim((string)($existing->fetchColumn() ?: ''));
        if ($existingPublicId !== '') {
            return $existingPublicId;
        }
        $publicId = 'iin_' . bin2hex(random_bytes(10));
        $now = gmdate('Y-m-d H:i:s');
        $title = 'Email: ' . trim($subject);
        $description = "Входящее письмо из корпоративной почты.\nОтправитель: {$sender}\nMessage-ID: {$messageId}\n\n{$body}";
        if ($hasOrganization) {
            $stmt = $this->db->prepare(
                "INSERT INTO intake_items (public_id, title, description, status, source_type, source_ref, external_source, external_id, creator_user_id, organization_id, created_at, updated_at)
                 VALUES (:public_id, :title, :description, 'pending', 'email', 'corporate-mail', 'corporate_mail', :external_id, 1, :organization_id, :created_at, :updated_at)"
            );
            $stmt->execute([':public_id' => $publicId, ':title' => $title, ':description' => substr($description, 0, 1000000), ':external_id' => $messageId, ':organization_id' => $organizationId, ':created_at' => $now, ':updated_at' => $now]);
        } else {
            $stmt = $this->db->prepare(
                "INSERT INTO intake_items (public_id, title, description, status, source_type, source_ref, external_source, external_id, creator_user_id, created_at, updated_at)
                 VALUES (:public_id, :title, :description, 'pending', 'email', 'corporate-mail', 'corporate_mail', :external_id, 1, :created_at, :updated_at)"
            );
            $stmt->execute([':public_id' => $publicId, ':title' => $title, ':description' => substr($description, 0, 1000000), ':external_id' => $messageId, ':created_at' => $now, ':updated_at' => $now]);
        }
        return $publicId;
    }

    /**
     * List mailboxes for organization.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listMailboxes(int $organizationId): array
    {
        $now=gmdate('Y-m-d H:i:s');
        $this->db->prepare("UPDATE crm_mail_mailboxes SET sync_status='error',last_error='Синхронизация прервана по тайм-ауту; повтор доступен.',sync_lock_token=NULL,sync_lock_until=NULL,updated_at=:now WHERE organization_id=:org AND sync_status='syncing' AND (sync_lock_until IS NULL OR sync_lock_until<:now)")->execute([':now'=>$now,':org'=>$organizationId]);
        $stmt=$this->db->prepare("SELECT m.public_id,m.email_address,m.display_name,m.protocol_adapter,m.sync_status,m.last_error,m.last_synced_at,m.is_active,m.created_at,m.updated_at,(SELECT COUNT(*) FROM crm_mail_sync_failures f WHERE f.organization_id=m.organization_id AND f.mailbox_id=m.id AND f.uidvalidity=m.sync_uidvalidity) AS sync_failure_count FROM crm_mail_mailboxes m WHERE m.organization_id=:org ORDER BY m.id DESC");
        $stmt->execute([':org'=>$organizationId]); return $stmt->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    /** List recent conversation threads for one workspace mailbox. */
    public function listThreads(int $organizationId, string $mailboxPublicId, int $limit = 100): array
    {
        $mailboxId = $this->mailboxIdForWorkspace($organizationId, $mailboxPublicId);
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT m.thread_id,m.subject,m.sender_email,m.sender_name,m.recipient_email,m.sent_at,m.direction,
                    (SELECT COUNT(*) FROM crm_mail_messages c WHERE c.organization_id=:org_count AND c.mailbox_id=:mailbox_count AND c.thread_id=m.thread_id) AS message_count
             FROM crm_mail_messages m
             WHERE m.organization_id=:org_row AND m.mailbox_id=:mailbox_row
               AND NOT EXISTS (
                   SELECT 1 FROM crm_mail_messages n
                   WHERE n.organization_id=:org_cmp AND n.mailbox_id=:mailbox_cmp AND n.thread_id=m.thread_id
                     AND (n.sent_at>m.sent_at OR (n.sent_at=m.sent_at AND n.id>m.id))
               )
             ORDER BY m.sent_at DESC,m.id DESC LIMIT :limit'
        );
        $stmt->bindValue(':org_count', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':mailbox_count', $mailboxId, PDO::PARAM_INT);
        $stmt->bindValue(':org_row', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':mailbox_row', $mailboxId, PDO::PARAM_INT);
        $stmt->bindValue(':org_cmp', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':mailbox_cmp', $mailboxId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** Return a bounded plain-text conversation for a mailbox within its workspace. */
    public function listMessages(int $organizationId, string $mailboxPublicId, string $threadId, int $limit = 100): array
    {
        $threadId = trim($threadId);
        if ($threadId === '' || strlen($threadId) > 128) return [];
        $mailboxId = $this->mailboxIdForWorkspace($organizationId, $mailboxPublicId);
        $limit = max(1, min(100, $limit));
        $stmt = $this->db->prepare(
            'SELECT public_id,thread_id,message_id_header,in_reply_to,sender_email,sender_name,recipient_email,subject,
                    SUBSTR(body_text,1,12000) AS body_text,direction,sent_at
             FROM crm_mail_messages
             WHERE organization_id=:org AND mailbox_id=:mailbox AND thread_id=:thread
             ORDER BY sent_at DESC,id DESC LIMIT :limit'
        );
        $stmt->bindValue(':org', $organizationId, PDO::PARAM_INT);
        $stmt->bindValue(':mailbox', $mailboxId, PDO::PARAM_INT);
        $stmt->bindValue(':thread', $threadId, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function mailboxIdForWorkspace(int $organizationId, string $mailboxPublicId): int
    {
        if ($organizationId <= 0 || $mailboxPublicId === '' || strlen($mailboxPublicId) > 64) {
            throw new RuntimeException('MAILBOX_NOT_FOUND');
        }
        $stmt = $this->db->prepare('SELECT id FROM crm_mail_mailboxes WHERE organization_id=:org AND public_id=:public_id LIMIT 1');
        $stmt->execute([':org' => $organizationId, ':public_id' => $mailboxPublicId]);
        $id = (int)$stmt->fetchColumn();
        if ($id <= 0) throw new RuntimeException('MAILBOX_NOT_FOUND');
        return $id;
    }

    private function resolveReplyThreadId(int $organizationId, int $mailboxId, string $replyTo): string
    {
        $stmt = $this->db->prepare('SELECT thread_id FROM crm_mail_messages WHERE organization_id=:org AND mailbox_id=:mailbox AND message_id_header=:message_id LIMIT 1');
        $stmt->execute([':org' => $organizationId, ':mailbox' => $mailboxId, ':message_id' => $replyTo]);
        $threadId = (string)($stmt->fetchColumn() ?: $replyTo);
        return strlen($threadId) <= 128 ? $threadId : 'thread_' . hash('sha256', $threadId);
    }
}
