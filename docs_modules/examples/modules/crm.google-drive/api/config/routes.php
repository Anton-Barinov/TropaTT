<?php

return [
    'GET /api/v1/modules/google-drive/status' => [
        'handler' => 'Module\\Crm\\GoogleDrive\\Controller\\GoogleDriveApiController@getStatus',
        'permission' => 'settings.view'
    ]
];
