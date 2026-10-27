<?php
declare(strict_types=1);

namespace Module\Crm\YandexDisk\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use PDO;
use RuntimeException;

/** Bounded Yandex Disk REST client with pinned, validated upload URLs. */
final class YandexDiskService
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;
    private const KEY_AAD = 'crm.yandex-disk.credentials.v1';

    public function __construct(private readonly PDO $db, private readonly int $workspaceId)
    {
        if ($workspaceId <= 0) throw new RuntimeException('An authorized workspace is required.');
    }

    public function getStatus(): array
    {
        $row = $this->settingsRow();
        return [
            'configured' => is_array($row) && trim((string)($row['oauth_token_encrypted'] ?? '')) !== '',
            'connected' => is_array($row) && !empty($row['account_uid']),
            'account_uid' => is_array($row) ? (string)($row['account_uid'] ?? '') : '',
            'root_folder' => is_array($row) ? (string)($row['root_folder'] ?? '/TropaTT_CRM') : '/TropaTT_CRM',
            'used_space' => is_array($row) && $row['used_space'] !== null ? (int)$row['used_space'] : null,
            'total_space' => is_array($row) && $row['total_space'] !== null ? (int)$row['total_space'] : null,
            'last_checked_at' => is_array($row) ? ($row['updated_at'] ?? null) : null,
        ];
    }

    public function saveToken(string $token, string $rootFolder = '/TropaTT_CRM'): void
    {
        $token = trim($token);
        if ($token === '' || strlen($token) > 8192 || preg_match('/[\x00-\x20\x7F]/', $token)) throw new RuntimeException('A valid Yandex Disk OAuth token is required.');
        $rootFolder = $this->validateRemotePath($rootFolder);
        $encrypted = $this->encrypt($token);
        $row = $this->settingsRow();
        if ($row) {
            $stmt = $this->db->prepare('UPDATE module_yandex_disk_settings SET oauth_token_encrypted=:token, root_folder=:root, account_uid=NULL, used_space=NULL, total_space=NULL, is_active=1, updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        } else {
            $stmt = $this->db->prepare('INSERT INTO module_yandex_disk_settings (workspace_id, oauth_token_encrypted, root_folder, auto_upload_task_files, auto_upload_backups, use_txt_proxy_bypass, is_active, created_at, updated_at) VALUES (:workspace_id,:token,:root,0,0,0,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        }
        $stmt->execute([':token' => $encrypted, ':root' => $rootFolder, ':workspace_id' => $this->workspaceId]);
    }

    public function testConnection(): array
    {
        $token = $this->token();
        $disk = $this->requestJson('https://cloud-api.yandex.net/v1/disk', $token, 'GET');
        if (!isset($disk['user']['uid']) || !is_scalar($disk['user']['uid'])) throw new RuntimeException('Yandex Disk account was not confirmed.');
        $uid = trim((string)$disk['user']['uid']);
        $used = isset($disk['used_space']) ? max(0, (int)$disk['used_space']) : null;
        $total = isset($disk['total_space']) ? max(0, (int)$disk['total_space']) : null;
        $stmt = $this->db->prepare('UPDATE module_yandex_disk_settings SET account_uid=:uid, used_space=:used, total_space=:total, updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        $stmt->execute([':uid' => substr($uid, 0, 64), ':used' => $used, ':total' => $total, ':workspace_id' => $this->workspaceId]);
        return ['ok' => true, 'account_uid' => $uid, 'used_space' => $used, 'total_space' => $total];
    }

    /** Upload a CRM-file already authorized by core FileService. */
    public function uploadFile(array $file, string $bytes): array
    {
        $size = strlen($bytes);
        if ($size > self::MAX_FILE_BYTES || (int)($file['size_bytes'] ?? -1) !== $size || !empty($file['is_quarantined'])) throw new RuntimeException('The file is unsafe or exceeds the 20 MB limit.');
        $publicId = trim((string)($file['public_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $publicId)) throw new RuntimeException('Invalid CRM file identifier.');
        $row = $this->settingsRow();
        if (!is_array($row) || (int)($row['is_active'] ?? 0) !== 1) throw new RuntimeException('Yandex Disk is not configured for this workspace.');
        $root = $this->validateRemotePath((string)$row['root_folder']);
        $entity = preg_replace('/[^a-z0-9_-]/i', '', (string)($file['entity_type'] ?? '')) ?: 'Files';
        $entityId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($file['entity_public_id'] ?? ''));
        $name = $this->safeFilename((string)($file['original_name'] ?? 'file.bin'));
        $folder = $root . '/' . $entity . ($entityId !== '' ? '/' . $entityId : '');
        $contentHash = hash('sha256', $bytes);
        // Immutable, content-addressed paths prevent retries or stale CRM metadata
        // from replacing an unrelated remote object with the same file identifier.
        $path = $folder . '/' . $publicId . '-' . $contentHash . '-' . $name;
        $token = $this->token();
        foreach ([$root, $folder] as $directory) $this->createFolder($token, $directory);
        $uploadMeta = $this->requestJson('https://cloud-api.yandex.net/v1/disk/resources/upload?' . http_build_query(['path' => $path, 'overwrite' => 'false'], '', '&', PHP_QUERY_RFC3986), $token, 'GET', null, [409]);
        if (!empty($uploadMeta['conflict'])) {
            // Safe recovery for a retry after a lost upload response: adopt the
            // existing object only when provider metadata proves it is identical.
            $existing = $this->requestJson('https://cloud-api.yandex.net/v1/disk/resources?' . http_build_query(['path' => $path], '', '&', PHP_QUERY_RFC3986), $token, 'GET');
            if (!self::remoteFileMatches($existing, $path, $size, $contentHash)) {
                throw new RuntimeException('An object already exists at the immutable Yandex Disk path but its content could not be verified.');
            }
        } else {
            $uploadUrl = $this->validateUploadUrl((string)($uploadMeta['href'] ?? ''));
            $this->uploadToProviderUrl($uploadUrl, $bytes);
        }
        $verify = $this->requestJson('https://cloud-api.yandex.net/v1/disk/resources?' . http_build_query(['path' => $path], '', '&', PHP_QUERY_RFC3986), $token, 'GET');
        if (!self::remoteFileMatches($verify, $path, $size, $contentHash)) throw new RuntimeException('Yandex Disk did not confirm the uploaded file contents.');
        $publicUrl = null; // Keep files private; public publishing is not part of this flow.
        $stmt = $this->db->prepare('INSERT INTO module_yandex_disk_files (public_id, workspace_id, crm_file_public_id, original_name, original_extension, yandex_path, file_size_bytes, public_url, used_bypass, status, created_at, updated_at) VALUES (:pub,:org,:file,:name,:ext,:path,:size,NULL,0,\'uploaded\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP) ON DUPLICATE KEY UPDATE original_name=VALUES(original_name), original_extension=VALUES(original_extension), yandex_path=VALUES(yandex_path), file_size_bytes=VALUES(file_size_bytes), public_url=NULL, used_bypass=0, status=\'uploaded\', updated_at=CURRENT_TIMESTAMP');
        $stmt->execute([':pub' => 'ydf_' . bin2hex(random_bytes(12)), ':org' => $this->workspaceId, ':file' => $publicId, ':name' => substr($name, 0, 255), ':ext' => strtolower(substr((string)pathinfo($name, PATHINFO_EXTENSION), 0, 32)), ':path' => substr($path, 0, 500), ':size' => $size]);
        return ['ok' => true, 'path' => $path, 'size_bytes' => $size, 'public_url' => $publicUrl];
    }

    /** @param array<string,mixed> $metadata */
    public static function remoteFileMatches(array $metadata, string $path, int $size, string $sha256): bool
    {
        $metadataPath = (string)($metadata['path'] ?? '');
        if (str_starts_with($metadataPath, 'disk:/')) {
            $metadataPath = substr($metadataPath, 5);
        }
        return ($metadata['type'] ?? null) === 'file'
            && $metadataPath === $path
            && (int)($metadata['size'] ?? -1) === $size
            && is_string($metadata['sha256'] ?? null)
            && hash_equals(strtolower($sha256), strtolower((string)$metadata['sha256']));
    }

    public function validateRemotePath(string $path): string
    {
        if (preg_match('/[\x00-\x1F\x7F]/', $path)) throw new RuntimeException('Invalid remote path.');
        $path = trim($path);
        if ($path === '' || strlen($path) > 400 || $path[0] !== '/' || str_contains($path, '\\')) throw new RuntimeException('Invalid remote path.');
        foreach (explode('/', rawurldecode($path)) as $segment) if ($segment === '.' || $segment === '..') throw new RuntimeException('Remote path traversal is not allowed.');
        return '/' . implode('/', array_filter(explode('/', $path), static fn(string $part): bool => $part !== ''));
    }

    private function createFolder(string $token, string $path): void
    {
        $url = 'https://cloud-api.yandex.net/v1/disk/resources?' . http_build_query(['path' => $path], '', '&', PHP_QUERY_RFC3986);
        $this->requestJson($url, $token, 'PUT', null, [201, 409]);
        // On 409, confirm the existing path is a directory owned by this account.
        $meta = $this->requestJson($url, $token, 'GET');
        if ((string)($meta['path'] ?? '') !== $path || !in_array((string)($meta['type'] ?? ''), ['dir'], true)) throw new RuntimeException('Yandex Disk folder was not confirmed.');
    }

    private function requestJson(string $url, string $token, string $method, ?string $body = null, array $acceptedStatuses = []): array
    {
        if (!function_exists('curl_init') || !extension_loaded('openssl')) throw new RuntimeException('Secure HTTPS support is not available.');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || strtolower((string)($parts['host'] ?? '')) !== 'cloud-api.yandex.net' || (int)($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('Yandex API URL is invalid.');
        $validation = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https']);
        if (empty($validation['ok']) || empty($validation['resolved_ips'])) throw new RuntimeException('Yandex API endpoint failed SSRF validation.');
        $ip = trim((string)$validation['resolved_ips'][0], '[]');
        $resolveAddress = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $ip . ']' : $ip;
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Yandex Disk request could not be initialized.');
        $headers = ['Authorization: OAuth ' . $token, 'Accept: application/json'];
        $raw = '';
        $options = [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => false, CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
            if (strlen($raw) + strlen($chunk) > 262144) return 0;
            $raw .= $chunk;
            return strlen($chunk);
        }, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => ['cloud-api.yandex.net:443:' . $resolveAddress], CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*'];
        if ($body !== null) { $options[CURLOPT_POSTFIELDS] = $body; $headers[] = 'Content-Type: application/octet-stream'; $options[CURLOPT_HTTPHEADER] = $headers; }
        curl_setopt_array($ch, $options);
        $executed = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($executed !== true || (($status < 200 || $status >= 300) && !in_array($status, $acceptedStatuses, true))) throw new RuntimeException('Yandex Disk API request failed.');
        $decoded = json_decode($raw, true);
        if ($status === 409 && in_array($status, $acceptedStatuses, true)) return ['conflict' => true];
        return is_array($decoded) ? $decoded : [];
    }

    private function validateUploadUrl(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass'])
            || !($host === 'disk.yandex.net' || str_ends_with($host, '.disk.yandex.net'))) throw new RuntimeException('Yandex Disk returned an invalid upload endpoint.');
        $check = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https']);
        if (empty($check['ok']) || empty($check['resolved_ips'])) throw new RuntimeException('Yandex upload endpoint failed SSRF validation.');
        return ['url' => $url, 'host' => $host, 'ips' => $check['resolved_ips']];
    }

    private function uploadToProviderUrl(array $endpoint, string $bytes): void
    {
        $url = (string)$endpoint['url']; $host = (string)$endpoint['host'];
        $addresses = array_map(static fn(string $ip): string => str_contains($ip, ':') ? '[' . $ip . ']' : $ip, (array)$endpoint['ips']);
        $resolve = [$host . ':443:' . implode(',', $addresses)];
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Yandex upload request could not be initialized.');
        $response = '';
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => 'PUT', CURLOPT_POSTFIELDS => $bytes, CURLOPT_RETURNTRANSFER => false, CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response): int {
            if (strlen($response) + strlen($chunk) > 65536) return 0;
            $response .= $chunk;
            return strlen($chunk);
        }, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 35, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => $resolve, CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*', CURLOPT_HTTPHEADER => ['Content-Type: application/octet-stream', 'Content-Length: ' . strlen($bytes)] ]);
        $executed = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($executed !== true || $status < 200 || $status >= 300) throw new RuntimeException('Yandex Disk upload did not complete.');
    }

    private function token(): string
    {
        $row = $this->settingsRow();
        if (!is_array($row) || (int)($row['is_active'] ?? 0) !== 1) throw new RuntimeException('Yandex Disk is not configured for this workspace.');
        return $this->decrypt((string)$row['oauth_token_encrypted']);
    }

    private function settingsRow(): ?array
    {
        $stmt = $this->db->prepare('SELECT oauth_token_encrypted,root_folder,account_uid,used_space,total_space,is_active,updated_at FROM module_yandex_disk_settings WHERE workspace_id=:workspace_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function encrypt(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to protect the Yandex token.');
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, $iv, $tag, self::KEY_AAD, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('Yandex token encryption failed.');
        return 'gcm:' . base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'gcm:') || !function_exists('openssl_decrypt')) throw new RuntimeException('Yandex token needs secure re-entry.');
        $raw = base64_decode(substr($encrypted, 4), true);
        if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('Yandex token is unreadable.');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), self::KEY_AAD);
        if (!is_string($plain)) throw new RuntimeException('Yandex token is unreadable. Re-enter the provider token.');
        return $plain;
    }

    private function cryptoKey(): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if (strlen($key) < 32) throw new RuntimeException('APP_KEY must contain at least 32 characters to protect provider credentials.');
        return hash('sha256', $key, true);
    }

    private function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name)); $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '');
        if ($name === '' || $name === '.' || $name === '..') return 'file.bin';
        return function_exists('mb_substr') ? mb_substr($name, 0, 180, 'UTF-8') : substr($name, 0, 180);
    }
}
