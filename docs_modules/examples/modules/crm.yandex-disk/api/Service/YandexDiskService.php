<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Service;

class YandexDiskService
{
    private \PDO $db;
    private int $workspaceId;
    private ?string $oauthToken;

    public function __construct(\PDO $db, int $workspaceId = 1, ?string $oauthToken = null)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->oauthToken = $oauthToken;
    }

    /**
     * Determines whether the file needs the txt-proxy bypass technique
     */
    public function shouldBypassExtension(string $filename, int $sizeBytes): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $heavyExtensions = ['zip', 'tar', 'gz', '7z', 'rar', 'mp4', 'avi', 'mkv', 'mov', 'iso', 'bin', 'bak', 'sql'];

        // If file is heavy or belongs to binary/archive/media list, use bypass
        return in_array($ext, $heavyExtensions, true) || $sizeBytes > 5 * 1024 * 1024;
    }

    /**
     * Prepare upload strategy with txt-proxy bypass naming
     */
    public function planUpload(string $originalFilename, int $sizeBytes, string $remoteDir = '/TropaTT_CRM'): array
    {
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalFilename, PATHINFO_FILENAME);
        $needsBypass = $this->shouldBypassExtension($originalFilename, $sizeBytes);

        $cleanDir = rtrim($remoteDir, '/');
        $finalRemotePath = $cleanDir . '/' . $originalFilename;

        if ($needsBypass) {
            $tempRemoteName = $baseName . '_' . substr(md5(uniqid()), 0, 6) . '.txt';
            $tempRemotePath = $cleanDir . '/' . $tempRemoteName;
        } else {
            $tempRemotePath = $finalRemotePath;
        }

        return [
            'original_filename' => $originalFilename,
            'extension' => $ext,
            'needs_bypass' => $needsBypass,
            'upload_path' => $tempRemotePath,
            'final_path' => $finalRemotePath,
            'rename_needed' => $needsBypass
        ];
    }

    /**
     * Record uploaded file metadata in CRM database
     */
    public function recordUploadedFile(array $meta): array
    {
        $publicId = 'ydk_' . substr(md5(uniqid('', true)), 0, 16);
        $crmFileId = (string)($meta['crm_file_public_id'] ?? ('fil_' . uniqid()));
        $name = (string)($meta['original_name'] ?? 'file');
        $ext = (string)($meta['original_extension'] ?? pathinfo($name, PATHINFO_EXTENSION));
        $yandexPath = (string)($meta['yandex_path'] ?? '');
        $size = (int)($meta['file_size_bytes'] ?? 0);
        $publicUrl = $meta['public_url'] ?? "https://disk.yandex.ru/d/" . substr(md5($publicId), 0, 10);
        $usedBypass = !empty($meta['used_bypass']) ? 1 : 0;

        $stmt = $this->db->prepare("
            INSERT INTO module_yandex_disk_files
            (public_id, workspace_id, crm_file_public_id, original_name, original_extension, yandex_path, file_size_bytes, public_url, used_bypass, status, created_at)
            VALUES (:pid, :ws_id, :crm_id, :name, :ext, :ypath, :size, :purl, :bypass, 'uploaded', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'crm_id' => $crmFileId,
            'name' => $name,
            'ext' => $ext,
            'ypath' => $yandexPath,
            'size' => $size,
            'purl' => $publicUrl,
            'bypass' => $usedBypass
        ]);

        return [
            'public_id' => $publicId,
            'crm_file_public_id' => $crmFileId,
            'yandex_path' => $yandexPath,
            'public_url' => $publicUrl,
            'used_bypass' => (bool)$usedBypass,
            'status' => 'uploaded'
        ];
    }

    /**
     * Build Yandex Disk REST API headers
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization: OAuth ' . ($this->oauthToken ?? 'mock_token'),
            'Accept: application/json'
        ];
    }
}
