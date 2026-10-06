<?php
declare(strict_types=1);

namespace Module\Crm\StorageConnectors\Service\Adapter;

use RuntimeException;

/**
 * Lightweight S3-compatible backend adapter using standard AWS SigV4 authentication over native cURL.
 * No heavy external SDK required; keeps shared hosting memory usage < 32 MB.
 */
final class S3CompatibleStorageAdapter implements StorageAdapterInterface
{
    private string $endpointUrl;
    private string $bucket;
    private string $region;
    private string $accessKey;
    private string $secretKey;
    private int $timeoutSeconds;

    public function __construct(
        string $endpointUrl,
        string $bucket,
        string $accessKey,
        string $secretKey,
        string $region = 'us-east-1',
        int $timeoutSeconds = 20
    ) {
        $this->endpointUrl = rtrim($endpointUrl, '/');
        $this->bucket = trim($bucket, '/');
        $this->accessKey = $accessKey;
        $this->secretKey = $secretKey;
        $this->region = $region !== '' ? $region : 'us-east-1';
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function testConnection(): array
    {
        $uri = '/' . $this->bucket;
        $url = $this->endpointUrl . $uri;

        $headers = $this->signRequest('GET', $uri, '', ['max-keys' => '1']);
        $ch = curl_init($url . '?max-keys=1');
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $res = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '') {
            return ['ok' => false, 'message' => "S3 network error: {$err}"];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['ok' => true, 'message' => 'S3 bucket connection verified successfully.'];
        }

        if ($httpCode === 403) {
            return ['ok' => false, 'message' => 'S3 access denied. Verify access_key and secret_key permissions.'];
        }

        return ['ok' => false, 'message' => "S3 returned HTTP {$httpCode}: " . substr((string)$res, 0, 150)];
    }

    public function putObject(string $objectKey, string $content): array
    {
        $cleanKey = ltrim($objectKey, '/');
        $uri = '/' . $this->bucket . '/' . $cleanKey;
        $url = $this->endpointUrl . $uri;

        $sha256 = hash('sha256', $content);
        $sizeBytes = strlen($content);

        $headers = $this->signRequest('PUT', $uri, $content, [], [
            'content-type' => 'application/octet-stream',
            'content-length' => (string)$sizeBytes,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        $res = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($err !== '' || ($httpCode < 200 || $httpCode >= 300)) {
            throw new RuntimeException("S3 upload failed: HTTP {$httpCode} {$err}");
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
        $uri = '/' . $this->bucket . '/' . $cleanKey;
        $url = $this->endpointUrl . $uri;

        $headers = $this->signRequest('GET', $uri, '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
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
        $uri = '/' . $this->bucket . '/' . $cleanKey;
        $url = $this->endpointUrl . $uri;

        $headers = $this->signRequest('DELETE', $uri, '');
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_HTTPHEADER => $headers,
        ]);

        curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300) || $httpCode === 204;
    }

    public function createSignedUrl(string $objectKey, int $expiresInSeconds = 300): ?string
    {
        $cleanKey = ltrim($objectKey, '/');
        $uri = '/' . $this->bucket . '/' . $cleanKey;

        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $service = 's3';

        $parsed = parse_url($this->endpointUrl);
        $host = (string)($parsed['host'] ?? 'localhost');
        if (isset($parsed['port'])) {
            $host .= ':' . $parsed['port'];
        }

        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $queryParams = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => "{$this->accessKey}/{$credentialScope}",
            'X-Amz-Date' => $amzDate,
            'X-Amz-Expires' => (string)$expiresInSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($queryParams);

        $canonicalQuery = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $canonicalHeaders = "host:{$host}\n";
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "GET\n{$uri}\n{$canonicalQuery}\n{$canonicalHeaders}\nhost\n{$payloadHash}";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return $this->endpointUrl . $uri . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    /**
     * @param array<string, string> $queryParams
     * @param array<string, string> $extraHeaders
     * @return array<int, string>
     */
    private function signRequest(string $method, string $uri, string $payload, array $queryParams = [], array $extraHeaders = []): array
    {
        $now = time();
        $amzDate = gmdate('Ymd\THis\Z', $now);
        $dateStamp = gmdate('Ymd', $now);
        $service = 's3';

        $parsed = parse_url($this->endpointUrl);
        $host = (string)($parsed['host'] ?? 'localhost');
        if (isset($parsed['port'])) {
            $host .= ':' . $parsed['port'];
        }

        $payloadHash = hash('sha256', $payload);

        $headersToSign = array_merge([
            'host' => $host,
            'x-amz-date' => $amzDate,
            'x-amz-content-sha256' => $payloadHash,
        ], $extraHeaders);
        ksort($headersToSign);

        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headersToSign as $k => $v) {
            $lk = strtolower(trim((string)$k));
            $canonicalHeaders .= "{$lk}:" . trim((string)$v) . "\n";
            $signedHeadersList[] = $lk;
        }
        $signedHeaders = implode(';', $signedHeadersList);

        ksort($queryParams);
        $canonicalQuery = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $canonicalRequest = "{$method}\n{$uri}\n{$canonicalQuery}\n{$canonicalHeaders}\n{$signedHeaders}\n{$payloadHash}";
        $credentialScope = "{$dateStamp}/{$this->region}/{$service}/aws4_request";
        $stringToSign = "AWS4-HMAC-SHA256\n{$amzDate}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $this->secretKey, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorization = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$credentialScope}, SignedHeaders={$signedHeaders}, Signature={$signature}";

        $outHeaders = ["Authorization: {$authorization}"];
        foreach ($headersToSign as $k => $v) {
            if (strtolower($k) !== 'host') {
                $outHeaders[] = "{$k}: {$v}";
            }
        }

        return $outHeaders;
    }
}
