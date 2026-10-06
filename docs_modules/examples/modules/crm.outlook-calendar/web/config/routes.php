<?php

return [
    'GET /outlook-calendar' => [
        'handler' => 'Module\\Crm\\OutlookCalendar\\Web\\OutlookCalendarWebController@index',
        'permission' => 'calendar.view'
    ]
];
