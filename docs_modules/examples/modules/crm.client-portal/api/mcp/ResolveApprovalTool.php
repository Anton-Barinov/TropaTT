<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Module\Crm\ClientPortal\Service\ClientPortalService;

final class ResolveApprovalTool
{
    public function handle(array $arguments, array $context): array
    {
        $orgId = (int)($context['user']['organization_id'] ?? 1);
        $approvalId = (string)($arguments['approval_public_id'] ?? '');
        $clientId = (string)($arguments['client_public_id'] ?? '');
        $decision = (string)($arguments['decision'] ?? 'approved');
        $note = !empty($arguments['note']) ? (string)$arguments['note'] : null;

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
