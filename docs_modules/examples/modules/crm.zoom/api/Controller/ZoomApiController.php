<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Api\Controller;

use Module\Crm\Zoom\Service\ZoomService;

class ZoomApiController
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
                'zoom_meetings' => true,
                'cloud_recordings' => true,
                'calendar_sync' => true
            ]
        ];
    }

    public function createMeeting(array $input): array
    {
        $service = new ZoomService($this->db);
        return $service->createMeeting($input);
    }

    public function listMeetings(): array
    {
        $stmt = $this->db->query("
            SELECT public_id, zoom_meeting_id, topic, agenda, start_time, duration_minutes, join_url, passcode, status, created_at
            FROM module_zoom_meetings
            ORDER BY start_time DESC
            LIMIT 50
        ");

        return [
            'items' => $stmt ? $stmt->fetchAll(\PDO::FETCH_ASSOC) : []
        ];
    }
}
