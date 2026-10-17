<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Controller;

use Api\Controller\Common\BaseController;
use Api\System\Library\Http\JsonResponse;
use InvalidArgumentException;
use Module\Crm\HubSpotMigration\Service\HubSpotMigrationService;

final class HubSpotMigrationApiController extends BaseController
{
    public function getStatus(): JsonResponse
    {
        return $this->success('HUBSPOT_MIGRATION_STATUS', 'Состояние модуля загружено.', [
            'status' => 'not_implemented',
            'connected' => false,
            'supported_objects' => [],
            'features' => ['dry_run' => false, 'chunked_import' => false, 'idempotency' => false, 'rollback' => false],
            'message' => 'The migration workflow is not available yet. No data can be imported.',
        ]);
    }
}
