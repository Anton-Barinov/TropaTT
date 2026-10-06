<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Mcp;

class DiskStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'provider' => 'Yandex Disk',
            'status' => 'connected',
            'root_folder' => '/TropaTT_CRM',
            'bypass_mode_enabled' => true,
            'message' => 'Yandex Disk cloud storage service is active and ready.'
        ];
    }
}
