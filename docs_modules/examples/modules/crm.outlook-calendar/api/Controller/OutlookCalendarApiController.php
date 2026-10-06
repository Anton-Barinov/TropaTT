<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Controller;

class OutlookCalendarApiController
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
            'supported_endpoints' => ['delta', 'sync', 'webhooks'],
            'version' => '1.0.0'
        ];
    }
}
