<?php
declare(strict_types=1);

namespace Module\Crm\FixtureConnector\Job;

use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Module\WorkspaceModuleJobInterface;

final class SyncIntakeJob implements WorkspaceModuleJobInterface
{
    /**
     * Process asynchronous background intake synchronization.
     *
     * @param array<string, mixed> $payload
     * @param ModuleExecutionContext $context
     * @param Container $container
     */
    public function handle(array $payload, ModuleExecutionContext $context, Container $container): void
    {
        $orgId = $context->organizationId;
        if ($orgId <= 0) {
            throw new \RuntimeException("SyncIntakeJob requires an authorized organization context.");
        }

        /** @var \Api\System\Library\Db $db */
        $db = $container->get('db');

        $eventType = (string)($payload['event_type'] ?? 'intake.created');
        $idempotencyKey = (string)($payload['idempotency_key'] ?? ($context->correlationId ?: uniqid('job_', true)));

        // Guard against duplicate execution within workspace
        $existing = $db->query(
            "SELECT id FROM mod_crm_fixture_connector_log WHERE organization_id = :org_id AND idempotency_key = :idemp LIMIT 1",
            [
                'org_id' => $orgId,
                'idemp' => $idempotencyKey,
            ]
        )->row;

        if (!empty($existing)) {
            return; // Already processed idempotently
        }

        $db->query(
            "INSERT INTO mod_crm_fixture_connector_log (organization_id, idempotency_key, event_type, payload_json, status, created_at)
             VALUES (:org_id, :idemp, :event_type, :payload, 'completed', NOW())",
            [
                'org_id' => $orgId,
                'idemp' => $idempotencyKey,
                'event_type' => $eventType,
                'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]
        );
    }
}
