<?php
declare(strict_types=1);

use Module\Crm\GithubIntegration\Controller\GitHubController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => GitHubController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.github-integration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => GitHubController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.github-integration.manage', 'module.github-integration.secret_manage']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => GitHubController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.github-integration.manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => GitHubController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.github-integration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => GitHubController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.github-integration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/discover', 'controller' => GitHubController::class, 'action' => 'discover', 'auth' => true, 'required_permissions' => ['module.github-integration.manage']],

    // Repo links
    ['methods' => ['GET'], 'route' => '/links', 'controller' => GitHubController::class, 'action' => 'listLinks', 'auth' => true, 'required_permissions' => ['module.github-integration.view']],
    ['methods' => ['POST'], 'route' => '/links', 'controller' => GitHubController::class, 'action' => 'createLink', 'auth' => true, 'required_permissions' => ['module.github-integration.manage', 'project.manage', 'task.manage']],
    ['methods' => ['DELETE'], 'route' => '/links/{public_id}', 'controller' => GitHubController::class, 'action' => 'deleteLink', 'auth' => true, 'required_permissions' => ['module.github-integration.manage']],
    ['methods' => ['POST'], 'route' => '/links/{public_id}/sync', 'controller' => GitHubController::class, 'action' => 'syncNow', 'auth' => true, 'required_permissions' => ['module.github-integration.run', 'project.manage', 'task.manage']],
    ['methods' => ['GET'], 'route' => '/links/{public_id}/logs', 'controller' => GitHubController::class, 'action' => 'listLinkLogs', 'auth' => true, 'required_permissions' => ['module.github-integration.view']],

    // Incoming webhook (public; HMAC-verified inside the action)
    ['methods' => ['POST'], 'route' => '/webhook/{public_id}', 'controller' => GitHubController::class, 'action' => 'webhook', 'auth' => false],
];
