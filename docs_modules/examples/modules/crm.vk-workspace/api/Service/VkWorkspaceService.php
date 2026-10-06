<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Service;

class VkWorkspaceService
{
    private \PDO $db;
    private int $workspaceId;
    private ?string $apiToken;
    private ?string $botToken;
    private string $domain;

    public function __construct(
        \PDO $db,
        int $workspaceId = 1,
        ?string $apiToken = null,
        ?string $botToken = null,
        string $domain = 'myteam.mail.ru'
    ) {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
        $this->apiToken = $apiToken;
        $this->botToken = $botToken;
        $this->domain = $domain;
    }

    /**
     * Generate VK Calls room URL and credentials
     */
    public function createCallRoom(string $title): array
    {
        $roomId = 'vkc_' . bin2hex(random_bytes(6));
        $pin = (string)random_int(100000, 999999);
        $callUrl = "https://calls.vk.com/{$roomId}?pin={$pin}";

        return [
            'room_id' => $roomId,
            'call_url' => $callUrl,
            'pin' => $pin
        ];
    }

    /**
     * Schedule a meeting: creates VK call room, saves record, formats calendar event
     */
    public function scheduleMeeting(array $data): array
    {
        $publicId = 'vkm_' . substr(md5(uniqid('', true)), 0, 16);
        $title = (string)($data['title'] ?? 'Встреча VK WorkSpace');
        $startsAt = (string)($data['starts_at'] ?? date('Y-m-d H:i:s'));
        $durationMinutes = (int)($data['duration_minutes'] ?? 30);
        $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + ($durationMinutes * 60));
        $description = (string)($data['description'] ?? '');
        $crmEventId = (string)($data['crm_calendar_event_public_id'] ?? ('evt_' . substr(md5(uniqid()), 0, 12)));
        $projectId = (string)($data['crm_project_public_id'] ?? '');
        $taskId = (string)($data['crm_task_public_id'] ?? '');
        $clientId = (string)($data['crm_client_public_id'] ?? '');

        $room = $this->createCallRoom($title);

        $stmt = $this->db->prepare("
            INSERT INTO module_vk_workspace_meetings
            (public_id, workspace_id, crm_calendar_event_public_id, crm_project_public_id, crm_task_public_id, crm_client_public_id, title, description, starts_at, ends_at, call_room_url, call_room_id, call_pin, status, created_at)
            VALUES (:pid, :ws_id, :crm_event, :crm_proj, :crm_task, :crm_client, :title, :desc, :starts, :ends, :url, :room_id, :pin, 'scheduled', CURRENT_TIMESTAMP)
        ");

        $stmt->execute([
            'pid' => $publicId,
            'ws_id' => $this->workspaceId,
            'crm_event' => $crmEventId !== '' ? $crmEventId : null,
            'crm_proj' => $projectId !== '' ? $projectId : null,
            'crm_task' => $taskId !== '' ? $taskId : null,
            'crm_client' => $clientId !== '' ? $clientId : null,
            'title' => $title,
            'desc' => $description,
            'starts' => $startsAt,
            'ends' => $endsAt,
            'url' => $room['call_url'],
            'room_id' => $room['room_id'],
            'pin' => $room['pin']
        ]);

        $meetingId = (int)$this->db->lastInsertId();

        $participants = (array)($data['participants'] ?? []);
        $addedParticipants = [];
        if (!empty($participants)) {
            $pStmt = $this->db->prepare("
                INSERT INTO module_vk_workspace_participants
                (meeting_id, email, rsvp_status, created_at)
                VALUES (:mid, :email, 'needs_action', CURRENT_TIMESTAMP)
            ");
            foreach ($participants as $pEmail) {
                $email = trim((string)$pEmail);
                if ($email !== '') {
                    $pStmt->execute(['mid' => $meetingId, 'email' => $email]);
                    $addedParticipants[] = $email;
                }
            }
        }

        return [
            'public_id' => $publicId,
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'call_room_url' => $room['call_url'],
            'call_pin' => $room['pin'],
            'crm_calendar_event_public_id' => $crmEventId,
            'participants_count' => count($addedParticipants),
            'status' => 'scheduled'
        ];
    }

    /**
     * Format VK Teams notification payload
     */
    public function formatTeamsNotification(string $title, string $startsAt, string $callUrl): array
    {
        $message = "📅 **Запланирована встреча**: {$title}\n"
                 . "⏰ **Время начала**: {$startsAt}\n"
                 . "📞 **Ссылка на звонок**: {$callUrl}";

        return [
            'text' => $message,
            'parse_mode' => 'MarkdownV2',
            'inline_keyboard' => [
                [
                    ['text' => 'Присоединиться к VK Звонку', 'url' => $callUrl]
                ]
            ]
        ];
    }

    /**
     * Get VK Teams API request headers
     */
    public function getBotHeaders(): array
    {
        return [
            'Authorization: Bearer ' . ($this->botToken ?? 'mock_bot_token'),
            'Content-Type: application/json'
        ];
    }
}
