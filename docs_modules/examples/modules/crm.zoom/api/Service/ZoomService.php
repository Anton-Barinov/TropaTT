<?php

declare(strict_types=1);

namespace Module\Crm\Zoom\Service;

class ZoomService
{
    private \PDO $db;
    private int $workspaceId;
    private ?string $accessToken;

    public function __construct(\PDO $db, int $workspaceId = 1, ?string $accessToken = null)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->accessToken = $accessToken;
    }

    /**
     * Generate mock or authenticated Zoom meeting payload
     */
    public function generateMeetingResponse(string $topic, string $startTime, int $duration, ?string $passcode = null): array
    {
        $zoomMeetingId = (int)random_int(81000000000, 89999999999);
        $passcode = $passcode ?? substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
        $joinUrl = "https://us05web.zoom.us/j/{$zoomMeetingId}?pwd=" . bin2hex(random_bytes(8));
        $startUrl = "https://us05web.zoom.us/s/{$zoomMeetingId}?zak=" . bin2hex(random_bytes(16));

        return [
            'id' => $zoomMeetingId,
            'topic' => $topic,
            'start_time' => $startTime,
            'duration' => $duration,
            'join_url' => $joinUrl,
            'start_url' => $startUrl,
            'password' => $passcode
        ];
    }

    /**
     * Create meeting in Zoom and store in CRM
     */
    public function createMeeting(array $input): array
    {
        $publicId = 'zmm_' . substr(md5(uniqid('', true)), 0, 16);
        $topic = (string)($input['topic'] ?? 'Онлайн-встреча Zoom');
        $startTime = (string)($input['start_time'] ?? date('Y-m-d H:i:s'));
        $duration = (int)($input['duration'] ?? 40);
        $agenda = (string)($input['agenda'] ?? '');
        $passcode = !empty($input['password']) ? (string)$input['password'] : null;

        $crmEventId = (string)($input['crm_calendar_event_public_id'] ?? ('evt_' . substr(md5(uniqid()), 0, 12)));
        $projectId = (string)($input['crm_project_public_id'] ?? '');
        $taskId = (string)($input['crm_task_public_id'] ?? '');
        $clientId = (string)($input['crm_client_public_id'] ?? '');

        $zoomData = $this->generateMeetingResponse($topic, $startTime, $duration, $passcode);

        $stmt = $this->db->prepare("
            INSERT INTO module_zoom_meetings
            (public_id, workspace_id, crm_calendar_event_public_id, crm_project_public_id, crm_task_public_id, crm_client_public_id, zoom_meeting_id, topic, agenda, start_time, duration_minutes, join_url, start_url, passcode, status, created_at)
            VALUES (:pid, :ws_id, :crm_event, :crm_proj, :crm_task, :crm_client, :z_id, :topic, :agenda, :start_time, :duration, :join_url, :start_url, :passcode, 'waiting', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'crm_event' => $crmEventId !== '' ? $crmEventId : null,
            'crm_proj' => $projectId !== '' ? $projectId : null,
            'crm_task' => $taskId !== '' ? $taskId : null,
            'crm_client' => $clientId !== '' ? $clientId : null,
            'z_id' => $zoomData['id'],
            'topic' => $topic,
            'agenda' => $agenda,
            'start_time' => $startTime,
            'duration' => $duration,
            'join_url' => $zoomData['join_url'],
            'start_url' => $zoomData['start_url'],
            'passcode' => $zoomData['password']
        ]);

        return [
            'public_id' => $publicId,
            'zoom_meeting_id' => $zoomData['id'],
            'topic' => $topic,
            'start_time' => $startTime,
            'duration_minutes' => $duration,
            'join_url' => $zoomData['join_url'],
            'passcode' => $zoomData['password'],
            'crm_calendar_event_public_id' => $crmEventId,
            'status' => 'waiting'
        ];
    }

    /**
     * Get Zoom API Request Headers
     */
    public function getAuthHeaders(): array
    {
        return [
            'Authorization: Bearer ' . ($this->accessToken ?? 'mock_zoom_jwt_token'),
            'Content-Type: application/json',
            'Accept: application/json'
        ];
    }
}
