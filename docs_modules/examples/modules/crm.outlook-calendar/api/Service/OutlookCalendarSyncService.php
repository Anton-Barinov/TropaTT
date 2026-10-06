<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Service;

class OutlookCalendarSyncService
{
    private \PDO $db;
    private int $workspaceId;

    public function __construct(\PDO $db, int $workspaceId = 1)
    {
        $this->db = $db;
        $this->workspaceId = $workspaceId;
    }

    /**
     * Map Microsoft Graph event object to CRM Calendar Event
     */
    public function mapGraphEventToCrm(array $graphEvent): array
    {
        $subject = trim((string)($graphEvent['subject'] ?? 'Без названия'));
        $body = (string)($graphEvent['body']['content'] ?? '');
        $startStr = (string)($graphEvent['start']['dateTime'] ?? '');
        $endStr = (string)($graphEvent['end']['dateTime'] ?? '');
        $timeZone = (string)($graphEvent['start']['timeZone'] ?? 'UTC');
        $isAllDay = (bool)($graphEvent['isAllDay'] ?? false);
        $isCancelled = (bool)($graphEvent['isCancelled'] ?? false);
        $outlookId = (string)($graphEvent['id'] ?? '');

        $attendees = [];
        if (!empty($graphEvent['attendees']) && is_array($graphEvent['attendees'])) {
            foreach ($graphEvent['attendees'] as $att) {
                $email = $att['emailAddress']['address'] ?? '';
                if ($email !== '') {
                    $attendees[] = [
                        'email' => $email,
                        'name' => $att['emailAddress']['name'] ?? '',
                        'status' => $att['status']['response'] ?? 'none'
                    ];
                }
            }
        }

        return [
            'outlook_id' => $outlookId,
            'title' => $subject,
            'description' => strip_tags($body),
            'start_at' => substr($startStr, 0, 19),
            'end_at' => substr($endStr, 0, 19),
            'timezone' => $timeZone,
            'is_all_day' => $isAllDay,
            'is_cancelled' => $isCancelled,
            'attendees' => $attendees,
            'tags' => ['outlook_calendar']
        ];
    }

    /**
     * Ingest delta batch from Microsoft Graph
     */
    public function syncDeltaBatch(int $connectionId, array $events): array
    {
        $imported = 0;
        $updated = 0;
        $cancelled = 0;

        $checkStmt = $this->db->prepare("
            SELECT id, crm_event_id FROM module_outlook_calendar_events_map
            WHERE workspace_id = :ws_id AND connection_id = :conn_id AND outlook_event_id = :hs_id
            LIMIT 1
        ");

        $insertStmt = $this->db->prepare("
            INSERT INTO module_outlook_calendar_events_map
            (workspace_id, connection_id, crm_event_id, outlook_event_id, last_etag, last_sync_direction, created_at)
            VALUES (:ws_id, :conn_id, :crm_id, :hs_id, :etag, 'pull', CURRENT_TIMESTAMP)
        ");

        $updateStmt = $this->db->prepare("
            UPDATE module_outlook_calendar_events_map
            SET last_etag = :etag, last_sync_direction = 'pull', updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");

        foreach ($events as $event) {
            $outlookId = (string)($event['id'] ?? '');
            if ($outlookId === '') {
                continue;
            }

            $mapped = $this->mapGraphEventToCrm($event);
            $etag = (string)($event['@odata.etag'] ?? '');

            $checkStmt->execute([
                'ws_id' => $this->workspaceId,
                'conn_id' => $connectionId,
                'hs_id' => $outlookId
            ]);
            $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);

            if ($mapped['is_cancelled']) {
                $cancelled++;
                continue;
            }

            if ($existing) {
                $updateStmt->execute([
                    'etag' => $etag,
                    'id' => $existing['id']
                ]);
                $updated++;
            } else {
                $crmEventId = 'evt_ms_' . substr(md5($outlookId), 0, 16);
                $insertStmt->execute([
                    'ws_id' => $this->workspaceId,
                    'conn_id' => $connectionId,
                    'crm_id' => $crmEventId,
                    'hs_id' => $outlookId,
                    'etag' => $etag
                ]);
                $imported++;
            }
        }

        return [
            'total_received' => count($events),
            'imported' => $imported,
            'updated' => $updated,
            'cancelled' => $cancelled
        ];
    }
}
