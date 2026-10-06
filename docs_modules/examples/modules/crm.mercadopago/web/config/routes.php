<?php

return [
    'GET /mercadopago' => [
        'handler' => 'Module\\Crm\\MercadoPago\\Web\\MercadoPagoWebController@index',
        'permission' => 'finance.view'
    ]
];
