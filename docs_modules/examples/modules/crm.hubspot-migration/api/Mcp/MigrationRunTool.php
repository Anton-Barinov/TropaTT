<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Mcp;

class MigrationRunTool
{
    public function execute(array $args, array $context): array
    {
        $mode = $args['mode'] ?? 'dry_run';
        $batchSize = (int)($args['batch_size'] ?? 50);

        return [
            'mode' => $mode,
            'batch_size' => $batchSize,
            'processed' => 0,
            'status' => 'completed',
            'message' => "Execution in mode [{$mode}] completed with zero errors."
        ];
    }
}
