<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use PDO;

final class BotStatusTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        $orgId = $context->organizationId;
        if ($orgId <= 0) {
            throw new \RuntimeException('An active workspace is required.');
        }
        $db = DatabaseConnectionPool::getConnection();

        $stmt = $db->prepare(
            "SELECT public_id, is_active, created_at, updated_at
             FROM crm_telegram_configs
             WHERE organization_id = :org_id LIMIT 1"
        );
        $stmt->execute([':org_id' => $orgId]);
        $cfg = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'configured' => (bool)$cfg,
            'is_active' => !empty($cfg['is_active']),
            'public_id' => $cfg['public_id'] ?? null,
        ];
    }
}
