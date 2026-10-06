<?php

declare(strict_types=1);

namespace Module\Crm\Dropbox\Service;

class DropboxStorageService
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
     * Compute clean Dropbox project folder path
     */
    public function resolveProjectPath(?string $projectPublicId, string $filename, string $basePath = '/TropaTT_CRM'): array
    {
        $cleanBase = '/' . trim($basePath, '/');
        $cleanFilename = ltrim($filename, '/');

        if ($projectPublicId !== null && $projectPublicId !== '') {
            $folder = "{$cleanBase}/Projects/{$projectPublicId}";
        } else {
            $folder = "{$cleanBase}/General";
        }

        $fullPath = "{$folder}/{$cleanFilename}";

        return [
            'folder' => $folder,
            'path_display' => $fullPath,
            'path_lower' => strtolower($fullPath)
        ];
    }

    /**
     * Generate standard Dropbox shared link format
     */
    public function buildSharedLink(string $pathDisplay, bool $directDownload = false): string
    {
        $hash = substr(md5($pathDisplay), 0, 15);
        $cleanName = rawurlencode(basename($pathDisplay));
        $dlParam = $directDownload ? 'dl=1' : 'dl=0';

        return "https://www.dropbox.com/scl/fi/{$hash}/{$cleanName}?rlkey=live_{$hash}&{$dlParam}";
    }

    /**
     * Save synced file record to database
     */
    public function recordSyncedFile(array $meta): array
    {
        $publicId = 'dbx_' . substr(md5(uniqid('', true)), 0, 16);
        $crmFileId = (string)($meta['crm_file_public_id'] ?? ('fil_' . uniqid()));
        $projectId = (string)($meta['crm_project_public_id'] ?? '');
        $dropboxId = (string)($meta['dropbox_file_id'] ?? ('id:' . substr(md5(uniqid()), 0, 12)));
        $pathDisplay = (string)($meta['dropbox_path_display'] ?? '/TropaTT_CRM/file.bin');
        $pathLower = strtolower($pathDisplay);
        $size = (int)($meta['file_size_bytes'] ?? 0);
        $sharedLink = (string)($meta['shared_link_url'] ?? $this->buildSharedLink($pathDisplay));

        $stmt = $this->db->prepare("
            INSERT INTO module_dropbox_files
            (public_id, workspace_id, crm_file_public_id, crm_project_public_id, dropbox_file_id, dropbox_path_lower, dropbox_path_display, file_size_bytes, shared_link_url, status, created_at)
            VALUES (:pid, :ws_id, :crm_file, :crm_proj, :dbx_id, :plow, :pdisp, :size, :surl, 'synced', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'crm_file' => $crmFileId,
            'crm_proj' => $projectId !== '' ? $projectId : null,
            'dbx_id' => $dropboxId,
            'plow' => $pathLower,
            'pdisp' => $pathDisplay,
            'size' => $size,
            'surl' => $sharedLink
        ]);

        return [
            'public_id' => $publicId,
            'crm_file_public_id' => $crmFileId,
            'dropbox_file_id' => $dropboxId,
            'dropbox_path' => $pathDisplay,
            'shared_link_url' => $sharedLink,
            'status' => 'synced'
        ];
    }

    /**
     * Build Dropbox API v2 Authorization and JSON Headers
     */
    public function getApiHeaders(array $extra = []): array
    {
        $headers = [
            'Authorization: Bearer ' . ($this->accessToken ?? 'mock_token'),
            'Content-Type: application/json'
        ];

        return array_merge($headers, $extra);
    }
}
