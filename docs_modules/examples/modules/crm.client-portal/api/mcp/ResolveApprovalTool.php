<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\ClientPortal\Service\ClientPortalService;

final class ResolveApprovalTool
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        $auth = $container->has('auth_user') ? $container->get('auth_user') : null;
        $user = is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : null;
        if ($user === null || !$container->has('service.authz')
            || !$container->get('service.authz')->hasPermissions($user, ['client.manage'])) {
            return ['error' => 'FORBIDDEN'];
        }

        $orgId = $context->organizationId;
        if ($orgId <= 0) {
            return ['error' => 'ACTIVE_WORKSPACE_REQUIRED'];
        }
        $approvalId = is_string($arguments['approval_public_id'] ?? null) ? trim($arguments['approval_public_id']) : '';
        $clientId = is_string($arguments['client_public_id'] ?? null) ? trim($arguments['client_public_id']) : '';
        $decision = is_string($arguments['decision'] ?? null) ? $arguments['decision'] : '';
        $note = is_string($arguments['note'] ?? null) ? trim($arguments['note']) : null;
        if ($approvalId === '' || $clientId === '' || !in_array($decision, ['approved', 'rejected'], true)
            || ($note !== null && strlen($note) > 10000)) {
            return ['error' => 'VALIDATION_ERROR'];
        }

        $client = $container->get('db.pdo')->prepare(
            "SELECT public_id FROM counterparties
             WHERE public_id = :public_id AND organization_id = :organization_id
               AND counterparty_type IN ('individual', 'sole_proprietor', 'legal_entity')
               AND status = 'active' LIMIT 1"
        );
        $client->execute([':public_id' => $clientId, ':organization_id' => $orgId]);
        if ($client->fetchColumn() === false) {
            return ['error' => 'CLIENT_NOT_FOUND'];
        }

        $db = DatabaseConnectionPool::getConnection();
        $service = new ClientPortalService($db);
        $success = $service->resolveApproval($orgId, $approvalId, $clientId, $decision, $note);

        return [
            'success' => $success,
            'approval_public_id' => $approvalId,
            'decision' => $decision,
        ];
    }
}
