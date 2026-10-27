<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Mcp;

use Module\Crm\OutlookCalendar\Service\OutlookCalendarSyncService;
use PDO;
use RuntimeException;

final class CalendarStatusTool
{
    public function execute(array $args, array $context): array
    {
        $workspaceId = (int)($context['organization_id'] ?? 0);
        $actorPublicId = (string)($context['actor_public_id'] ?? '');
        $db = $context['db'] ?? null;
        if ($workspaceId <= 0 || $actorPublicId === '' || !($db instanceof PDO)) {
            throw new RuntimeException('An authorized Outlook workspace and actor are required.');
        }
        $userId = $this->resolveUserId($db, $actorPublicId);
        $status = (new OutlookCalendarSyncService($db, $workspaceId))->status($userId);

        return ['ok' => true, 'status' => $status];
    }

    private function resolveUserId(PDO $db, string $actorPublicId): int
    {
        $stmt = $db->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute([':public_id' => $actorPublicId]);
        $userId = (int)$stmt->fetchColumn();
        if ($userId <= 0) {
            throw new RuntimeException('The current CRM user could not be resolved.');
        }
        // The caller supplies an already-authorized active workspace. The service
        // then queries connection rows with both that workspace and this actor ID.
        return $userId;
    }
}
