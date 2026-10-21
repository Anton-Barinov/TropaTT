<?php

declare(strict_types=1);

namespace Module\Crm\YandexDisk\Controller;

class YandexDiskApiController
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
            'features' => [
                'txt_proxy_bypass' => false,
                'public_links' => false,
                'auto_backup_sync' => false,
                'upload_available' => false
            ]
        ];
    }
}
