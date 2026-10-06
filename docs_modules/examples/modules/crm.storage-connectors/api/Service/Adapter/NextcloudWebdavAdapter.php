<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service\Adapter;

use RuntimeException;

/**
 * Nextcloud / WebDAV storage backend adapter using native PHP cURL.
 */
final class NextcloudWebdavAdapter implements StorageAdapterInterface
{
    private string $baseUrl;
    private string $remotePath;
    private string $username;
    private string $password;
    private int $timeoutSeconds;

    public function __construct(
        string $baseUrl,
        string $remotePath,
        string $username,
        string $password,
        int $timeoutSeconds = 20
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->remotePath = '/' . trim($remotePath, '/');
        $this->username = $username;
        $this->password = $password;
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function testConnection(): array
    {
        $url = $this->baseUrl . $this->remotePath;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_HTTPHEADER => ['Depth: 0'],
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => "cURL error: {$err}"];
        }

        // WebDAV 207 Multi-Status or 200 OK or 405 means endpoint responds
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'message' => 'WebDAV connected successfully (HTTP ' . $httpCode . ')'];
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return ['ok' => false, 'message' => "Authentication failed (HTTP {$httpCode}). Check credentials."];
        }

        return ['ok' => false, 'message' => "WebDAV responded with HTTP {$httpCode}"];
    }

    public function putObject(string $objectKey, string $content): array
    {
        $cleanKey = ltrim($objectKey, '/');
        $url = $this->baseUrl . $this->remotePath . '/' . $cleanKey;

        $sha256 = hash('sha256', $content);
        $sizeBytes = strlen($content);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $sizeBytes,
                'OC-Checksum: SHA256:' . $sha256,
            ],
        ]);

        $res = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || ($httpCode < 200 || $httpCode >= 300)) {
            throw new RuntimeException("WebDAV upload failed: HTTP {$httpCode} {$err}");
        }

        return [
            'ok' => true,
            'object_key' => $cleanKey,
            'sha256' => $sha256,
            'size_bytes' => $sizeBytes,
        ];
    }

    public function getObject(string $objectKey): ?string
    {
        $cleanKey = ltrim($objectKey, '/');
        $url = $this->baseUrl . $this->remotePath . '/' . $cleanKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
        ]);

        $content = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 404) {
            return null;
        }

        if ($httpCode >= 200 && $httpCode < 300 && is_string($content)) {
            return $content;
        }

        return null;
    }

    public function deleteObject(string $objectKey): bool
    {
        $cleanKey = ltrim($objectKey, '/');
        $url = $this->baseUrl . $this->remotePath . '/' . $cleanKey;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
    }

    public function createSignedUrl(string $objectKey, int $expiresInSeconds = 300): ?string
    {
        // WebDAV does not provide standard client-side signed URLs; downloads route safely through FileService
        return null;
    }
}
