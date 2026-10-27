<?php
declare(strict_types=1);

namespace Module\Crm\PaystackAfrica\Service;

use PDO;
use PDOException;
use RuntimeException;

/** Stores workspace-specific Paystack settings without exposing the secret key. */
final class PaystackSettingsService
{
    private const KEY_INFO = 'crm.module.crm.paystack-africa.credentials';
    private const KEY_CONTEXT = 'TropaTT CRM';

    public function __construct(private readonly PDO $db)
    {
    }

    /** @return array{configured:bool,credential_valid:bool,mode:string,is_live:bool,public_key:string,secret_key_configured:bool,webhook_path:?string} */
    public function getSettings(int $workspaceId): array
    {
        $this->assertWorkspace($workspaceId);
        $stmt = $this->db->prepare('SELECT public_key, secret_key_encrypted, is_live FROM module_paystack_settings WHERE workspace_id = :workspace_id LIMIT 1');
        $stmt->execute(['workspace_id' => $workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return [
                'configured' => false,
                'credential_valid' => false,
                'mode' => 'test',
                'is_live' => false,
                'public_key' => '',
                'secret_key_configured' => false,
                'webhook_path' => null,
            ];
        }

        $isLive = (int)($row['is_live'] ?? 0) === 1;
        $mode = $isLive ? 'live' : 'test';
        $publicKey = (string)($row['public_key'] ?? '');
        $encrypted = (string)($row['secret_key_encrypted'] ?? '');
        $secretKeyConfigured = false;
        try {
            $secretKey = $encrypted !== '' ? $this->decryptSecret($encrypted) : '';
            $secretKeyConfigured = $this->keyMatchesMode($secretKey, 'sk_', $mode);
        } catch (RuntimeException) {
            // Status reveals only validity; crypto errors and stored values stay private.
        }

        $publicKeyValid = $this->keyMatchesMode($publicKey, 'pk_', $mode);
        $webhookLookup = $this->db->prepare('SELECT webhook_id FROM module_paystack_webhook_routes WHERE workspace_id = :workspace_id LIMIT 1');
        $webhookLookup->execute(['workspace_id' => $workspaceId]);
        $webhookId = $webhookLookup->fetchColumn();
        $webhookPath = is_string($webhookId) && preg_match('/^[a-f0-9]{32}$/D', $webhookId)
            ? '/api/v1/_module/crm.paystack-africa/webhook/' . $webhookId
            : null;
        return [
            'configured' => $publicKeyValid && $secretKeyConfigured,
            'credential_valid' => $publicKeyValid && $secretKeyConfigured,
            'mode' => $mode,
            'is_live' => $isLive,
            'public_key' => $publicKeyValid ? $publicKey : '',
            'secret_key_configured' => $secretKeyConfigured,
            'webhook_path' => $webhookPath,
        ];
    }

    /**
     * Replace the workspace credentials. The secret is required every time so
     * the browser never needs to receive or preserve a masked secret value.
     *
     * @return array{configured:bool,credential_valid:bool,mode:string,is_live:bool,public_key:string,secret_key_configured:bool,webhook_path:?string}
     */
    public function saveSettings(int $workspaceId, string $publicKey, string $secretKey, string $mode): array
    {
        $this->assertWorkspace($workspaceId);
        $mode = strtolower(trim($mode));
        $publicKey = trim($publicKey);
        $secretKey = trim($secretKey);
        if (!in_array($mode, ['test', 'live'], true)
            || !$this->keyMatchesMode($publicKey, 'pk_', $mode)
            || !$this->keyMatchesMode($secretKey, 'sk_', $mode)) {
            throw new \InvalidArgumentException('Provide valid Paystack public and secret keys for the selected mode.');
        }

        $encryptedSecret = $this->encryptSecret($secretKey);
        $isLive = $mode === 'live' ? 1 : 0;
        $this->db->beginTransaction();
        try {
            $check = $this->db->prepare('SELECT id FROM module_paystack_settings WHERE workspace_id = :workspace_id LIMIT 1');
            $check->execute(['workspace_id' => $workspaceId]);
            $id = $check->fetchColumn();
            if ($id !== false) {
                $update = $this->db->prepare('UPDATE module_paystack_settings SET public_key = :public_key, secret_key_encrypted = :secret, is_live = :is_live, updated_at = CURRENT_TIMESTAMP WHERE workspace_id = :workspace_id');
                $update->execute([
                    'public_key' => $publicKey,
                    'secret' => $encryptedSecret,
                    'is_live' => $isLive,
                    'workspace_id' => $workspaceId,
                ]);
            } else {
                $insert = $this->db->prepare('INSERT INTO module_paystack_settings (workspace_id, public_key, secret_key_encrypted, is_live, created_at, updated_at) VALUES (:workspace_id, :public_key, :secret, :is_live, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
                $insert->execute([
                    'workspace_id' => $workspaceId,
                    'public_key' => $publicKey,
                    'secret' => $encryptedSecret,
                    'is_live' => $isLive,
                ]);
            }
            $this->getOrCreateWebhookId($workspaceId);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->getSettings($workspaceId);
    }

    /** Resolve a webhook route and decrypt its key only for server-side signature verification. */
    public function getWebhookCredentials(string $webhookId): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $webhookId)) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT s.workspace_id, s.secret_key_encrypted, s.public_key, s.is_live
             FROM module_paystack_webhook_routes r
             INNER JOIN module_paystack_settings s ON s.workspace_id = r.workspace_id
             WHERE r.webhook_id = :webhook_id LIMIT 1'
        );
        $stmt->execute(['webhook_id' => $webhookId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        $mode = (int)($row['is_live'] ?? 0) === 1 ? 'live' : 'test';
        try {
            $secret = $this->decryptSecret((string)($row['secret_key_encrypted'] ?? ''));
        } catch (RuntimeException) {
            return null;
        }
        if (!$this->keyMatchesMode($secret, 'sk_', $mode)
            || !$this->keyMatchesMode((string)($row['public_key'] ?? ''), 'pk_', $mode)) {
            return null;
        }
        return ['workspace_id' => (int)$row['workspace_id'], 'secret_key' => $secret];
    }

    private function getOrCreateWebhookId(int $workspaceId): string
    {
        $find = $this->db->prepare('SELECT webhook_id FROM module_paystack_webhook_routes WHERE workspace_id = :workspace_id LIMIT 1');
        $find->execute(['workspace_id' => $workspaceId]);
        $existing = $find->fetchColumn();
        if (is_string($existing) && preg_match('/^[a-f0-9]{32}$/D', $existing)) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $webhookId = bin2hex(random_bytes(16));
            try {
                $insert = $this->db->prepare('INSERT INTO module_paystack_webhook_routes (workspace_id, webhook_id, created_at) VALUES (:workspace_id, :webhook_id, CURRENT_TIMESTAMP)');
                $insert->execute(['workspace_id' => $workspaceId, 'webhook_id' => $webhookId]);
                return $webhookId;
            } catch (PDOException $e) {
                $find->execute(['workspace_id' => $workspaceId]);
                $existing = $find->fetchColumn();
                if (is_string($existing) && preg_match('/^[a-f0-9]{32}$/D', $existing)) {
                    return $existing;
                }
                if (($e->errorInfo[0] ?? '') !== '23000') {
                    throw $e;
                }
            }
        }

        throw new RuntimeException('Unable to create a unique Paystack webhook route.');
    }

