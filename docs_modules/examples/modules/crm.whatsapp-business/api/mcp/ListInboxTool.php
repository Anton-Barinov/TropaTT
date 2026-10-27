<?php
declare(strict_types=1);

namespace Module\Crm\WhatsAppBusiness\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\WhatsAppBusiness\Service\WhatsAppBusinessService;

final class ListInboxTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['organization_id'] ?? $context['user']['organization_id'] ?? 0);
        if ($orgId <= 0) {
            throw new \RuntimeException('An active workspace is required to list WhatsApp conversations.');
        }
        $db = DatabaseConnectionPool::getConnection();
        $service = new WhatsAppBusinessService($db);
        $items = $service->listConversations($orgId);

        return [
            'conversations' => $items,
            'count' => count($items),
        ];
    }
}
