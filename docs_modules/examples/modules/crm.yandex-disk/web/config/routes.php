<?php

return [
    'GET /yandex-disk' => [
        'handler' => 'Module\\Crm\\YandexDisk\\Web\\YandexDiskWebController@index',
        'permission' => 'settings.edit'
    ]
];
