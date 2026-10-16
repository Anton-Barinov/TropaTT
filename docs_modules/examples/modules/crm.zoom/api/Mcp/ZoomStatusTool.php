<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Mcp;

class ZoomStatusTool
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function handle(array $params): array
    {
        return [
            'status' => 'not_configured',
            'provider' => 'Zoom Video Communications',
            'features' => [
                'meetings' => 'enabled',
                'recordings_sync' => 'enabled',
                'calendar_sync' => 'enabled',
                'webhooks' => 'enabled'
            ],
            'configured' => false
        ];
    }
}
