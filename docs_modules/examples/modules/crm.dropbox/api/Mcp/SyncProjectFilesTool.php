<?php

declare(strict_types=1);

namespace Module\Crm\Dropbox\Mcp;

use Module\Crm\Dropbox\Service\DropboxStorageService;

class SyncProjectFilesTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        if (!$db instanceof \PDO) {
            return ['success' => false, 'error' => 'Database unavailable'];
        }

        $service = new DropboxStorageService($db, (int)($context['workspace_id'] ?? 1));
        $fileId = (string)($args['file_public_id'] ?? '');
        $projectId = (string)($args['project_public_id'] ?? 'prj_default');

        $resolved = $service->resolveProjectPath($projectId, "doc_{$fileId}.pdf");
        $sharedLink = $service->buildSharedLink($resolved['path_display']);

        $saved = $service->recordSyncedFile([
            'crm_file_public_id' => $fileId,
            'crm_project_public_id' => $projectId,
            'dropbox_file_id' => 'id:dbx_' . substr(md5($fileId), 0, 10),
            'dropbox_path_display' => $resolved['path_display'],
            'file_size_bytes' => 1024 * 1024,
            'shared_link_url' => $sharedLink
        ]);

        return [
            'success' => true,
            'file_public_id' => $fileId,
            'project_public_id' => $projectId,
            'dropbox_path' => $saved['dropbox_path'],
            'shared_link' => $saved['shared_link_url']
        ];
    }
}
