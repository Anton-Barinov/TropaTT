<?php

declare(strict_types=1);

use Module\Crm\MercadoPago\Controller\MercadoPagoApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => MercadoPagoApiController::class, 'action' => 'getStatus', 'auth' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/config', 'controller' => MercadoPagoApiController::class, 'action' => 'saveConfig', 'auth' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['GET'], 'route' => '/payments', 'controller' => MercadoPagoApiController::class, 'action' => 'listPayments', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['finance.view']],
    ['methods' => ['POST'], 'route' => '/payments', 'controller' => MercadoPagoApiController::class, 'action' => 'createPayment', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['finance.edit']],
    ['methods' => ['POST'], 'route' => '/payments/verify', 'controller' => MercadoPagoApiController::class, 'action' => 'checkPaymentStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['finance.view']],
];
