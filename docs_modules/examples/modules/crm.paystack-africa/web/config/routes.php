<?php

return [
    'GET /paystack-africa' => [
        'handler' => 'Module\\Crm\\PaystackAfrica\\Web\\PaystackAfricaWebController@index',
        'permission' => 'finance.view'
    ]
];
