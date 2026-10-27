<?php
declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Service;

use Api\System\Library\Security\UrlSafetyValidator;
use PDO;
use RuntimeException;
use Closure;

final class GoogleDriveApiException extends RuntimeException
{
    public function __construct(public readonly int $status)
    {
        parent::__construct('Google Drive request failed with HTTP ' . $status . '.');
    }
}

/** Workspace-scoped Google Drive OAuth and private file-transfer client. */
final class GoogleDriveStorageService
{
    private const MAX_FILE_BYTES = 20 * 1024 * 1024;
    private const AAD = 'crm.google-drive.credentials.v1';
    private const UPLOAD_LEASE_SECONDS = 300;
    private ?Closure $httpTransport;

    public function __construct(private readonly PDO $db, private readonly int $workspaceId, ?callable $httpTransport = null)
    {
        if ($workspaceId <= 0) throw new RuntimeException('An authorized workspace is required.');
        $this->httpTransport = $httpTransport !== null ? Closure::fromCallable($httpTransport) : null;
    }

    public function categorizeMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'application/vnd.google-apps.document' => 'document',
            'application/vnd.google-apps.spreadsheet' => 'spreadsheet',
            'application/vnd.google-apps.presentation' => 'presentation',
            'application/pdf' => 'pdf',
            default => 'binary',
        };
    }

    public function getStatus(): array
    {
        $row = $this->settingsRow();
        return [
            'configured' => is_array($row) && !empty($row['refresh_token_encrypted']) && !empty($row['client_id']) && !empty($row['client_secret_encrypted']),
            'connected' => is_array($row) && !empty($row['google_account_email']),
            'account_email' => is_array($row) ? (string)($row['google_account_email'] ?? '') : '',
            'root_folder_id' => is_array($row) ? (string)($row['root_folder_id'] ?? '') : '',
            'root_folder_name' => is_array($row) ? (string)($row['root_folder_name'] ?? 'TropaTT CRM') : 'TropaTT CRM',
            'last_checked_at' => is_array($row) ? ($row['updated_at'] ?? null) : null,
        ];
    }

    public function saveSettings(string $clientId, string $clientSecret, string $refreshToken, string $rootFolderId = ''): void
    {
        foreach ([$clientId, $clientSecret, $refreshToken] as $value) {
            if (trim($value) === '' || strlen($value) > 8192 || preg_match('/[\x00-\x20\x7F]/', $value)) throw new RuntimeException('Google OAuth fields are invalid.');
        }
        $rootFolderId = trim($rootFolderId);
        if ($rootFolderId !== '' && !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $rootFolderId)) throw new RuntimeException('Google Drive folder ID is invalid.');
        $secretEncrypted = $this->encrypt($clientSecret);
        $refreshEncrypted = $this->encrypt($refreshToken);
        $existing = $this->settingsRow();
        if ($existing !== null) {
            $stmt = $this->db->prepare('UPDATE module_google_drive_settings SET client_id=:client_id,client_secret_encrypted=:client_secret,refresh_token_encrypted=:refresh_token,access_token_encrypted=NULL,token_expires_at=NULL,google_account_email=NULL,root_folder_id=:folder_id,is_active=1,updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        } else {
            $stmt = $this->db->prepare('INSERT INTO module_google_drive_settings (workspace_id,client_id,client_secret_encrypted,refresh_token_encrypted,access_token_encrypted,token_expires_at,root_folder_id,root_folder_name,auto_create_project_folders,is_active,created_at,updated_at) VALUES (:workspace_id,:client_id,:client_secret,:refresh_token,NULL,NULL,:folder_id,:folder_name,0,1,CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
        }
        $stmt->execute([':workspace_id' => $this->workspaceId, ':client_id' => trim($clientId), ':client_secret' => $secretEncrypted, ':refresh_token' => $refreshEncrypted, ':folder_id' => $rootFolderId !== '' ? $rootFolderId : null, ':folder_name' => 'TropaTT CRM']);
    }

    public function testConnection(): array
    {
        $token = $this->accessToken(true);
        $about = $this->requestJson('GET', 'https://www.googleapis.com/drive/v3/about?fields=user(emailAddress,displayName),storageQuota(limit,usage)', $token);
        $email = trim((string)($about['user']['emailAddress'] ?? ''));
        if ($email === '') throw new RuntimeException('Google Drive did not confirm the account.');
        $stmt = $this->db->prepare('UPDATE module_google_drive_settings SET google_account_email=:email,updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        $stmt->execute([':email' => substr($email, 0, 255), ':workspace_id' => $this->workspaceId]);
        return ['ok' => true, 'account_email' => $email, 'display_name' => (string)($about['user']['displayName'] ?? '')];
    }

    /** Upload an authorized CRM file using a DB claim and provider-side reconciliation. */
    public function uploadFile(array $file, string $bytes): array
    {
        $size = strlen($bytes);
        if ($size <= 0 || $size > self::MAX_FILE_BYTES || (int)($file['size_bytes'] ?? -1) !== $size || !empty($file['is_quarantined'])) throw new RuntimeException('The file is unsafe, empty, or exceeds 20 MB.');
        $filePublicId = trim((string)($file['public_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $filePublicId)) throw new RuntimeException('Invalid CRM file identifier.');
        $name = $this->safeFilename((string)($file['original_name'] ?? 'file.bin'));
        $mime = trim((string)($file['mime_type'] ?? 'application/octet-stream'));
        if (!preg_match('~^[A-Za-z0-9!#$&^_.+-]+/[A-Za-z0-9!#$&^_.+-]+$~', $mime)) $mime = 'application/octet-stream';
        $row = $this->settingsRow();
        if (!$row || (int)($row['is_active'] ?? 0) !== 1) throw new RuntimeException('Google Drive is not configured for this workspace.');
        $digest = hash('sha256', $bytes);
        $accessToken = $this->accessToken();
        $mapping = $this->findFileMapping($filePublicId);
        if (is_array($mapping) && in_array((string)($mapping['status'] ?? ''), ['linked', 'updating'], true) && trim((string)($mapping['drive_file_id'] ?? '')) !== '' && (string)$mapping['drive_file_id'] !== 'pending') {
            $claimId = $this->claimLinkedFileMapping($filePublicId, $name, $mime, $size, $mapping);
            if ($claimId === null) throw new RuntimeException('A Google Drive file update is already in progress. Retry after it finishes.');
            return $this->updateMappedFile($mapping, $claimId, $filePublicId, $name, $mime, $size, $bytes, $digest, $accessToken);
        }

        $claimId = $this->claimFileMapping($filePublicId, $name, $mime, $size, $mapping);
        if ($claimId === null) {
            $current = $this->findFileMapping($filePublicId);
            if (is_array($current) && (string)($current['status'] ?? '') === 'linked'
                && trim((string)($current['drive_file_id'] ?? '')) !== ''
                && (string)$current['drive_file_id'] !== 'pending') {
                $updateClaim = $this->claimLinkedFileMapping($filePublicId, $name, $mime, $size, $current);
                if ($updateClaim === null) throw new RuntimeException('A Google Drive file update is already in progress. Retry after it finishes.');
                return $this->updateMappedFile($current, $updateClaim, $filePublicId, $name, $mime, $size, $bytes, $digest, $accessToken);
            }
            if (!is_array($current) || !in_array((string)($current['status'] ?? ''), ['pending', 'uploading'], true)) {
                throw new RuntimeException('Google Drive upload reservation changed; retry after reconciliation.');
            }
            $reconciled = $this->findProviderFile($filePublicId, $digest, $size, $name, (string)($row['root_folder_id'] ?? ''));
            if ($reconciled === null) throw new RuntimeException('A file upload is already in progress. Retry after it finishes.');
            return $this->completeFileClaim($filePublicId, (string)$current['public_id'], $reconciled, $name, $mime, $size, true);
        }

        $remote = $this->findProviderFile($filePublicId, $digest, $size, $name, (string)($row['root_folder_id'] ?? ''));
        if ($remote !== null) return $this->completeFileClaim($filePublicId, $claimId, $remote, $name, $mime, $size, true);
        $claim = $this->findFileMapping($filePublicId);
        $driveId = trim((string)($claim['drive_file_id'] ?? ''));
        if ($driveId === '' || $driveId === 'pending') {
            $generated = $this->requestJson('GET', 'https://www.googleapis.com/drive/v3/files/generateIds?count=1&space=drive&fields=ids', $accessToken);
            $driveId = trim((string)($generated['ids'][0] ?? ''));
            if (!preg_match('/^[A-Za-z0-9_-]{10,128}$/', $driveId)) throw new RuntimeException('Google Drive did not return a valid idempotent file ID.');
            $saveId = $this->db->prepare('UPDATE module_google_drive_files SET drive_file_id=:drive_id,updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:crm_id AND public_id=:claim AND status=\'pending\' AND drive_file_id=\'pending\'');
            $saveId->execute([':drive_id' => $driveId, ':workspace_id' => $this->workspaceId, ':crm_id' => $filePublicId, ':claim' => $claimId]);
            if ($saveId->rowCount() !== 1) throw new RuntimeException('Google Drive upload ID reservation changed; retry after reconciliation.');
        } elseif (!preg_match('/^[A-Za-z0-9_-]{10,128}$/', $driveId)) {
            throw new RuntimeException('Google Drive upload is awaiting manual reconciliation.');
        }
        $byReservedId = $this->getProviderFileById($driveId);
        if ($byReservedId !== null) {
            $remote = $this->validateProviderFile($byReservedId, $filePublicId, $digest, $size, $filePublicId . '-' . $name, (string)($row['root_folder_id'] ?? ''));
            return $this->completeFileClaim($filePublicId, $claimId, $remote, $name, $mime, $size, true);
        }
        $mark = $this->db->prepare('UPDATE module_google_drive_files SET status=\'uploading\',updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:crm_id AND public_id=:claim AND status=\'pending\'');
        $mark->execute([':workspace_id' => $this->workspaceId, ':crm_id' => $filePublicId, ':claim' => $claimId]);
        if ($mark->rowCount() !== 1) throw new RuntimeException('Google Drive upload reservation expired before provider upload; retry after reconciliation.');

        $metadata = ['id' => $driveId, 'name' => $filePublicId . '-' . $name, 'mimeType' => $mime, 'appProperties' => ['crm_workspace_id' => (string)$this->workspaceId, 'crm_file_public_id' => $filePublicId, 'crm_sha256' => $digest]];
        $folderId = trim((string)($row['root_folder_id'] ?? ''));
        if ($folderId !== '') $metadata['parents'] = [$folderId];
        $boundary = 'crm_' . bin2hex(random_bytes(16));
        $body = '--' . $boundary . "\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
            . json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            . "\r\n--" . $boundary . "\r\nContent-Type: " . $mime . "\r\n\r\n" . $bytes . "\r\n--" . $boundary . "--\r\n";
        try {
            $response = $this->requestJson('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,size,mimeType,webViewLink,parents,appProperties', $accessToken, $body, 'multipart/related; boundary=' . $boundary);
            $remote = $this->validateProviderFile($response, $filePublicId, $digest, $size, $metadata['name'], $folderId);
            return $this->completeFileClaim($filePublicId, $claimId, $remote, $name, $mime, $size, false);
        } catch (\Throwable $uploadError) {
            try {
                $byId = $this->getProviderFileById($driveId);
                if ($byId !== null) {
                    $reconciled = $this->validateProviderFile($byId, $filePublicId, $digest, $size, $metadata['name'], $folderId);
                    return $this->completeFileClaim($filePublicId, $claimId, $reconciled, $name, $mime, $size, true);
                }
                $reconciled = $this->findProviderFile($filePublicId, $digest, $size, $name, $folderId);
                if ($reconciled !== null) return $this->completeFileClaim($filePublicId, $claimId, $reconciled, $name, $mime, $size, true);
            } catch (\Throwable $reconcileError) {
                throw $reconcileError;
            }
            throw $uploadError;
        }
    }

    /** @return array<string,mixed>|null */
    private function findFileMapping(string $filePublicId): ?array
    {
        $stmt = $this->db->prepare('SELECT public_id,drive_file_id,web_view_link,file_size_bytes,status FROM module_google_drive_files WHERE workspace_id=:workspace_id AND crm_file_public_id=:file_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId, ':file_id' => $filePublicId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** Atomic workspace/file reservation prevents duplicate concurrent provider creates. */
    private function claimFileMapping(string $filePublicId, string $name, string $mime, int $size, ?array $existing): ?string
    {
        $claimId = 'gdf_' . bin2hex(random_bytes(12));
        if ($existing === null) {
            try {
                $stmt = $this->db->prepare('INSERT INTO module_google_drive_files (public_id,workspace_id,crm_file_public_id,drive_file_id,original_name,mime_type,web_view_link,web_content_link,file_size_bytes,share_role,status,created_at,updated_at) VALUES (:claim,:workspace_id,:crm_id,\'pending\',:name,:mime,\'\',NULL,:size,\'private\',\'pending\',CURRENT_TIMESTAMP,CURRENT_TIMESTAMP)');
                $stmt->execute([':claim' => $claimId, ':workspace_id' => $this->workspaceId, ':crm_id' => $filePublicId, ':name' => substr($name, 0, 255), ':mime' => substr($mime, 0, 128), ':size' => $size]);
                return $claimId;
            } catch (\PDOException) {
                $existing = $this->findFileMapping($filePublicId);
                if ($existing === null) throw new RuntimeException('Google Drive upload could not reserve the CRM file mapping.');
            }
        }
        $state = (string)($existing['status'] ?? '');
        if (!in_array($state, ['pending', 'uploading'], true)) return null;
        $existingDriveId = trim((string)($existing['drive_file_id'] ?? ''));
        if ($state === 'uploading' && !preg_match('/^[A-Za-z0-9_-]{10,128}$/', $existingDriveId)) return null;
        $previousClaim = (string)($existing['public_id'] ?? '');
        $staleBefore = date('Y-m-d H:i:s', time() - self::UPLOAD_LEASE_SECONDS);
        $stmt = $this->db->prepare('UPDATE module_google_drive_files SET public_id=:new_claim,original_name=:name,mime_type=:mime,file_size_bytes=:size,status=\'pending\',updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:crm_id AND public_id=:old_claim AND status=:old_status AND updated_at<=:stale_before');
        $stmt->execute([':new_claim' => $claimId, ':name' => substr($name, 0, 255), ':mime' => substr($mime, 0, 128), ':size' => $size, ':workspace_id' => $this->workspaceId, ':crm_id' => $filePublicId, ':old_claim' => $previousClaim, ':old_status' => $state, ':stale_before' => $staleBefore]);
        return $stmt->rowCount() === 1 ? $claimId : null;
    }

    /** Fetch by a pre-generated Drive ID to safely resolve an indeterminate create retry. */
    private function getProviderFileById(string $driveId): ?array
    {
        try {
            return $this->requestJson('GET', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($driveId) . '?supportsAllDrives=true&fields=id,name,size,mimeType,webViewLink,parents,appProperties,trashed', $this->accessToken());
        } catch (GoogleDriveApiException $error) {
            if ($error->status === 404) return null;
            throw $error;
        }
    }

    /** @return array<string,mixed>|null */
    private function findProviderFile(string $filePublicId, string $digest, int $size, string $name, string $folderId): ?array
    {
        $query = "trashed = false and appProperties has { key = 'crm_workspace_id' and value = '" . $this->workspaceId . "' } and appProperties has { key = 'crm_file_public_id' and value = '" . $filePublicId . "' }";
        $url = 'https://www.googleapis.com/drive/v3/files?' . http_build_query(['q' => $query, 'spaces' => 'drive', 'pageSize' => '2', 'fields' => 'nextPageToken,files(id,name,size,mimeType,webViewLink,parents,appProperties,trashed)', 'supportsAllDrives' => 'true', 'includeItemsFromAllDrives' => 'true'], '', '&', PHP_QUERY_RFC3986);
        $result = $this->requestJson('GET', $url, $this->accessToken());
        $items = $result['files'] ?? null;
        if (!is_array($items)) throw new RuntimeException('Google Drive identity lookup returned an invalid result.');
        if (count($items) > 1 || trim((string)($result['nextPageToken'] ?? '')) !== '') throw new RuntimeException('Multiple Google Drive objects match this workspace/file identity; manual reconciliation is required.');
        if ($items === []) return null;
        return $this->validateProviderFile($items[0], $filePublicId, $digest, $size, $filePublicId . '-' . $name, $folderId);
    }

    /** @param array<string,mixed> $metadata @return array<string,mixed> */
    private function validateProviderFile(array $metadata, string $filePublicId, string $digest, int $size, string $expectedName, string $folderId): array
    {
        $properties = is_array($metadata['appProperties'] ?? null) ? $metadata['appProperties'] : [];
        $id = trim((string)($metadata['id'] ?? ''));
        if ($id === '' || ($metadata['trashed'] ?? false) === true
            || ($properties['crm_workspace_id'] ?? '') !== (string)$this->workspaceId
            || ($properties['crm_file_public_id'] ?? '') !== $filePublicId
            || ($properties['crm_sha256'] ?? '') !== $digest
            || (int)($metadata['size'] ?? -1) !== $size
            || (string)($metadata['name'] ?? '') !== $expectedName
            || ($folderId !== '' && !in_array($folderId, (array)($metadata['parents'] ?? []), true))) {
            throw new RuntimeException('A Drive object matched the CRM identity but its content or location did not; it was not adopted.');
        }
        $link = trim((string)($metadata['webViewLink'] ?? ''));
        if (!$this->isGoogleDriveLink($link)) $link = '';
        $metadata['webViewLink'] = $link;
        return $metadata;
    }

    /** @param array<string,mixed> $remote */
    private function completeFileClaim(string $filePublicId, string $claimId, array $remote, string $name, string $mime, int $size, bool $alreadyUploaded): array
    {
        $driveId = trim((string)($remote['id'] ?? ''));
        $link = (string)($remote['webViewLink'] ?? '');
        $stmt = $this->db->prepare('UPDATE module_google_drive_files SET drive_file_id=:drive_id,original_name=:name,mime_type=:mime,web_view_link=:link,web_content_link=NULL,file_size_bytes=:size,share_role=\'private\',status=\'linked\',updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:crm_id AND public_id=:claim AND status IN (\'pending\',\'uploading\',\'updating\')');
        $stmt->execute([':drive_id' => $driveId, ':name' => substr($name, 0, 255), ':mime' => substr($mime, 0, 128), ':link' => substr($link, 0, 500), ':size' => $size, ':workspace_id' => $this->workspaceId, ':crm_id' => $filePublicId, ':claim' => $claimId]);
        if ($stmt->rowCount() !== 1) {
            $winner = $this->findFileMapping($filePublicId);
            if (!is_array($winner) || (string)($winner['status'] ?? '') !== 'linked' || !hash_equals((string)($winner['drive_file_id'] ?? ''), $driveId) || (int)($winner['file_size_bytes'] ?? -1) !== $size) throw new RuntimeException('Google Drive upload claim changed before mapping could be committed.');
        }
        return ['ok' => true, 'drive_file_id' => $driveId, 'web_view_link' => $link, 'size_bytes' => $size, 'already_uploaded' => $alreadyUploaded];
    }

    /** Claim an existing linked row before provider writes; stale claims are recoverable after the bounded HTTP timeout. */
    private function claimLinkedFileMapping(string $filePublicId, string $name, string $mime, int $size, array $mapping): ?string
    {
        $driveId = trim((string)($mapping['drive_file_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{10,128}$/', $driveId)) throw new RuntimeException('The linked Drive file identifier is invalid.');
        $claimId = 'gdf_' . bin2hex(random_bytes(12));
        $previousClaim = (string)($mapping['public_id'] ?? '');
        $state = (string)($mapping['status'] ?? '');
        $values = [':new_claim' => $claimId, ':name' => substr($name, 0, 255), ':mime' => substr($mime, 0, 128), ':size' => $size, ':workspace_id' => $this->workspaceId, ':crm_id' => $filePublicId, ':old_claim' => $previousClaim, ':drive_id' => $driveId];
        if ($state === 'linked') {
            $stmt = $this->db->prepare('UPDATE module_google_drive_files SET public_id=:new_claim,original_name=:name,mime_type=:mime,file_size_bytes=:size,status=\'updating\',updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:crm_id AND public_id=:old_claim AND drive_file_id=:drive_id AND status=\'linked\'');
            $stmt->execute($values);
            return $stmt->rowCount() === 1 ? $claimId : null;
        }
        if ($state !== 'updating') return null;
        $values[':stale_before'] = date('Y-m-d H:i:s', time() - self::UPLOAD_LEASE_SECONDS);
        $stmt = $this->db->prepare('UPDATE module_google_drive_files SET public_id=:new_claim,original_name=:name,mime_type=:mime,file_size_bytes=:size,updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id AND crm_file_public_id=:crm_id AND public_id=:old_claim AND drive_file_id=:drive_id AND status=\'updating\' AND updated_at<=:stale_before');
        $stmt->execute($values);
        return $stmt->rowCount() === 1 ? $claimId : null;
    }

    private function updateMappedFile(array $mapping, string $claimId, string $filePublicId, string $name, string $mime, int $size, string $bytes, string $digest, string $accessToken): array
    {
        $knownId = (string)$mapping['drive_file_id'];
        $verifyUrl = 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($knownId) . '?supportsAllDrives=true&fields=id,name,size,mimeType,md5Checksum,webViewLink,trashed,parents,appProperties';
        $verify = $this->requestJson('GET', $verifyUrl, $accessToken);
        $properties = (array)($verify['appProperties'] ?? []);
        if (($verify['id'] ?? '') !== $knownId || ($verify['trashed'] ?? false) === true || ($properties['crm_workspace_id'] ?? '') !== (string)$this->workspaceId || ($properties['crm_file_public_id'] ?? '') !== $filePublicId) throw new RuntimeException('The linked Drive object does not belong to this workspace/file mapping.');
        $expectedMd5 = md5($bytes);
        $alreadyUploaded = ($properties['crm_sha256'] ?? '') === $digest && (int)($verify['size'] ?? -1) === $size
            && hash_equals(strtolower((string)($verify['md5Checksum'] ?? '')), $expectedMd5);
        if (!$alreadyUploaded) {
            $updated = $this->requestJson('PATCH', 'https://www.googleapis.com/upload/drive/v3/files/' . rawurlencode($knownId) . '?uploadType=media&supportsAllDrives=true&fields=id,size,mimeType,webViewLink', $accessToken, $bytes, $mime);
            if ((int)($updated['size'] ?? 0) !== $size) throw new RuntimeException('Google Drive did not confirm the updated file size.');
            $properties['crm_sha256'] = $digest;
            $metadata = $this->requestJson('PATCH', 'https://www.googleapis.com/drive/v3/files/' . rawurlencode($knownId) . '?supportsAllDrives=true&fields=id,appProperties', $accessToken, json_encode(['appProperties' => $properties], JSON_THROW_ON_ERROR));
            if (($metadata['id'] ?? '') !== $knownId) throw new RuntimeException('Google Drive did not confirm file ownership metadata.');
            $verify = $this->requestJson('GET', $verifyUrl, $accessToken);
            $properties = (array)($verify['appProperties'] ?? []);
        }
        if (($verify['id'] ?? '') !== $knownId || ($verify['trashed'] ?? false) === true
            || ($properties['crm_workspace_id'] ?? '') !== (string)$this->workspaceId
            || ($properties['crm_file_public_id'] ?? '') !== $filePublicId
            || ($properties['crm_sha256'] ?? '') !== $digest || (int)($verify['size'] ?? -1) !== $size
            || !hash_equals(strtolower((string)($verify['md5Checksum'] ?? '')), $expectedMd5)) {
            throw new RuntimeException('Google Drive could not verify the linked file identity, SHA-256, MD5, and size after update.');
        }
        $this->completeFileClaim($filePublicId, $claimId, $verify, $name, $mime, $size, $alreadyUploaded);
        return ['ok' => true, 'drive_file_id' => $knownId, 'web_view_link' => (string)($verify['webViewLink'] ?? ''), 'size_bytes' => $size, 'already_uploaded' => $alreadyUploaded];
    }

    /** Never synthesize a Drive link from an unverified ID. */
    public function formatDriveLinks(string $driveFileId): array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $driveFileId)) throw new RuntimeException('Invalid Google Drive file identifier.');
        $stmt = $this->db->prepare('SELECT drive_file_id,web_view_link,web_content_link FROM module_google_drive_files WHERE workspace_id=:workspace_id AND drive_file_id=:drive_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId, ':drive_id' => $driveFileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) throw new RuntimeException('Google Drive file is not linked in this workspace.');
        return ['web_view_link' => (string)$row['web_view_link'], 'web_content_link' => (string)($row['web_content_link'] ?? '')];
    }

    private function accessToken(bool $forceRefresh = false): string
    {
        $row = $this->settingsRow();
        if (!$row || (int)($row['is_active'] ?? 0) !== 1) throw new RuntimeException('Google Drive is not configured for this workspace.');
        $expires = strtotime((string)($row['token_expires_at'] ?? '')) ?: 0;
        if (!$forceRefresh && $expires > time() + 60 && !empty($row['access_token_encrypted'])) return $this->decrypt((string)$row['access_token_encrypted']);
        $clientSecret = $this->decrypt((string)$row['client_secret_encrypted']);
        $refreshToken = $this->decrypt((string)$row['refresh_token_encrypted']);
        $response = $this->requestForm('https://oauth2.googleapis.com/token', [
            'client_id' => (string)$row['client_id'], 'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken, 'grant_type' => 'refresh_token',
        ]);
        $access = trim((string)($response['access_token'] ?? ''));
        $expiresIn = (int)($response['expires_in'] ?? 0);
        if ($access === '' || $expiresIn < 60) throw new RuntimeException('Google OAuth did not issue a valid access token.');
        $stmt = $this->db->prepare('UPDATE module_google_drive_settings SET access_token_encrypted=:token,token_expires_at=:expires,updated_at=CURRENT_TIMESTAMP WHERE workspace_id=:workspace_id');
        $stmt->execute([':token' => $this->encrypt($access), ':expires' => date('Y-m-d H:i:s', time() + min($expiresIn, 86400)), ':workspace_id' => $this->workspaceId]);
        return $access;
    }

    private function requestForm(string $url, array $data): array
    {
        return $this->request('POST', $url, null, 'application/x-www-form-urlencoded', http_build_query($data, '', '&', PHP_QUERY_RFC3986));
    }

    private function requestJson(string $method, string $url, string $token, ?string $body = null, string $contentType = 'application/json'): array
    {
        return $this->request($method, $url, $token, $contentType, $body);
    }

    private function request(string $method, string $url, ?string $token, string $contentType, ?string $body = null): array
    {
        if ($this->httpTransport === null && (!function_exists('curl_init') || !extension_loaded('openssl'))) throw new RuntimeException('Secure HTTPS support is unavailable.');
        $parts = parse_url($url);
        $allowedHost = in_array(strtolower((string)($parts['host'] ?? '')), ['oauth2.googleapis.com', 'www.googleapis.com'], true);
        if (($parts['scheme'] ?? '') !== 'https' || !$allowedHost || (int)($parts['port'] ?? 443) !== 443 || isset($parts['user']) || isset($parts['pass'])) throw new RuntimeException('Google API URL is invalid.');
        $headers = ['Accept: application/json'];
        if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
        if ($body !== null) $headers[] = 'Content-Type: ' . $contentType;
        if ($this->httpTransport !== null) {
            $response = ($this->httpTransport)($method, $url, $token, $contentType, $body, 262144);
            if (!is_array($response) || !is_string($response['body'] ?? null) || strlen($response['body']) > 262144) throw new RuntimeException('Google Drive response was invalid or too large.');
            $status = (int)($response['status'] ?? 0);
            if ($status < 200 || $status >= 300) throw new GoogleDriveApiException($status);
            $decoded = json_decode($response['body'], true);
            if (!is_array($decoded)) throw new RuntimeException('Google Drive returned an invalid response.');
            return $decoded;
        }
        $raw = '';
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Google API request could not be initialized.');
        $validation = (new UrlSafetyValidator())->validateProviderUrl($url, true, ['https']);
        if (empty($validation['ok']) || empty($validation['resolved_ips'])) throw new RuntimeException('Google API endpoint failed SSRF validation.');
        $host = strtolower((string)$parts['host']);
        $ip = trim((string)$validation['resolved_ips'][0], '[]');
        $resolveAddress = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $ip . ']' : $ip;
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => false, CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int { if (strlen($raw) + strlen($chunk) > 262144) return 0; $raw .= $chunk; return strlen($chunk); }, CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 35, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_RESOLVE => [$host . ':443:' . $resolveAddress], CURLOPT_PROXY => '', CURLOPT_NOPROXY => '*']);
        $executed = curl_exec($ch); $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE); curl_close($ch);
        if ($executed !== true) throw new RuntimeException('Google Drive request failed before a response was received.');
        if ($status < 200 || $status >= 300) throw new GoogleDriveApiException($status);
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) throw new RuntimeException('Google Drive returned an invalid response.');
        return $decoded;
    }

    private function settingsRow(): ?array
    {
        $stmt = $this->db->prepare('SELECT client_id,client_secret_encrypted,refresh_token_encrypted,access_token_encrypted,token_expires_at,google_account_email,root_folder_id,root_folder_name,is_active,updated_at FROM module_google_drive_settings WHERE workspace_id=:workspace_id LIMIT 1');
        $stmt->execute([':workspace_id' => $this->workspaceId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function encrypt(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) throw new RuntimeException('OpenSSL is required to protect Google credentials.');
        $iv = random_bytes(12); $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, $iv, $tag, self::AAD, 16);
        if (!is_string($cipher) || strlen($tag) !== 16) throw new RuntimeException('Google credential encryption failed.');
        return 'gcm:' . base64_encode($iv . $tag . $cipher);
    }

    private function decrypt(string $encrypted): string
    {
        if (!str_starts_with($encrypted, 'gcm:')) throw new RuntimeException('Stored Google credentials require secure re-entry.');
        $raw = base64_decode(substr($encrypted, 4), true);
        if (!is_string($raw) || strlen($raw) < 29) throw new RuntimeException('Stored Google credentials are unreadable.');
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $this->cryptoKey(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16), self::AAD);
        if (!is_string($plain)) throw new RuntimeException('Stored Google credentials are unreadable. Re-enter them.');
        return $plain;
    }

    private function cryptoKey(): string
    {
        $key = trim((string)(getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '')));
        if (strlen($key) < 32) throw new RuntimeException('APP_KEY must contain at least 32 characters to protect Google credentials.');
        return hash('sha256', $key, true);
    }

    private function safeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $name) ?? '');
        if ($name === '' || $name === '.' || $name === '..') return 'file.bin';
        return function_exists('mb_substr') ? mb_substr($name, 0, 160, 'UTF-8') : substr($name, 0, 160);
    }

    private function isGoogleDriveLink(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && in_array(strtolower((string)($parts['host'] ?? '')), ['drive.google.com', 'docs.google.com'], true) && (int)($parts['port'] ?? 443) === 443 && !isset($parts['user']) && !isset($parts['pass']);
    }
}
