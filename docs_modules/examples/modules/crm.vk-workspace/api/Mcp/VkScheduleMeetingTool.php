<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Mcp;

use Module\Crm\VkWorkspace\Service\VkWorkspaceService;

class VkScheduleMeetingTool
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function handle(array $params): array
    {
        $service = new VkWorkspaceService($this->db);
        $result = $service->scheduleMeeting($params);

        return [
            'success' => true,
            'meeting_public_id' => $result['public_id'],
            'title' => $result['title'],
            'starts_at' => $result['starts_at'],
            'ends_at' => $result['ends_at'],
            'call_room_url' => $result['call_room_url'],
            'call_pin' => $result['call_pin'],
            'crm_calendar_event_public_id' => $result['crm_calendar_event_public_id'],
            'message' => 'Встреча успешно запланирована, создана комната VK Звонков и добавлена в календарь CRM'
        ];
    }
}
