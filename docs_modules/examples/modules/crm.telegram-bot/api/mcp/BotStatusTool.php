<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\TelegramBot\Service\TelegramBotService;

final class BotStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        $orgId = $context->organizationId;
        if ($orgId <= 0) {
            throw new \RuntimeException('An active workspace is required.');
        }
        $db = DatabaseConnectionPool::getConnection();
        $service = new TelegramBotService($db);

        return $service->getStatus($orgId);
    }
}
