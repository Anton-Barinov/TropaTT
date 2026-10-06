<?php

return [
    'GET /api/v1/modules/dropbox/status' => [
        'handler' => 'Module\\Crm\\Dropbox\\Controller\\DropboxApiController@getStatus',
        'permission' => 'settings.view'
    ]
];
