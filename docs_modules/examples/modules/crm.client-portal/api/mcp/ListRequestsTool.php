<?php
declare(strict_types=1);

namespace Module\Crm\ClientPortal\Mcp;

use Api\System\Library\Database\DatabaseConnectionPool;
use Api\System\Library\Container;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\ClientPortal\Service\ClientPortalService;

final class ListRequestsTool
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        $auth = $container->has('auth_user') ? $container->get('auth_user') : null;
        $user = is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : null;
        if ($user === null || !$container->has('service.authz')) {
            return ['error' => 'FORBIDDEN'];
        }

        if (!$container->get('service.authz')->hasPermissions($user, ['client.manage'])) {
            return ['error' => 'FORBIDDEN'];
        }

        $orgId = $context->organizationId;
        if ($orgId <= 0) {
            return ['error' => 'ACTIVE_WORKSPACE_REQUIRED'];
        }

        $clientPublicId = !empty($arguments['client_public_id']) ? (string)$arguments['client_public_id'] : null;

        $db = DatabaseConnectionPool::getConnection();
        $service = new ClientPortalService($db);
        $items = $service->listRequests($orgId, $clientPublicId, $clientPublicId === null);

        return [
            'requests' => $items,
            'count' => count($items),
        ];
    }
}
