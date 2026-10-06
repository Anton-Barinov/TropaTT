<?php
declare(strict_types=1);

namespace Module\Crm\TelegramBot\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use PDO;

final class BotStatusTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['user']['organization_id'] ?? 1);
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
