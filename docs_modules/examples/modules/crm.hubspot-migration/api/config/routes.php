<?php

return [
    'GET /api/v1/modules/hubspot-migration/status' => [
        'handler' => 'Module\\Crm\\HubSpotMigration\\Controller\\HubSpotMigrationApiController@getStatus',
        'permission' => 'settings.view'
    ],
    'POST /api/v1/modules/hubspot-migration/stage' => [
        'handler' => 'Module\\Crm\\HubSpotMigration\\Controller\\HubSpotMigrationApiController@stageBatch',
        'permission' => 'settings.edit'
    ]
];
