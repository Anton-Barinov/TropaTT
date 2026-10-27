<?php
declare(strict_types=1);

namespace Module\Crm\PublicForms\Mcp;

use Api\System\Library\Container;
use Api\System\Library\Module\Mcp\ModuleMcpToolInterface;
use Api\System\Library\Module\ModuleExecutionContext;
use Module\Crm\PublicForms\Service\PublicFormsService;

final class ListFormsTool implements ModuleMcpToolInterface
{
    public function execute(array $arguments, ModuleExecutionContext $context, Container $container): array
    {
        $auth = $container->has('auth_user') ? $container->get('auth_user') : null;
        $user = is_array($auth) && is_array($auth['user'] ?? null) ? $auth['user'] : null;
        if ($user === null || $context->organizationId < 1 || !$container->has('db.pdo')
            || (empty($user['is_root']) && (!$container->has('service.authz')
                || !$container->get('service.authz')->hasPermissions($user, ['settings.view'])))) {
            return ['error' => 'FORBIDDEN'];
        }
        $items = (new PublicFormsService($container->get('db.pdo')))->listForms($context->organizationId);

        return [
            'forms' => $items,
            'count' => count($items),
        ];
    }
}
