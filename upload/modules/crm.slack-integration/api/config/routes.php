<?php
declare(strict_types=1);

use Module\Crm\SlackIntegration\Controller\SlackController;

return [
    // Connections
    ['methods' => ['GET'], 'route' => '/connections', 'controller' => SlackController::class, 'action' => 'listConnections', 'auth' => true, 'required_permissions' => ['module.slack-integration.view']],
    ['methods' => ['POST'], 'route' => '/connections', 'controller' => SlackController::class, 'action' => 'createConnection', 'auth' => true, 'required_permissions' => ['module.slack-integration.manage', 'module.slack-integration.secret_manage']],
    ['methods' => ['GET'], 'route' => '/connections/{public_id}', 'controller' => SlackController::class, 'action' => 'getConnection', 'auth' => true, 'required_permissions' => ['module.slack-integration.view']],
    ['methods' => ['PATCH'], 'route' => '/connections/{public_id}', 'controller' => SlackController::class, 'action' => 'updateConnection', 'auth' => true, 'required_permissions' => ['module.slack-integration.manage']],
    ['methods' => ['DELETE'], 'route' => '/connections/{public_id}', 'controller' => SlackController::class, 'action' => 'deleteConnection', 'auth' => true, 'required_permissions' => ['module.slack-integration.manage']],
    ['methods' => ['POST'], 'route' => '/connections/{public_id}/test', 'controller' => SlackController::class, 'action' => 'testConnection', 'auth' => true, 'required_permissions' => ['module.slack-integration.manage']],

    // Rules
    ['methods' => ['GET'], 'route' => '/rules', 'controller' => SlackController::class, 'action' => 'listRules', 'auth' => true, 'required_permissions' => ['module.slack-integration.view']],
    ['methods' => ['POST'], 'route' => '/rules', 'controller' => SlackController::class, 'action' => 'createRule', 'auth' => true, 'required_permissions' => ['module.slack-integration.manage']],
    ['methods' => ['DELETE'], 'route' => '/rules/{public_id}', 'controller' => SlackController::class, 'action' => 'deleteRule', 'auth' => true, 'required_permissions' => ['module.slack-integration.manage']],

    // Notify entrypoint (called by workflow call_webhook, server-side; no bearer token)
    ['methods' => ['POST'], 'route' => '/notify', 'controller' => SlackController::class, 'action' => 'notify', 'auth' => false],

    // Deliveries
    ['methods' => ['GET'], 'route' => '/deliveries', 'controller' => SlackController::class, 'action' => 'listDeliveries', 'auth' => true, 'required_permissions' => ['module.slack-integration.view']],
];
