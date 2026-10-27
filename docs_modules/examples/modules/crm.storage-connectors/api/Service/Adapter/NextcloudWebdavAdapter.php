<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service\Adapter;

use Module\Crm\StorageConnectors\Service\SafeEndpoint;
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
        $response = '';
        $tooLarge = false;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            ...SafeEndpoint::curlOptions($url, $this->timeoutSeconds),
            CURLOPT_CUSTOMREQUEST => 'PROPFIND',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response, &$tooLarge): int {
                if (strlen($response) + strlen($chunk) > 65536) { $tooLarge = true; return 0; }
                $response .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_HTTPHEADER => ['Depth: 0'],
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || $tooLarge) {
            return ['ok' => false, 'message' => 'WebDAV response failed or exceeded the response limit.'];
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
        $cleanKey = $this->normalizeObjectKey($objectKey);
        $url = $this->baseUrl . $this->remotePath . '/' . $cleanKey;

        $sha256 = hash('sha256', $content);
        $sizeBytes = strlen($content);

        $this->ensureDirectories($cleanKey);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            ...SafeEndpoint::curlOptions($url, $this->timeoutSeconds),
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseTooLarge): int {
                $responseTooLarge = ($responseTooLarge ?? 0) + strlen($chunk);
                return $responseTooLarge <= 65536 ? strlen($chunk) : 0;
            },
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/octet-stream',
                'Content-Length: ' . $sizeBytes,
                'OC-Checksum: SHA256:' . $sha256,
            ],
        ]);

        $responseTooLarge = 0;
        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || ($httpCode < 200 || $httpCode >= 300)) {
            throw new RuntimeException('WebDAV upload failed.');
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
        $cleanKey = $this->normalizeObjectKey($objectKey);
        $url = $this->baseUrl . $this->remotePath . '/' . $cleanKey;
        $content = '';
        $tooLarge = false;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            ...SafeEndpoint::curlOptions($url, $this->timeoutSeconds),
            CURLOPT_HTTPGET => true,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$content, &$tooLarge): int {
                if (strlen($content) + strlen($chunk) > 20971520) {
                    $tooLarge = true;
                    return 0;
                }
                $content .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_USERPWD => "{$this->username}:{$this->password}",
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($tooLarge) {
            throw new RuntimeException('Remote file exceeds the 20 MB storage limit.');
        }

        if ($httpCode === 404) {
            return null;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return $content;
        }

        return null;
    }

    public function deleteObject(string $objectKey): bool
    {
        $cleanKey = $this->normalizeObjectKey($objectKey);
        $url = $this->baseUrl . $this->remotePath . '/' . $cleanKey;

        $ch = curl_init($url);
        $responseTooLarge = 0;
        curl_setopt_array($ch, [
            ...SafeEndpoint::curlOptions($url, $this->timeoutSeconds),
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseTooLarge): int {
                $responseTooLarge += strlen($chunk);
                return $responseTooLarge <= 65536 ? strlen($chunk) : 0;
            },
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

    private function normalizeObjectKey(string $objectKey): string
    {
        $value = trim(str_replace('\\', '/', $objectKey), '/');
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new RuntimeException('Remote object key is invalid.');
        }
        foreach (explode('/', rawurldecode($value)) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('Remote object key traversal is not allowed.');
            }
        }
        return implode('/', array_map('rawurlencode', explode('/', $value)));
    }

    private function ensureDirectories(string $encodedObjectKey): void
    {
        $segments = explode('/', $encodedObjectKey);
        array_pop($segments); // Last segment is the file name.
        $path = $this->remotePath;
        foreach ($segments as $segment) {
            $path .= '/' . $segment;
            $url = $this->baseUrl . $path;
            $responseBytes = 0;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                ...SafeEndpoint::curlOptions($url, $this->timeoutSeconds),
                CURLOPT_CUSTOMREQUEST => 'MKCOL',
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$responseBytes): int { $responseBytes += strlen($chunk); return $responseBytes <= 65536 ? strlen($chunk) : 0; },
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
                CURLOPT_USERPWD => "{$this->username}:{$this->password}",
            ]);
            curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_errno($ch);
            curl_close($ch);
            // 405 means this collection already exists. Other failures (including
            // conflict because the configured base directory is missing) fail closed.
            if ($error !== 0 || !in_array($status, [200, 201, 204, 405], true)) throw new RuntimeException('WebDAV target directory could not be prepared.');
        }
    }
}
