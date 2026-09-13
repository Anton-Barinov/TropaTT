<?php
declare(strict_types=1);

use Module\Crm\EcommerceGateway\Controller\EcommerceGatewayPageController;

return [
    'module-ecommerce-gateway' => [EcommerceGatewayPageController::class, 'index'],
];
