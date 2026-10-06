<?php

return [
    'GET /api/v1/modules/paystack-africa/status' => [
        'handler' => 'Module\\Crm\\PaystackAfrica\\Controller\\PaystackAfricaApiController@getStatus',
        'permission' => 'finance.view'
    ]
];
