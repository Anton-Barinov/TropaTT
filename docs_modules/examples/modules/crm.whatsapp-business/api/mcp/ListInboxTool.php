<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\WhatsAppBusiness\Service\WhatsAppBusinessService;

final class ListInboxTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['user']['organization_id'] ?? 1);
        $db = DatabaseConnectionPool::getConnection();
        $service = new WhatsAppBusinessService($db);
        $items = $service->listConversations($orgId);

        return [
            'conversations' => $items,
            'count' => count($items),
        ];
    }
}
