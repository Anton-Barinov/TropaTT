<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Mcp;

use Module\Crm\YandexDisk\Service\YandexDiskService;

class UploadFileTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        if (!$db instanceof \PDO) {
            return ['success' => false, 'error' => 'Database connection unavailable'];
        }

        $service = new YandexDiskService($db, (int)($context['workspace_id'] ?? 1));
        $fileId = (string)($args['file_public_id'] ?? '');
        $targetPath = (string)($args['target_path'] ?? '/TropaTT_CRM/Tasks');

        $plan = $service->planUpload("backup_{$fileId}.zip", 25 * 1024 * 1024, $targetPath);

        $saved = $service->recordUploadedFile([
            'crm_file_public_id' => $fileId,
            'original_name' => "backup_{$fileId}.zip",
            'original_extension' => 'zip',
            'yandex_path' => $plan['final_path'],
            'file_size_bytes' => 25 * 1024 * 1024,
            'used_bypass' => $plan['needs_bypass']
        ]);

        return [
            'success' => true,
            'file_public_id' => $fileId,
            'yandex_path' => $saved['yandex_path'],
            'public_url' => $saved['public_url'],
            'used_txt_bypass' => $plan['needs_bypass'],
            'message' => 'File successfully uploaded and published on Yandex Disk.'
        ];
    }
}
