<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Mcp;

class MigrationRunTool
{
    public function execute(array $args, array $context): array
    {
        $mode = (string)($args['mode'] ?? '');
        if (!in_array($mode, ['dry_run', 'live_batch', 'rollback'], true)) {
            return ['status' => 'rejected', 'code' => 'INVALID_MODE'];
        }
        $batchSize = filter_var($args['batch_size'] ?? 50, FILTER_VALIDATE_INT);
        if ($batchSize === false || $batchSize < 1 || $batchSize > 100) {
            return ['status' => 'rejected', 'code' => 'INVALID_BATCH_SIZE'];
        }

        return [
            'mode' => $mode,
            'batch_size' => $batchSize,
            'processed' => 0,
            'status' => 'not_implemented',
            'message' => 'Migration execution is not available until the module provides a workspace-scoped import pipeline.'
        ];
    }
}
