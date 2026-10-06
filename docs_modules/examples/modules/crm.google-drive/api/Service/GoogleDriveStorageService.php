<?php

declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Service;

class GoogleDriveStorageService
{
    private \PDO $db;
    private int $workspaceId;
    private ?string $accessToken;

    public function __construct(\PDO $db, int $workspaceId = 1, ?string $accessToken = null)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->accessToken = $accessToken;
    }

    /**
     * Map MIME type to corresponding Google Drive icon / export category
     */
    public function categorizeMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'application/vnd.google-apps.document' => 'document',
            'application/vnd.google-apps.spreadsheet' => 'spreadsheet',
            'application/vnd.google-apps.presentation' => 'presentation',
            'application/pdf' => 'pdf',
            default => 'binary'
        };
    }

    /**
     * Compute webViewLink for Google Drive file
     */
    public function formatDriveLinks(string $driveFileId): array
    {
        return [
            'web_view_link' => "https://drive.google.com/file/d/{$driveFileId}/view?usp=sharing",
            'web_content_link' => "https://drive.google.com/uc?id={$driveFileId}&export=download"
        ];
    }

    /**
     * Record Google Drive file attachment in database
     */
    public function recordDriveFile(array $meta): array
    {
        $publicId = 'gdv_' . substr(md5(uniqid('', true)), 0, 16);
        $crmFileId = (string)($meta['crm_file_public_id'] ?? ('fil_' . uniqid()));
        $projectId = (string)($meta['crm_project_public_id'] ?? '');
        $clientId = (string)($meta['crm_client_public_id'] ?? '');
        $driveFileId = (string)($meta['drive_file_id'] ?? ('1g_' . substr(md5(uniqid()), 0, 18)));
        $name = (string)($meta['original_name'] ?? 'Google Document');
        $mime = (string)($meta['mime_type'] ?? 'application/pdf');
        $links = $this->formatDriveLinks($driveFileId);
        $viewLink = (string)($meta['web_view_link'] ?? $links['web_view_link']);
        $contentLink = (string)($meta['web_content_link'] ?? $links['web_content_link']);
        $size = (int)($meta['file_size_bytes'] ?? 0);
        $shareRole = (string)($meta['share_role'] ?? 'reader');

        $stmt = $this->db->prepare("
            INSERT INTO module_google_drive_files
            (public_id, workspace_id, crm_file_public_id, crm_project_public_id, crm_client_public_id, drive_file_id, original_name, mime_type, web_view_link, web_content_link, file_size_bytes, share_role, status, created_at)
            VALUES (:pid, :ws_id, :crm_file, :crm_proj, :crm_client, :g_id, :name, :mime, :vlink, :clink, :size, :role, 'linked', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'crm_file' => $crmFileId,
            'crm_proj' => $projectId !== '' ? $projectId : null,
            'crm_client' => $clientId !== '' ? $clientId : null,
            'g_id' => $driveFileId,
            'name' => $name,
            'mime' => $mime,
            'vlink' => $viewLink,
            'clink' => $contentLink,
            'size' => $size,
            'role' => $shareRole
        ]);

        return [
            'public_id' => $publicId,
            'crm_file_public_id' => $crmFileId,
            'drive_file_id' => $driveFileId,
            'web_view_link' => $viewLink,
            'category' => $this->categorizeMimeType($mime),
            'status' => 'linked'
        ];
    }

    /**
     * Get Google Drive API v3 headers
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization: Bearer ' . ($this->accessToken ?? 'mock_google_token'),
            'Accept: application/json'
        ];
    }
}
