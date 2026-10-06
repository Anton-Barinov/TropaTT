<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\ClientPortal\Service\ClientPortalService;

final class ListRequestsTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['user']['organization_id'] ?? 1);
        $clientPublicId = !empty($arguments['client_public_id']) ? (string)$arguments['client_public_id'] : null;

        $db = DatabaseConnectionPool::getConnection();
        $service = new ClientPortalService($db);
        $items = $service->listRequests($orgId, $clientPublicId);

        return [
            'requests' => $items,
            'count' => count($items),
        ];
    }
}
