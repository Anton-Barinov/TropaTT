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
            'status' => 'active',
            'features' => [
                'txt_proxy_bypass' => true,
                'public_links' => true,
                'auto_backup_sync' => true
            ]
        ];
    }
}
