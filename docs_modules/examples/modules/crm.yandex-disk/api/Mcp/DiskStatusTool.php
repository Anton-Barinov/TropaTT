<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Mcp;

class DiskStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'provider' => 'Yandex Disk',
            'status' => 'not_configured',
            'root_folder' => '/TropaTT_CRM',
            'bypass_mode_enabled' => false,
            'message' => 'Yandex Disk credentials are not configured; storage operations are unavailable.'
        ];
    }
}
