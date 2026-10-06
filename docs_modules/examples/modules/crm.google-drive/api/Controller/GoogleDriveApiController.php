<?php

declare(strict_types=1);

namespace Module\Crm\GoogleDrive\Controller;

class GoogleDriveApiController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getStatus(): array
    {
        return [
            'status' => 'active',
            'api' => 'Google Drive API v3',
            'features' => [
                'folder_structure' => true,
                'docs_integration' => true,
                'permissions_management' => true
            ]
        ];
    }
}
