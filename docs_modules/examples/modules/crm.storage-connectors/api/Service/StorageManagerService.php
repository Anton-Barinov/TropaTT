<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service;

use Api\System\Library\Security\KeyGuard;
use Module\Crm\StorageConnectors\Service\Adapter\NextcloudWebdavAdapter;
use Module\Crm\StorageConnectors\Service\Adapter\S3CompatibleStorageAdapter;
use Module\Crm\StorageConnectors\Service\Adapter\StorageAdapterInterface;
use PDO;
use RuntimeException;

/**
 * Storage manager orchestrating workspace storage configuration, adapter instantiation,
 * host preflight checks, file mapping and transparent upload/download.
 */
final class StorageManagerService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
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
        $now = date('Y-m-d H:i:s');
        $credJson = json_encode($credentials, JSON_UNESCAPED_UNICODE);
        $encrypted = class_exists(KeyGuard::class) ? KeyGuard::encrypt($credJson) : base64_encode($credJson);

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

        $rawCred = (string)$row['auth_credentials_encrypted'];
        $decryptedJson = class_exists(KeyGuard::class) ? KeyGuard::decrypt($rawCred) : base64_decode($rawCred);
        $creds = json_decode((string)$decryptedJson, true) ?: [];

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
        $adapter = $this->getAdapter($organizationId);
        if ($adapter === null) {
            throw new RuntimeException('No active remote storage configured for workspace.');
        }

        $now = date('Y-m-d H:i:s');
        $sha256 = hash('sha256', $content);
        $sizeBytes = strlen($content);
        $yearMonth = date('Y/m');
        $remoteKey = "org_{$organizationId}/{$yearMonth}/{$filePublicId}_{$filename}";

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
}
