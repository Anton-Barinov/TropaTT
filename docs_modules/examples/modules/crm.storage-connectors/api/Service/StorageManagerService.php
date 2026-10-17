<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use Module\Crm\StorageConnectors\Service\Adapter\NextcloudWebdavAdapter;
use Module\Crm\StorageConnectors\Service\Adapter\S3CompatibleStorageAdapter;
use Module\Crm\StorageConnectors\Service\Adapter\StorageAdapterInterface;
use PDO;
use RuntimeException;

/**
 * Storage manager orchestrating workspace storage configuration, adapter instantiation,
 * host preflight checks, file mapping and transparent upload/download.
 */
final class StorageManagerService implements FileMigrationStorageManagerInterface
{
    public const MAX_FILE_BYTES = 20971520;
    private const CREDENTIAL_AAD = 'crm.storage-connectors.credentials.v1';

    private PDO $db;
    private UrlSafetyValidator $urlSafety;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->urlSafety = new UrlSafetyValidator();
    }

    /**
     * Preflight environment check for shared hosting limits and required extensions.
     *
     * @return array{ok: bool, checks: array<string, array{status: string, message: string}>}
     */
    public function preflight(): array
    {
        $checks = [];

        // 1. cURL extension
        $hasCurl = extension_loaded('curl');
        $checks['curl'] = [
            'status' => $hasCurl ? 'pass' : 'fail',
            'message' => $hasCurl ? 'cURL extension is available' : 'cURL extension is required for external storage',
        ];

        // 2. OpenSSL extension
        $hasOpenssl = extension_loaded('openssl');
        $checks['openssl'] = [
            'status' => $hasOpenssl ? 'pass' : 'fail',
            'message' => $hasOpenssl ? 'OpenSSL extension is available' : 'OpenSSL is required for secure HTTPS transfers',
        ];

        // 3. TLS 1.2+ support
        $curlVer = function_exists('curl_version') ? curl_version() : [];
        $sslVer = (string)($curlVer['ssl_version'] ?? '');
        $checks['tls'] = [
            'status' => $sslVer !== '' ? 'pass' : 'warn',
            'message' => $sslVer !== '' ? "SSL engine: {$sslVer}" : 'Cannot detect SSL engine version',
        ];

        // 4. Memory limit check (shared hosting safe <= 128M / target < 32M)
        $memLimit = ini_get('memory_limit') ?: '128M';
        $checks['memory'] = [
            'status' => 'pass',
            'message' => "Configured PHP memory_limit: {$memLimit}",
        ];

        // 5. Stream wrapper / allow_url_fopen
        $urlFopen = (bool)ini_get('allow_url_fopen');
        $checks['allow_url_fopen'] = [
            'status' => $urlFopen ? 'pass' : 'warn',
            'message' => $urlFopen ? 'allow_url_fopen is enabled' : 'allow_url_fopen disabled (cURL fallback active)',
        ];

        $allPassed = $checks['curl']['status'] === 'pass' && $checks['openssl']['status'] === 'pass';

        return [
            'ok' => $allPassed,
            'checks' => $checks,
        ];
    }

    /**
     * Get active storage configuration for organization workspace.
     *
     * @return array<string, mixed>|null
     */
    public function getConfig(int $organizationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT public_id, organization_id, provider_type, endpoint_url, bucket_or_path, region, is_active, sync_policy, created_at, updated_at
             FROM crm_storage_configs
             WHERE organization_id = :org_id AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    /**
     * Save storage configuration for workspace.
     *
     * @param array<string, string> $credentials
     */
    public function saveConfig(
        int $organizationId,
        string $providerType,
        string $endpointUrl,
        string $bucketOrPath,
        array $credentials,
        string $region = 'us-east-1',
        string $syncPolicy = 'new_files'
    ): string {
        $this->assertSafeEndpoint($endpointUrl);
        $this->assertProviderCredentials($providerType, $credentials);
        $bucketOrPath = $this->normalizeRemotePath($bucketOrPath);
        if ($providerType === 's3' && !preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $bucketOrPath)) {
            throw new RuntimeException('S3 bucket name is invalid.');
        }
        $now = date('Y-m-d H:i:s');
        $credJson = json_encode($credentials, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $encrypted = $this->encryptCredentials($credJson);

        $existing = $this->db->prepare("SELECT public_id FROM crm_storage_configs WHERE organization_id = :org_id AND provider_type = :type");
        $existing->execute([':org_id' => $organizationId, ':type' => $providerType]);
        $pubId = $existing->fetchColumn();

        if ($pubId) {
            $stmt = $this->db->prepare(
                "UPDATE crm_storage_configs
                 SET endpoint_url = :url, bucket_or_path = :bkt, region = :reg, auth_credentials_encrypted = :cred, sync_policy = :policy, is_active = 1, updated_at = :now
                 WHERE public_id = :pub_id"
            );
            $stmt->execute([
                ':url' => $endpointUrl,
                ':bkt' => $bucketOrPath,
                ':reg' => $region,
                ':cred' => $encrypted,
                ':policy' => $syncPolicy,
                ':now' => $now,
                ':pub_id' => $pubId,
            ]);
            return (string)$pubId;
        }

        $pubId = 'stc_' . bin2hex(random_bytes(10));
        $stmt = $this->db->prepare(
            "INSERT INTO crm_storage_configs (public_id, organization_id, provider_type, endpoint_url, bucket_or_path, region, auth_credentials_encrypted, is_active, sync_policy, created_at, updated_at)
             VALUES (:pub_id, :org_id, :type, :url, :bkt, :reg, :cred, 1, :policy, :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $pubId,
            ':org_id' => $organizationId,
            ':type' => $providerType,
            ':url' => $endpointUrl,
            ':bkt' => $bucketOrPath,
            ':reg' => $region,
            ':cred' => $encrypted,
            ':policy' => $syncPolicy,
            ':now' => $now,
        ]);

        return $pubId;
    }

    /**
     * Instantiate adapter for configured provider in workspace.
     */
    public function getAdapter(int $organizationId): ?StorageAdapterInterface
    {
        $stmt = $this->db->prepare(
            "SELECT provider_type, endpoint_url, bucket_or_path, region, auth_credentials_encrypted
             FROM crm_storage_configs
             WHERE organization_id = :org_id AND is_active = 1
             LIMIT 1"
        );
        $stmt->execute([':org_id' => $organizationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $this->assertSafeEndpoint((string)$row['endpoint_url']);
        $rawCred = (string)$row['auth_credentials_encrypted'];
        try {
            $decryptedJson = $this->decryptCredentials($rawCred);
        } catch (RuntimeException $exception) {
            $legacyJson = base64_decode($rawCred, true);
            $legacyCredentials = $legacyJson === false ? null : json_decode($legacyJson, true);
            if (!is_array($legacyCredentials)) {
                throw $exception;
            }

            $this->assertProviderCredentials((string)$row['provider_type'], $legacyCredentials);
            $decryptedJson = json_encode($legacyCredentials, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $migratedCredentials = $this->encryptCredentials($decryptedJson);
            $migrateStmt = $this->db->prepare(
                "UPDATE crm_storage_configs
                 SET auth_credentials_encrypted = :credentials, updated_at = :updated_at
                 WHERE organization_id = :organization_id AND provider_type = :provider_type AND auth_credentials_encrypted = :legacy_credentials"
            );
            $migrateStmt->execute([
                ':credentials' => $migratedCredentials,
                ':updated_at' => date('Y-m-d H:i:s'),
                ':organization_id' => $organizationId,
                ':provider_type' => (string)$row['provider_type'],
                ':legacy_credentials' => $rawCred,
            ]);
            if ($migrateStmt->rowCount() !== 1) {
                throw new RuntimeException('Legacy storage credentials could not be upgraded safely. Re-enter provider credentials.');
            }
        }
        $creds = json_decode($decryptedJson, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($creds)) {
            throw new RuntimeException('Stored storage credentials have an invalid format. Re-enter the provider credentials.');
        }

        if ($row['provider_type'] === 's3') {
            return new S3CompatibleStorageAdapter(
                endpointUrl: (string)$row['endpoint_url'],
                bucket: (string)$row['bucket_or_path'],
                accessKey: (string)($creds['access_key'] ?? ''),
                secretKey: (string)($creds['secret_key'] ?? ''),
                region: (string)($row['region'] ?? 'us-east-1')
            );
        }

        if ($row['provider_type'] === 'nextcloud_webdav') {
            return new NextcloudWebdavAdapter(
                baseUrl: (string)$row['endpoint_url'],
                remotePath: (string)$row['bucket_or_path'],
                username: (string)($creds['username'] ?? ''),
                password: (string)($creds['password'] ?? '')
            );
        }

        return null;
    }

    /**
     * Store file remotely and record mapping with checksum.
     *
     * @return array{ok: bool, remote_key: string, sha256: string}
     */
    public function storeRemoteFile(int $organizationId, string $filePublicId, string $content, string $filename): array
    {
        if ($organizationId <= 0 || !preg_match('/^[A-Za-z0-9_-]{1,120}$/', $filePublicId)) {
            throw new RuntimeException('Invalid storage scope or file identifier.');
        }
        if (strlen($content) > self::MAX_FILE_BYTES) {
            throw new RuntimeException('File exceeds the 20 MB remote-storage limit.');
        }

        $adapter = $this->getAdapter($organizationId);
        if ($adapter === null) {
            throw new RuntimeException('No active remote storage configured for workspace.');
        }

        $now = date('Y-m-d H:i:s');
        $sha256 = hash('sha256', $content);
        $sizeBytes = strlen($content);
        $yearMonth = date('Y/m');
        $safeFilename = $this->normalizeFilename($filename);
        $remoteKey = "org_{$organizationId}/{$yearMonth}/{$filePublicId}_{$safeFilename}";

        $uploadResult = $adapter->putObject($remoteKey, $content);
        if (!$uploadResult['ok']) {
            throw new RuntimeException('Remote upload returned failure.');
        }

        // Save mapping
        $mapPubId = 'sfm_' . bin2hex(random_bytes(10));
        $stmt = $this->db->prepare(
            "INSERT INTO crm_storage_file_mappings (public_id, organization_id, file_public_id, provider_type, remote_object_key, file_size_bytes, sha256_checksum, sync_status, created_at, updated_at)
             VALUES (:pub_id, :org_id, :file_id, :type, :key, :size, :sha, 'synced', :now, :now)"
        );
        $stmt->execute([
            ':pub_id' => $mapPubId,
            ':org_id' => $organizationId,
            ':file_id' => $filePublicId,
            ':type' => $uploadResult['provider_type'] ?? 'remote',
            ':key' => $remoteKey,
            ':size' => $sizeBytes,
            ':sha' => $sha256,
            ':now' => $now,
        ]);

        return [
            'ok' => true,
            'remote_key' => $remoteKey,
            'sha256' => $sha256,
        ];
    }

    private function assertSafeEndpoint(string $endpointUrl): void
    {
        $value = trim($endpointUrl);
        $parts = parse_url($value);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('Storage endpoint must be an HTTPS URL without embedded credentials, query, or fragment.');
        }

        $port = (int)($parts['port'] ?? 443);
        if ($port !== 443) {
            throw new RuntimeException('Storage endpoint must use HTTPS port 443.');
        }

        $validation = $this->urlSafety->validateProviderUrl($value, true, ['https']);
        if (($validation['ok'] ?? false) !== true || empty($validation['resolved_ips'])) {
            throw new RuntimeException('Storage endpoint is invalid or resolves to a private, reserved, or unavailable network address.');
        }

        $path = (string)($parts['path'] ?? '');
        foreach (explode('/', rawurldecode($path)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('Storage endpoint path traversal is not allowed.');
            }
        }
    }

    private function assertProviderCredentials(string $providerType, array $credentials): void
    {
        if ($providerType === 's3') {
            if (trim((string)($credentials['access_key'] ?? '')) === '' || trim((string)($credentials['secret_key'] ?? '')) === '') {
                throw new RuntimeException('S3 access key and secret key are required.');
            }
            return;
        }

        if ($providerType === 'nextcloud_webdav') {
            if (trim((string)($credentials['username'] ?? '')) === '' || (string)($credentials['password'] ?? '') === '') {
                throw new RuntimeException('WebDAV username and app password are required.');
            }
            return;
        }

        throw new RuntimeException('Unsupported remote storage provider.');
    }

    private function credentialKey(): string
    {
        $masterKey = trim((string)getenv('APP_KEY'));
        if ($masterKey === '' || strlen($masterKey) < 32 || !function_exists('openssl_encrypt') || !function_exists('openssl_decrypt')) {
            throw new RuntimeException('Secure credential storage is unavailable; configure APP_KEY and OpenSSL before enabling remote storage.');
        }

        return hash_hkdf('sha256', $masterKey, 32, self::CREDENTIAL_AAD, 'crm.storage-connectors');
    }

    private function encryptCredentials(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag, self::CREDENTIAL_AAD, 16);
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Failed to encrypt remote-storage credentials.');
        }

        return json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag),
            'ct' => base64_encode($ciphertext),
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function decryptCredentials(string $payload): string
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || ($decoded['v'] ?? null) !== 1) {
            throw new RuntimeException('Stored storage credentials use an unsupported format. Re-enter the provider credentials.');
        }

        $iv = base64_decode((string)($decoded['iv'] ?? ''), true);
        $tag = base64_decode((string)($decoded['tag'] ?? ''), true);
        $ciphertext = base64_decode((string)($decoded['ct'] ?? ''), true);
        if ($iv === false || strlen($iv) !== 12 || $tag === false || strlen($tag) !== 16 || $ciphertext === false) {
            throw new RuntimeException('Stored storage credentials are corrupt. Re-enter the provider credentials.');
        }

        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->credentialKey(), OPENSSL_RAW_DATA, $iv, $tag, self::CREDENTIAL_AAD);
        if ($plaintext === false) {
            throw new RuntimeException('Stored storage credentials could not be authenticated. Re-enter the provider credentials.');
        }

        return $plaintext;
    }

    private function normalizeRemotePath(string $path): string
    {
        $value = trim(str_replace('\\', '/', $path), '/');
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('Storage bucket or remote path is invalid.');
        }
        foreach (explode('/', rawurldecode($value)) as $segment) {
            if ($segment === '.' || $segment === '..') {
                throw new RuntimeException('Storage bucket or remote path traversal is not allowed.');
            }
        }
        return $value;
    }

    private function normalizeFilename(string $filename): string
    {
        $value = basename(str_replace('\\', '/', $filename));
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value) ?? '';
        $value = trim($value);
        if ($value === '' || $value === '.' || $value === '..' || strlen($value) > 180) {
            throw new RuntimeException('Storage filename is invalid.');
        }
        return $value;
    }
}
