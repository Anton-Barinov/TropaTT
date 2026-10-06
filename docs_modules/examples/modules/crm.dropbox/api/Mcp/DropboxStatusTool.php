<?php

declare(strict_types=1);

namespace Module\Crm\Dropbox\Mcp;

class DropboxStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'provider' => 'Dropbox v2',
            'status' => 'connected',
            'base_folder' => '/TropaTT_CRM',
            'shared_links_enabled' => true,
            'message' => 'Dropbox corporate storage integration is active.'
        ];
    }
}
