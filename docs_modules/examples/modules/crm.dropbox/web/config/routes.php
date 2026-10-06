<?php

return [
    'GET /dropbox' => [
        'handler' => 'Module\\Crm\\Dropbox\\Web\\DropboxWebController@index',
        'permission' => 'settings.edit'
    ]
];
