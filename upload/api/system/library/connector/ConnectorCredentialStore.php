<?php
declare(strict_types=1);

namespace Api\System\Library\Connector;

use Api\System\Library\Support\AppLog;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Encrypted, workspace-isolated server-side credential store for modules.
 * Uses AES-256-GCM authenticated encryption with sub-keys derived from APP_KEY via HKDF.
 */
final class ConnectorCredentialStore implements ConnectorCredentialStoreInterface
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ?string $masterKey = null,
        private readonly string $tableName = 'connector_credentials',
    ) {
    }

    public function setSecret(string $moduleName, int $organizationId, string $keyName, string $secret): void
    {
        $this->validateScope($moduleName, $organizationId, $keyName);

        $encryptionKey = $this->deriveKey($moduleName);
        $iv = random_bytes(self::IV_BYTES);
        $tag = '';

        $ciphertext = openssl_encrypt(
            $secret,
            self::CIPHER,
            $encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_BYTES
        );

        if ($ciphertext === false) {
            throw new RuntimeException('Failed to encrypt connector credential');
        }

        $payload = json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct' => base64_encode($ciphertext),
        ], JSON_UNESCAPED_SLASHES);

        $stmt = $this->pdo->prepare("SELECT id FROM {$this->tableName} WHERE module_name = :module AND organization_id = :org_id AND key_name = :key_name");
        $stmt->execute([
            'module' => $moduleName,
            'org_id' => $organizationId,
            'key_name' => $keyName,
        ]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = date('Y-m-d H:i:s');
        if ($existing) {
            $update = $this->pdo->prepare("UPDATE {$this->tableName} SET encrypted_value = :val, updated_at = :now WHERE id = :id");
            $update->execute([
                'val' => $payload,
                'now' => $now,
                'id' => (int)$existing['id'],
            ]);
        } else {
            $insert = $this->pdo->prepare("INSERT INTO {$this->tableName} (module_name, organization_id, key_name, encrypted_value, created_at, updated_at) VALUES (:module, :org_id, :key_name, :val, :created_at, :updated_at)");
            $insert->execute([
                'module' => $moduleName,
                'org_id' => $organizationId,
                'key_name' => $keyName,
                'val' => $payload,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function getSecret(string $moduleName, int $organizationId, string $keyName): ?string
    {
        $this->validateScope($moduleName, $organizationId, $keyName);

        $stmt = $this->pdo->prepare("SELECT encrypted_value FROM {$this->tableName} WHERE module_name = :module AND organization_id = :org_id AND key_name = :key_name");
        $stmt->execute([
            'module' => $moduleName,
            'org_id' => $organizationId,
            'key_name' => $keyName,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !isset($row['encrypted_value'])) {
            return null;
        }

        $decoded = json_decode((string)$row['encrypted_value'], true);
        if (!is_array($decoded) || empty($decoded['iv']) || empty($decoded['tag']) || empty($decoded['ct'])) {
            AppLog::warning('ConnectorCredentialStore: corrupted credential payload', [
                'module' => $moduleName,
                'org_id' => $organizationId,
                'key_name' => $keyName,
            ]);
            return null;
        }

        $iv = base64_decode((string)$decoded['iv'], true);
        $tag = base64_decode((string)$decoded['tag'], true);
        $ciphertext = base64_decode((string)$decoded['ct'], true);

        if ($iv === false || $tag === false || $ciphertext === false) {
            return null;
        }

        $encryptionKey = $this->deriveKey($moduleName);
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($plaintext === false) {
            AppLog::warning('ConnectorCredentialStore: decryption failed (key mismatch or tampered data)', [
                'module' => $moduleName,
                'org_id' => $organizationId,
                'key_name' => $keyName,
            ]);
            return null;
        }

        return $plaintext;
    }

    public function hasSecret(string $moduleName, int $organizationId, string $keyName): bool
    {
        $this->validateScope($moduleName, $organizationId, $keyName);

        $stmt = $this->pdo->prepare("SELECT 1 FROM {$this->tableName} WHERE module_name = :module AND organization_id = :org_id AND key_name = :key_name");
        $stmt->execute([
            'module' => $moduleName,
            'org_id' => $organizationId,
            'key_name' => $keyName,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    public function deleteSecret(string $moduleName, int $organizationId, string $keyName): bool
    {
        $this->validateScope($moduleName, $organizationId, $keyName);

        $stmt = $this->pdo->prepare("DELETE FROM {$this->tableName} WHERE module_name = :module AND organization_id = :org_id AND key_name = :key_name");
        $stmt->execute([
            'module' => $moduleName,
            'org_id' => $organizationId,
            'key_name' => $keyName,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function redact(string $secret, int $visibleChars = 4): string
    {
        $len = strlen($secret);
        if ($len === 0) {
            return '';
        }
        if ($len <= 6) {
            return '******';
        }
        $visibleChars = max(1, min($visibleChars, (int)floor($len / 3)));
        $prefix = substr($secret, 0, $visibleChars);
        $suffix = substr($secret, -$visibleChars);

        return $prefix . '...' . $suffix;
    }

    private function validateScope(string $moduleName, int $organizationId, string $keyName): void
    {
        if ($moduleName === '' || !preg_match('/^[a-z0-9][a-z0-9._-]{0,189}$/i', $moduleName)) {
            throw new InvalidArgumentException('Invalid module name for credential storage');
        }
        if ($organizationId <= 0) {
            throw new InvalidArgumentException('Credentials must be scoped to an authoritative workspace');
        }
        if ($keyName === '' || strlen($keyName) > 64 || !preg_match('/^[a-z0-9_.-]+$/i', $keyName)) {
            throw new InvalidArgumentException('Invalid key name for credential storage');
        }
    }

    private function deriveKey(string $moduleName): string
    {
        $base = $this->masterKey ?? (string)getenv('APP_KEY');
        if ($base === '') {
            $base = (string)getenv('WEBHOOK_SECRET_KEY');
        }
        if ($base === '') {
            $base = 'tropatt_default_connector_key_fallback_replace_in_prod';
        }

        return hash_hkdf('sha256', $base, 32, 'connector_credentials_v1', $moduleName);
    }
}
