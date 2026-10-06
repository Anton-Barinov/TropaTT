<?php
declare(strict_types=1);

namespace Module\Crm\CorporateMail\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\CorporateMail\Service\CorporateMailService;

final class ListMailboxesTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new CorporateMailService($db);
        $items = $service->listMailboxes($orgId);

        return [
            'mailboxes' => $items,
            'count' => count($items),
        ];
    }
}
