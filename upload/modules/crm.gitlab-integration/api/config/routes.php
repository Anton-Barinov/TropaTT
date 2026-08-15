<?php
declare(strict_types=1);

use Module\Crm\GitlabIntegration\Controller\GitLabController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => GitLabController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => GitLabController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage', 'module.gitlab-integration.secret_manage']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => GitLabController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => GitLabController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => GitLabController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => GitLabController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage']],

    // Project links
    ['methods' => ['GET'], 'route' => '/links', 'controller' => GitLabController::class, 'action' => 'listLinks', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.view']],
    ['methods' => ['POST'], 'route' => '/links', 'controller' => GitLabController::class, 'action' => 'createLink', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage', 'project.manage', 'task.manage']],
    ['methods' => ['DELETE'], 'route' => '/links/{public_id}', 'controller' => GitLabController::class, 'action' => 'deleteLink', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.manage']],
    ['methods' => ['POST'], 'route' => '/links/{public_id}/sync', 'controller' => GitLabController::class, 'action' => 'syncNow', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.run', 'project.manage', 'task.manage']],
    ['methods' => ['GET'], 'route' => '/links/{public_id}/logs', 'controller' => GitLabController::class, 'action' => 'listLinkLogs', 'auth' => true, 'required_permissions' => ['module.gitlab-integration.view']],

    // Incoming webhook (public; token-verified inside the action)
    ['methods' => ['POST'], 'route' => '/webhook/{public_id}', 'controller' => GitLabController::class, 'action' => 'webhook', 'auth' => false],
];
