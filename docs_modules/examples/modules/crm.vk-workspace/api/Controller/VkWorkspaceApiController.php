<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Api\Controller;

use Module\Crm\VkWorkspace\Service\VkWorkspaceService;

class VkWorkspaceApiController
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
            'connected' => true,
            'features' => [
                'vk_calls' => true,
                'calendar_sync' => true,
                'vk_teams' => true
            ]
        ];
    }

    public function scheduleMeeting(array $input): array
    {
        $service = new VkWorkspaceService($this->db);
        return $service->scheduleMeeting($input);
    }

    public function listMeetings(): array
    {
        $stmt = $this->db->query("
            SELECT public_id, title, description, starts_at, ends_at, call_room_url, call_pin, status, created_at
            FROM module_vk_workspace_meetings
            ORDER BY starts_at DESC
            LIMIT 50
        ");

        return [
            'items' => $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : []
        ];
    }
}
