<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Mcp;

final class MigrationStatusTool
{
    public function execute(array $args, array $context): array
    {
        $db = $context['db'] ?? null;
        $workspaceId = (int)($context['workspace_id'] ?? 0);
        if (!$db instanceof \PDO || $workspaceId < 1) return ['success' => false, 'error' => 'Authorized workspace context is required.'];
        try {
            $stmt = $db->prepare('SELECT status, COUNT(*) AS count FROM module_hubspot_migration_sessions WHERE workspace_id=:workspace GROUP BY status');
            $stmt->execute(['workspace' => $workspaceId]);
            return ['ready' => true, 'source' => 'pasted HubSpot Contacts JSON export', 'supported_objects' => ['contacts'], 'unsupported_objects' => ['companies', 'deals', 'activities', 'associations'], 'sessions' => $stmt->fetchAll(\PDO::FETCH_ASSOC)];
        } catch (\Throwable) { return ['success' => false, 'error' => 'Migration status is unavailable for this workspace.']; }
    }
}
