<?php

declare(strict_types=1);

namespace Module\Crm\OutlookCalendar\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\OutlookCalendar\Service\OutlookCalendarSyncService;
use PDO;
use RuntimeException;

final class CalendarSyncTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        if (($arguments['direction'] ?? 'pull') !== 'pull') {
            throw new RuntimeException('Only Outlook-to-CRM calendar import is supported.');
        }
        $workspaceId = $context->organizationId;
        $actorPublicId = (string)($context->actorPublicId ?? '');
        $db = $container->get('db.pdo');
        if ($workspaceId <= 0 || $actorPublicId === '' || !($db instanceof PDO)) {
            throw new RuntimeException('An authorized Outlook workspace and actor are required.');
        }
        $stmt = $db->prepare('SELECT id FROM users WHERE public_id = :public_id LIMIT 1');
        $stmt->execute([':public_id' => $actorPublicId]);
        $userId = (int)$stmt->fetchColumn();
        if ($userId <= 0) {
            throw new RuntimeException('The current CRM user could not be resolved.');
        }
        $auth = $container->has('auth_user') ? $container->get('auth_user') : [];
        $actor = is_array($auth['user'] ?? null) ? (array)$auth['user'] : [];
        if ((int)($actor['id'] ?? 0) !== $userId || (int)($actor['organization_id'] ?? 0) !== $workspaceId) {
            throw new RuntimeException('The current actor does not match the authorized workspace context.');
        }
        $calendar = $container->get('service.calendar');
        $creator = static fn(array $input, array $eventActor, array $source): array|string => $calendar->createEvent($input, $eventActor, $source);
        $afterCommit = static function (array $event, array $eventActor) use ($calendar): void { $calendar->dispatchCreatedAfterCommit($event, $eventActor); };
        $result = (new OutlookCalendarSyncService($db, $workspaceId, null, $creator, $afterCommit))->sync($userId);

        return ['ok' => true, 'provider' => 'Microsoft Graph', 'direction' => 'pull'] + $result;
    }
}
