<?php

return [
    'GET /api/v1/modules/outlook-calendar/status' => [
        'handler' => 'Module\\Crm\\OutlookCalendar\\Controller\\OutlookCalendarApiController@getStatus',
        'permission' => 'calendar.view'
    ]
];