    private function encryptSecret(string $secret): string
    {
        if (!function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Secure Paystack credential storage is unavailable; enable PHP OpenSSL.');
        }
        $key = $this->encryptionKey();
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if (!is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to secure Paystack credentials.');
        }
        return 'v1:' . base64_encode($iv . $tag . $ciphertext);
    }

    private function decryptSecret(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'v1:')) {
            throw new RuntimeException('Stored Paystack credential is invalid.');
        }
        $payload = base64_decode(substr($encrypted, 3), true);
        if (!is_string($payload) || strlen($payload) < 29 || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Stored Paystack credential is invalid.');
        }
        $decrypted = openssl_decrypt(
            substr($payload, 28),
            'aes-256-gcm',
            $this->encryptionKey(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16)
        );
        if (!is_string($decrypted) || $decrypted === '') {
            throw new RuntimeException('Stored Paystack credential is invalid.');
        }
        return $decrypted;
    }

    private function encryptionKey(): string
    {
        $configuredKey = trim((string)(getenv('APP_KEY') ?: ($_SERVER['APP_KEY'] ?? $_ENV['APP_KEY'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/i', $configuredKey)) {
            throw new RuntimeException('Secure Paystack credential storage is unavailable; configure a valid 64-character hexadecimal APP_KEY.');
        }
        $masterKey = hex2bin($configuredKey);
        if (!is_string($masterKey)) {
            throw new RuntimeException('Secure Paystack credential storage is unavailable.');
        }
        return hash_hkdf('sha256', $masterKey, 32, self::KEY_INFO, self::KEY_CONTEXT);
    }

    private function keyMatchesMode(string $key, string $type, string $mode): bool
    {
        return strlen($key) <= 255
            && preg_match('/^' . preg_quote($type, '/') . '(test|live)_[A-Za-z0-9]{20,}$/D', $key) === 1
            && str_starts_with($key, $type . $mode . '_');
    }

    private function assertWorkspace(int $workspaceId): void
    {
        if ($workspaceId <= 0) {
            throw new RuntimeException('An active workspace is required.');
        }
    }
}
