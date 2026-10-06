<?php

return [
    'GET /api/v1/modules/yandex-disk/status' => [
        'handler' => 'Module\\Crm\\YandexDisk\\Controller\\YandexDiskApiController@getStatus',
        'permission' => 'settings.view'
    ]
];
