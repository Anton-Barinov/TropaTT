<?php
declare(strict_types=1);

use Module\Crm\StarterKits\Api\Controller\StarterKitsApiController;

return [
    ['methods' => ['GET'], 'route' => '/kits', 'controller' => StarterKitsApiController::class, 'action' => 'listKits', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/preview', 'controller' => StarterKitsApiController::class, 'action' => 'preview', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/apply', 'controller' => StarterKitsApiController::class, 'action' => 'apply', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
];
