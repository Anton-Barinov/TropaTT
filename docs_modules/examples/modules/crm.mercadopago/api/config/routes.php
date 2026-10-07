<?php
declare(strict_types=1);

use Module\Crm\MercadoPago\Controller\MercadoPagoApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => MercadoPagoApiController::class, 'action' => 'getStatus', 'auth' => true],
];
