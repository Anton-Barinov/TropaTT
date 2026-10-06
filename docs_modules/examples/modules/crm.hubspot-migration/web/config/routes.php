<?php

return [
    'GET /hubspot-migration' => [
        'handler' => 'Module\\Crm\\HubSpotMigration\\Web\\HubSpotMigrationWebController@index',
        'permission' => 'settings.edit'
    ]
];
