<?php

return [
    'GET /api/v1/modules/mercadopago/status' => [
        'handler' => 'Module\\Crm\\MercadoPago\\Controller\\MercadoPagoApiController@getStatus',
        'permission' => 'finance.view'
    ]
];
