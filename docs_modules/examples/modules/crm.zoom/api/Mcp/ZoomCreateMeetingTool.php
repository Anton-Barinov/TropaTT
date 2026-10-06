<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Mcp;

use Module\Crm\Zoom\Service\ZoomService;

class ZoomCreateMeetingTool
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function handle(array $params): array
    {
        $service = new ZoomService($this->db);
        $result = $service->createMeeting($params);

        return [
            'success' => true,
            'meeting_public_id' => $result['public_id'],
            'zoom_meeting_id' => $result['zoom_meeting_id'],
            'topic' => $result['topic'],
            'start_time' => $result['start_time'],
            'duration_minutes' => $result['duration_minutes'],
            'join_url' => $result['join_url'],
            'passcode' => $result['passcode'],
            'crm_calendar_event_public_id' => $result['crm_calendar_event_public_id'],
            'message' => 'Встреча в Zoom успешно создана и отображается в календаре CRM'
        ];
    }
}
