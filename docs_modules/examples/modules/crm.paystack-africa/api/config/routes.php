<?php
declare(strict_types=1);

use Module\Crm\PaystackAfrica\Controller\PaystackAfricaApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => PaystackAfricaApiController::class, 'action' => 'getStatus', 'auth' => true],
];
