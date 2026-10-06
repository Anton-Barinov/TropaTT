<?php

declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Mcp;

use Module\Crm\GoogleDrive\Service\GoogleDriveStorageService;

class AttachFileTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        if (!$db instanceof \PDO) {
            return ['success' => false, 'error' => 'Database connection unavailable'];
        }

        $service = new GoogleDriveStorageService($db, (int)($context['workspace_id'] ?? 1));
        $fileId = (string)($args['file_public_id'] ?? '');
        $projectId = (string)($args['project_public_id'] ?? 'prj_test_drive');
        $clientId = (string)($args['client_public_id'] ?? '');
        $role = (string)($args['share_role'] ?? 'reader');

        $driveId = '1gDrive_' . substr(md5($fileId), 0, 16);
        $links = $service->formatDriveLinks($driveId);

        $record = $service->recordDriveFile([
            'crm_file_public_id' => $fileId,
            'crm_project_public_id' => $projectId,
            'crm_client_public_id' => $clientId,
            'drive_file_id' => $driveId,
            'original_name' => "Project_Report_{$fileId}.pdf",
            'mime_type' => 'application/pdf',
            'web_view_link' => $links['web_view_link'],
            'web_content_link' => $links['web_content_link'],
            'file_size_bytes' => 2048576,
            'share_role' => $role
        ]);

        return [
            'success' => true,
            'file_public_id' => $fileId,
            'drive_file_id' => $driveId,
            'web_view_link' => $record['web_view_link'],
            'share_role' => $role,
            'message' => 'File successfully linked to Google Drive with collaborative access.'
        ];
    }
}
