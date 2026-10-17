<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Mcp;

class MigrationStatusTool
{
    public function execute(array $args, array $context): array
    {
        return [
            'status' => 'not_implemented',
            'active_sessions' => 0,
            'supported_entities' => [],
            'ready' => false,
            'message' => 'The migration workflow is not available yet. No data can be imported.'
        ];
    }
}
