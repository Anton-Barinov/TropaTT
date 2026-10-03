<?php
declare(strict_types=1);

use Module\Crm\FixtureConnector\Controller\FixtureConnectorController;

return [
    [
        'methods' => ['GET'],
        'route' => '/status',
        'controller' => FixtureConnectorController::class,
        'action' => 'status',
        'auth' => true,
        'workspace_required' => true,
        'required_permissions' => ['settings.view'],
    ],
    [
        'methods' => ['POST'],
        'route' => '/webhook',
        'controller' => FixtureConnectorController::class,
        'action' => 'webhook',
        'auth' => false,
        'workspace_required' => false,
        'idempotency' => false,
    ],
    [
        'methods' => ['POST'],
        'route' => '/sync',
        'controller' => FixtureConnectorController::class,
        'action' => 'sync',
        'auth' => true,
        'workspace_required' => true,
        'required_permissions' => ['settings.view'],
        'idempotency' => 'required',
    ],
];
