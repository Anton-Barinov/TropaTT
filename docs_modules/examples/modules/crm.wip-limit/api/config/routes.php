<?php
declare(strict_types=1);

return [
    ['methods' => ['GET'], 'route' => '/limits', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'list', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.view', 'module.wip-limit.manage']],
    ['methods' => ['GET'], 'route' => '/limits/{user_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'get', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.view', 'module.wip-limit.manage']],
    ['methods' => ['GET'], 'route' => '/limits-for-task/{task_public_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'getForTask', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.view', 'module.wip-limit.manage']],
    ['methods' => ['POST'], 'route' => '/limits', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'set', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.manage']],
    ['methods' => ['DELETE'], 'route' => '/limits/{user_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'delete', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.manage']],
    ['methods' => ['GET'], 'route' => '/summary', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'summary', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.view', 'module.wip-limit.manage']],
    ['methods' => ['GET'], 'route' => '/scopes/{scope_type}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'scopes', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.view', 'module.wip-limit.manage']],
    ['methods' => ['POST'], 'route' => '/scopes', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'setScope', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.manage']],
    ['methods' => ['DELETE'], 'route' => '/scopes/{scope_type}/{scope_public_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'deleteScope', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['module.wip-limit.manage']],
];
