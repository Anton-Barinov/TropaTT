<?php
declare(strict_types=1);

use Module\Crm\Mercadopago\Web\Controller\MercadoPagoWebController;

return [
    'module-mercadopago' => [MercadoPagoWebController::class, 'index'],
];
