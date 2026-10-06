<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Mcp;

class MigrationStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'status' => 'idle',
            'active_sessions' => 0,
            'supported_entities' => ['contacts', 'companies', 'deals', 'notes'],
            'ready' => true,
            'message' => 'HubSpot migration pipeline is ready for data ingest.'
        ];
    }
}
