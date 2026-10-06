<?php
declare(strict_types=1);

namespace Module\Crm\AutomationHub\Mcp;

use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Module\Crm\AutomationHub\Service\AutomationHubService;
use Api\System\Library\Database\DatabaseConnectionPool;

final class TestWebhookTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, array $context): array
    {
        $recipePubId = (string)($arguments['recipe_public_id'] ?? '');
        $db = DatabaseConnectionPool::getConnection();
        $service = new AutomationHubService($db);

        return $service->dispatch($recipePubId, 'mcp.ping', [
            'ping' => 'pong',
            'triggered_by' => 'MCP',
            'time' => time(),
        ]);
    }
}
