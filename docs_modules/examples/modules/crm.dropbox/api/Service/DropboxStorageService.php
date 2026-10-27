<?php
declare(strict_types=1);

namespace Module\Crm\Dropbox\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use PDO;
use RuntimeException;
use Closure;

/** Provider HTTP status/error needed to reconcile safe Dropbox conflict retries. */
final class DropboxApiException extends RuntimeException
{
    public function __construct(public readonly int $status, public readonly string $errorSummary = '')
    {
        parent::__construct('Dropbox API request failed with HTTP ' . $status . '.');
    }
}

/** Minimal, bounded Dropbox API v2 integration. All provider URLs are fixed. */
final class DropboxStorageService
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;
    private const KEY_AAD = 'crm.dropbox.credentials.v1';
    private ?Closure $httpTransport;

    public function __construct(private readonly PDO $db, private readonly int $workspaceId, ?callable $httpTransport = null)
    {
        if ($workspaceId <= 0) {
            throw new RuntimeException('An authorized workspace is required.');
        }
        $this->httpTransport = $httpTransport !== null ? Closure::fromCallable($httpTransport) : null;
    }

    /** @return array<string,mixed> */
    public function getStatus(): array
    {
        $row = $this->settingsRow();
        return [
            'connected' => is_array($row) && trim((string)($row['account_id'] ?? '')) !== '',
            'configured' => is_array($row) && trim((string)$row['refresh_token_encrypted']) !== '',
            'account_email' => is_array($row) ? (string)($row['account_email'] ?? '') : '',
            'base_folder_path' => is_array($row) ? (string)($row['base_folder_path'] ?? '/TropaTT_CRM') : '/TropaTT_CRM',
            'last_checked_at' => is_array($row) ? ($row['updated_at'] ?? null) : null,
            'features' => ['account_check' => true, 'file_upload' => true, 'shared_links' => false],
        ];
    }

    /**
     * @param array{app_key:string,app_secret:string,refresh_token:string,base_folder_path?:string} $input
     */
    public function saveCredentials(array $input): void
    {
        foreach (['app_key', 'app_secret', 'refresh_token'] as $key) {
            if (trim((string)($input[$key] ?? '')) === '' || strlen((string)$input[$key]) > 4096) {
                throw new RuntimeException('Dropbox application credentials are required.');
            }
        }
        $base = $this->normalizeBasePath((string)($input['base_folder_path'] ?? '/TropaTT_CRM'));
        $row = $this->settingsRow();
        $values = [
            ':app_key' => trim((string)$input['app_key']),
            ':app_secret' => $this->encrypt(trim((string)$input['app_secret'])),
            ':refresh_token' => $this->encrypt(trim((string)$input['refresh_token'])),
            ':base_path' => $base,
            ':workspace_id' => $this->workspaceId,
        ];
        if ($row) {
            $stmt = $this->db->prepare('UPDATE module_dropbox_settings SET app_key=:app_key, app_secret_encrypted=:app_secret, refresh_token_encrypted=:refresh_token, base_folder_path=:base_path, access_token_encrypted=NULL, token_expires_at=NULL, account_id=NULL, account_email=NULL, is_active=1, updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        } else {
            $stmt = $this->db->prepare('INSERT INTO module_dropbox_settings (workspace_id, app_key, app_secret_encrypted, refresh_token_encrypted, base_folder_path, is_active, created_at, updated_at) VALUES (:workspace_id, :app_key, :app_secret, :refresh_token, :base_path, 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)');
        }
        $stmt->execute($values);
    }

    /** Exchange refresh token, then verify identity with the provider. */
    public function testConnection(): array
    {
        $token = $this->accessToken(true);
        $account = $this->requestJson('https://api.dropboxapi.com/2/users/get_current_account', $token, 'POST', '{}');
        // Dropbox returns account_id as an opaque string; do not infer an
        // authenticated state from a successful HTTP status without it.
        if (!is_array($account) || trim((string)($account['account_id'] ?? '')) === '') throw new RuntimeException('Dropbox account check failed.');
        $email = trim((string)($account['email'] ?? ''));
        $stmt = $this->db->prepare('UPDATE module_dropbox_settings SET account_id=:account_id, account_email=:email, updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        $stmt->execute([':account_id' => substr((string)$account['account_id'], 0, 128), ':email' => $email !== '' ? substr($email, 0, 255) : null, ':workspace_id' => $this->workspaceId]);
        return ['ok' => true, 'account_email' => $email, 'message' => 'Подключение Dropbox проверено.'];
    }

    /** Upload only a file already authorized by core FileService. */
    public function uploadFile(array $file, string $bytes): array
    {
        if (strlen($bytes) > self::MAX_FILE_BYTES || (int)($file['size_bytes'] ?? -1) !== strlen($bytes)) {
            throw new RuntimeException('File size is invalid or exceeds 20 MB.');
        }
        $publicId = trim((string)($file['public_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $publicId)) {
            throw new RuntimeException('Invalid CRM file identifier.');
        }
        if (!empty($file['is_quarantined'])) {
            throw new RuntimeException('Quarantined files cannot be sent to external storage.');
        }
        $name = $this->safeFilename((string)($file['original_name'] ?? 'file.bin'));
        $base = $this->normalizeBasePath((string)(($this->settingsRow()['base_folder_path'] ?? '') ?: '/TropaTT_CRM'));
        $entityType = preg_replace('/[^a-z0-9_-]/i', '', (string)($file['entity_type'] ?? '')) ?: 'Files';
        $entityId = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($file['entity_public_id'] ?? ''));
        $folder = $base . '/' . $entityType . ($entityId !== '' ? '/' . $entityId : '');
        $path = $folder . '/' . $publicId . '-' . $name;
        $expectedHash = $this->dropboxContentHash($bytes);
        $token = $this->accessToken(true);
        $this->createFolder($token, $folder);
        $alreadyUploaded = false;
        $mapping = $this->db->prepare('SELECT id, dropbox_file_id, dropbox_path_display, content_hash, file_size_bytes FROM module_dropbox_files WHERE workspace_id=:workspace_id AND crm_file_public_id=:file_id LIMIT 1');
        $mapping->execute([':workspace_id' => $this->workspaceId, ':file_id' => $publicId]);
        $existingMapping = $mapping->fetch(PDO::FETCH_ASSOC);
        $writeMode = 'add';
        if (is_array($existingMapping)) {
            $mappedId = trim((string)($existingMapping['dropbox_file_id'] ?? ''));
            $mappedPath = trim((string)($existingMapping['dropbox_path_display'] ?? ''));
            if ($mappedId === '' || $mappedPath === '' || !hash_equals(strtolower($mappedPath), strtolower($path))) {
                throw new RuntimeException('The mapped Dropbox object does not match this workspace/file destination.');
            }
            $metadata = $this->getFileMetadata($token, $mappedId);
            if ($metadata === null || !$this->isMappedDropboxFile($metadata, $mappedId, $mappedPath)) {
                throw new RuntimeException('The mapped Dropbox object changed or is unavailable; upload was stopped to prevent overwriting another file.');
            }
            $writeMode = ['update' => (string)$metadata['rev']];
        }

        $result = null;
        if (!is_array($existingMapping)) {
            // Recover a prior successful provider upload whose local mapping insert
            // failed. Exact path, file identity shape, size and provider content hash
            // are required; folders, mismatches and ambiguous errors fail closed.
            $result = $this->getFileMetadata($token, $path);
            if ($result !== null && !$this->matchesExpectedDropboxFile($result, $path, strlen($bytes), $expectedHash)) {
                throw new RuntimeException('An object already occupies the CRM Dropbox path but its identity or checksum does not match.');
            }
            $alreadyUploaded = is_array($result);
        }

        if ($result === null) {
            $arg = json_encode(['path' => $path, 'mode' => $writeMode, 'autorename' => false, 'mute' => true, 'strict_conflict' => true], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            try {
                $result = $this->requestJson('https://content.dropboxapi.com/2/files/upload', $token, 'POST', $bytes, ['Dropbox-API-Arg: ' . $arg, 'Content-Type: application/octet-stream']);
            } catch (DropboxApiException $uploadError) {
                // Conflict or a lost HTTP response may follow a successful PUT.
                // Reconcile by deterministic destination and exact Dropbox hash;
                // never overwrite or adopt a mismatching object.
                $reconciled = $this->getFileMetadata($token, $path);
                if ($reconciled === null || !$this->matchesExpectedDropboxFile($reconciled, $path, strlen($bytes), $expectedHash)) {
                    throw $uploadError;
                }
                $result = $reconciled;
                $alreadyUploaded = true;
            } catch (\Throwable $uploadError) {
                // Network failure can occur after Dropbox committed the object.
                try {
                    $reconciled = $this->getFileMetadata($token, $path);
                    if ($reconciled !== null && $this->matchesExpectedDropboxFile($reconciled, $path, strlen($bytes), $expectedHash)) { $result = $reconciled; $alreadyUploaded = true; }
                    else throw $uploadError;
                } catch (DropboxApiException $lookupError) {
                    if ($lookupError->status !== 409 || !str_starts_with($lookupError->errorSummary, 'path/not_found')) throw $lookupError;
                    throw $uploadError;
                }
            }
        }

        $remoteId = trim((string)($result['id'] ?? ''));
        $remotePath = trim((string)($result['path_display'] ?? ''));
        $providerHash = strtolower(trim((string)($result['content_hash'] ?? '')));
        if (!$this->matchesExpectedDropboxFile($result, $path, strlen($bytes), $expectedHash)) {
            throw new RuntimeException('Dropbox did not confirm the uploaded file identity and checksum.');
        }
        $mappingId = is_array($existingMapping) ? (int)($existingMapping['id'] ?? 0) : 0;
        $values = [':workspace_id' => $this->workspaceId, ':file_id' => $publicId, ':remote_id' => substr($remoteId, 0, 128), ':path_lower' => substr((string)($result['path_lower'] ?? strtolower($remotePath)), 0, 500), ':path_display' => substr($remotePath, 0, 500), ':size' => strlen($bytes), ':hash' => $providerHash];
        try {
            if ($mappingId > 0) {
                $stmt = $this->db->prepare('UPDATE module_dropbox_files SET dropbox_file_id=:remote_id,dropbox_path_lower=:path_lower,dropbox_path_display=:path_display,file_size_bytes=:size,content_hash=:hash,status=\'synced\',updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:file_id');
            } else {
                $stmt = $this->db->prepare('INSERT INTO module_dropbox_files (public_id, workspace_id, crm_file_public_id, crm_project_public_id, dropbox_file_id, dropbox_path_lower, dropbox_path_display, file_size_bytes, content_hash, status, created_at, updated_at) VALUES (:public_id,:workspace_id,:file_id,NULL,:remote_id,:path_lower,:path_display,:size,:hash,\'synced\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
                $values[':public_id'] = 'dbf_' . bin2hex(random_bytes(12));
            }
            $stmt->execute($values);
        } catch (\PDOException $mappingError) {
            // A parallel retry may have committed the same mapping first.
            $winner = $this->db->prepare('SELECT dropbox_file_id,dropbox_path_display,file_size_bytes,content_hash,status FROM module_dropbox_files WHERE workspace_id=:workspace_id AND crm_file_public_id=:file_id LIMIT 1');
            $winner->execute([':workspace_id' => $this->workspaceId, ':file_id' => $publicId]);
            $saved = $winner->fetch(PDO::FETCH_ASSOC);
            if (!is_array($saved) || (string)$saved['status'] !== 'synced'
                || !hash_equals((string)$saved['dropbox_file_id'], $remoteId)
                || !hash_equals(strtolower((string)$saved['dropbox_path_display']), strtolower($remotePath))
                || (int)$saved['file_size_bytes'] !== strlen($bytes)
                || !hash_equals(strtolower((string)$saved['content_hash']), $expectedHash)) throw $mappingError;
        }
        return ['ok' => true, 'remote_id' => $remoteId, 'path' => $remotePath, 'size_bytes' => strlen($bytes), 'already_uploaded' => $alreadyUploaded];
    }

    /** @return array<string,mixed>|null */
    private function getFileMetadata(string $token, string $pathOrId): ?array
    {
        try {
            return $this->requestJson('https://api.dropboxapi.com/2/files/get_metadata', $token, 'POST', json_encode(['path' => $pathOrId], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (DropboxApiException $exception) {
            if ($exception->status === 409 && str_starts_with($exception->errorSummary, 'path/not_found')) return null;
            throw $exception;
        }
    }

    /** @param array<string,mixed> $metadata */
    private function isMappedDropboxFile(array $metadata, string $id, string $path): bool
    {
        return strtolower((string)($metadata['.tag'] ?? '')) === 'file'
            && trim((string)($metadata['id'] ?? '')) !== '' && hash_equals($id, (string)$metadata['id'])
            && trim((string)($metadata['rev'] ?? '')) !== ''
            && hash_equals(strtolower($path), strtolower((string)($metadata['path_display'] ?? '')));
    }

    /** @param array<string,mixed> $metadata */
    private function matchesExpectedDropboxFile(array $metadata, string $path, int $size, string $expectedHash): bool
    {
        $remoteHash = strtolower(trim((string)($metadata['content_hash'] ?? '')));
        return strtolower((string)($metadata['.tag'] ?? '')) === 'file'
            && trim((string)($metadata['id'] ?? '')) !== '' && trim((string)($metadata['rev'] ?? '')) !== ''
            && hash_equals(strtolower($path), strtolower((string)($metadata['path_display'] ?? '')))
            && (int)($metadata['size'] ?? -1) === $size
            && preg_match('/^[a-f0-9]{64}$/', $remoteHash) === 1
            && hash_equals($expectedHash, $remoteHash);
    }

    /** Dropbox hashes each 4 MiB block, concatenates the binary digests, then hashes again. */
    private function dropboxContentHash(string $bytes): string
    {
        $blockHashes = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 4 * 1024 * 1024) {
            $blockHashes .= hash('sha256', substr($bytes, $offset, 4 * 1024 * 1024), true);
        }
        return hash('sha256', $blockHashes);
    }

    private function createFolder(string $token, string $path): void
    {
        try {
            $this->requestJson('https://api.dropboxapi.com/2/files/create_folder_v2', $token, 'POST', json_encode(['path' => $path, 'autorename' => false], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } catch (RuntimeException $exception) {
            // A retry may encounter the folder created by the previous run.
            // Accept only an exact folder metadata match; do not treat arbitrary
            // provider errors (including auth failures) as successful.
            try {
                $metadata = $this->requestJson('https://api.dropboxapi.com/2/files/get_metadata', $token, 'POST', json_encode(['path' => $path], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
                if (strtolower((string)($metadata['.tag'] ?? '')) === 'folder'
                    && hash_equals(strtolower($path), strtolower((string)($metadata['path_display'] ?? '')))) {
                    return;
                }
            } catch (RuntimeException) {
                // Preserve the original create error below.
            }
            throw $exception;
        }
    }

    /** @return array<string,mixed> */
    private function requestJson(string $url, string $token, string $method, string $body, array $extraHeaders = []): array
    {
        if ($this->httpTransport !== null) {
            $headers = ['Authorization: Bearer ' . $token, 'Content-Type: application/json'];
            foreach ($extraHeaders as $header) $headers[] = $header;
            $response = ($this->httpTransport)($url, $method, $body, $headers, 262144);
            if (!is_array($response) || !is_string($response['body'] ?? null) || strlen($response['body']) > 262144) throw new RuntimeException('Dropbox API response was invalid.');
            if ((int)($response['status'] ?? 0) < 200 || (int)$response['status'] >= 300) { $error = json_decode($response['body'], true); throw new DropboxApiException((int)($response['status'] ?? 0), (string)($error['error_summary'] ?? '')); }
            $decoded = json_decode($response['body'], true);
            return is_array($decoded) ? $decoded : [];
        }
        if (!function_exists('curl_init') || !extension_loaded('openssl')) {
            throw new RuntimeException('Secure HTTPS support is not available on this PHP host.');
        }
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Dropbox request could not be initialized.');
        $headers = ['Authorization: Bearer ' . $token];
        $hasContentType = false;
        foreach ($extraHeaders as $header) {
            if (str_starts_with(strtolower($header), 'content-type:')) $hasContentType = true;
        }
        if (!$hasContentType) $headers[] = 'Content-Type: application/json';
        $headers = array_merge($headers, $extraHeaders);
        $response = '';
        $tooLarge = false;
        curl_setopt_array($ch, [CURLOPT_POST => $method === 'POST', CURLOPT_CUSTOMREQUEST => $method, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => false, CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$response, &$tooLarge): int { if (strlen($response) + strlen($chunk) > 262144) { $tooLarge = true; return 0; } $response .= $chunk; return strlen($chunk); }, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 35, ...$this->providerCurlOptions($url)]);
        $executed = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($executed !== true || $tooLarge) {
            throw new RuntimeException('Dropbox API request failed.');
        }
        if ($status < 200 || $status >= 300) {
            $errorData = json_decode($response, true);
            throw new DropboxApiException($status, (string)($errorData['error_summary'] ?? ''));
        }
        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function accessToken(bool $forceRefresh): string
    {
        $row = $this->settingsRow();
        if (!is_array($row) || (int)($row['is_active'] ?? 0) !== 1) throw new RuntimeException('Dropbox is not configured for this workspace.');
        $expiry = strtotime((string)($row['token_expires_at'] ?? ''));
        if (!$forceRefresh && $expiry !== false && $expiry > time() + 60 && trim((string)($row['access_token_encrypted'] ?? '')) !== '') return $this->decrypt((string)$row['access_token_encrypted']);
        $key = trim((string)($row['app_key'] ?? ''));
        $secret = $this->decrypt((string)$row['app_secret_encrypted']);
        $refresh = $this->decrypt((string)$row['refresh_token_encrypted']);
        if ($this->httpTransport !== null) {
            $response = ($this->httpTransport)('https://api.dropboxapi.com/oauth2/token', 'POST', http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]), ['Authorization: Basic ' . base64_encode($key . ':' . $secret), 'Content-Type: application/x-www-form-urlencoded'], 65536);
            $data = is_array($response) && is_string($response['body'] ?? null) && strlen($response['body']) <= 65536 ? json_decode($response['body'], true) : null;
            if (!is_array($response) || (int)($response['status'] ?? 0) < 200 || (int)$response['status'] >= 300 || !is_array($data) || trim((string)($data['access_token'] ?? '')) === '') throw new RuntimeException('Dropbox could not refresh the connection.');
            $token = (string)$data['access_token']; $expires = max(60, min(86400, (int)($data['expires_in'] ?? 14400)));
            $stmt = $this->db->prepare('UPDATE module_dropbox_settings SET access_token_encrypted=:token, token_expires_at=:expires, updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
            $stmt->execute([':token' => $this->encrypt($token), ':expires' => gmdate('Y-m-d H:i:s', time()+$expires), ':workspace_id' => $this->workspaceId]);
            return $token;
        }
        $ch = curl_init('https://api.dropboxapi.com/oauth2/token');
        if ($ch === false) throw new RuntimeException('Dropbox token request could not be initialized.');
        $raw = ''; $tooLarge = false;
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $refresh]), CURLOPT_USERPWD => $key . ':' . $secret, CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_RETURNTRANSFER => false, CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw, &$tooLarge): int { if (strlen($raw) + strlen($chunk) > 65536) { $tooLarge = true; return 0; } $raw .= $chunk; return strlen($chunk); }, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'], ...$this->providerCurlOptions('https://api.dropboxapi.com/oauth2/token')]);
        $executed = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        $data = $executed === true && !$tooLarge ? json_decode($raw, true) : null;
        if ($status < 200 || $status >= 300 || !is_array($data) || trim((string)($data['access_token'] ?? '')) === '') throw new RuntimeException('Dropbox could not refresh the connection. Check the app key, secret, and refresh token.');
        $token = (string)$data['access_token']; $expires = max(60, min(86400, (int)($data['expires_in'] ?? 14400)));
        $stmt = $this->db->prepare('UPDATE module_dropbox_settings SET access_token_encrypted=:token, token_expires_at=:expires, updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        $stmt->execute([':token' => $this->encrypt($token), ':expires' => gmdate('Y-m-d H:i:s', time()+$expires), ':workspace_id' => $this->workspaceId]);
        return $token;
    }

    private function settingsRow(): ?array
    {
        $stmt = $this->db->prepare('SELECT app_key,app_secret_encrypted,refresh_token_encrypted,access_token_encrypted,token_expires_at,account_id,account_email,base_folder_path,is_active,updated_at FROM module_dropbox_settings WHERE workspace_id=:workspace_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function encrypt(string $value): string
    {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to protect Dropbox credentials.');
        $key = $this->cryptoKey(); $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, self::KEY_AAD, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('Dropbox credentials could not be encrypted.');
        return 'gcm:' . base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $value): string
    {
        if (!str_starts_with($value, 'gcm:') || !function_exists('openssl_decrypt')) throw new RuntimeException('Dropbox credentials require secure re-entry.');
        $blob = base64_decode(substr($value, 4), true);
        if (!is_string($blob) || strlen($blob) < 29) throw new RuntimeException('Dropbox credentials are unreadable.');
        $clear = openssl_decrypt(substr($blob, 28), 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, substr($blob, 0, 12), substr($blob, 12, 16), self::KEY_AAD);
        if (!is_string($clear)) throw new RuntimeException('Dropbox credentials are unreadable. Re-enter the provider credentials.');
        return $clear;
    }

    private function cryptoKey(): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if (strlen($key) < 32) throw new RuntimeException('APP_KEY must contain at least 32 characters to protect Dropbox credentials.');
        return hash('sha256', $key, true);
    }

    private function normalizeBasePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || strlen($path) > 180 || str_contains($path, '\\') || preg_match('/[\x00-\x1F\x7F]/', $path) || preg_match('~(?:^|/)\.\.?(/|$)~', $path)) throw new RuntimeException('Dropbox base folder path is invalid.');
        return '/' . trim($path, '/');
    }

    private function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '';
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') return 'file.bin';
        return function_exists('mb_substr') ? mb_substr($name, 0, 180, 'UTF-8') : substr($name, 0, 180);
    }

    private function providerCurlOptions(string $url): array
    {
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'https' || !in_array($host, ['api.dropboxapi.com', 'content.dropboxapi.com'], true) || (int)($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('Dropbox provider URL is invalid.');
        $validation = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https']);
        if (empty($validation['ok']) || empty($validation['resolved_ips'])) throw new RuntimeException('Dropbox endpoint failed outbound network safety validation.');
        $ip = trim((string)$validation['resolved_ips'][0], '[]');
        $resolveAddress = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $ip . ']' : $ip;
        return [CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_RESOLVE => [$host . ':443:' . $resolveAddress], CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*'];
    }
}
