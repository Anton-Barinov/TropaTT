<?php

return [
    'GET /google-drive' => [
        'handler' => 'Module\\Crm\\GoogleDrive\\Web\\GoogleDriveWebController@index',
        'permission' => 'settings.edit'
    ]
];
