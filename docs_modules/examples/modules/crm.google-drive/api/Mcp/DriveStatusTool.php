<?php

declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Mcp;

class DriveStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'provider' => 'Google Drive API v3',
            'status' => 'connected',
            'root_folder' => 'TropaTT CRM',
            'docs_collaboration' => true,
            'message' => 'Google Drive integration is active.'
        ];
    }
}
