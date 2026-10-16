<?php

declare(strict_types=1);

namespace Module\Crm\Dropbox\Controller;

class DropboxApiController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getStatus(): array
    {
        return [
            'status' => 'not_configured',
            'connected' => false,
            'api_version' => 'v2',
            'features' => [
                'project_folder_hierarchy' => true,
                'shared_links' => true,
                'delta_cursor_sync' => true
            ]
        ];
    }
}
