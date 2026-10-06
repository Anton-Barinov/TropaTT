<?php

declare(strict_types=1);

namespace Module\Crm\HubSpotMigration\Controller;

use Module\Crm\HubSpotMigration\Service\HubSpotMigrationService;

class HubSpotMigrationApiController
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getStatus(): array
    {
        return [
            'status' => 'active',
            'supported_objects' => ['contacts', 'companies', 'deals'],
            'features' => [
                'dry_run' => true,
                'chunked_import' => true,
                'idempotency' => true,
                'rollback' => true
            ]
        ];
    }

    public function stageBatch(array $payload): array
    {
        $service = new HubSpotMigrationService($this->db);
        $sessionId = $payload['session_id'] ?? ('hs_sess_' . uniqid());
        $objectType = $payload['object_type'] ?? 'contacts';
        $records = $payload['records'] ?? [];

        return $service->stagePayload($sessionId, $objectType, $records);
    }
}
