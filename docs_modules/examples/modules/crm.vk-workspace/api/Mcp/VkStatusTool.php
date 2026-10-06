<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Mcp;

class VkStatusTool
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function handle(array $params): array
    {
        return [
            'status' => 'active',
            'provider' => 'VK WorkSpace (VK Звонки / Календарь / Teams)',
            'features' => [
                'vk_calls' => 'enabled',
                'calendar_sync' => 'enabled',
                'vk_teams_notifications' => 'enabled',
                'mail_invites' => 'enabled'
            ],
            'configured' => true
        ];
    }
}
