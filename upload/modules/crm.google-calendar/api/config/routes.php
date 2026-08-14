<?php
declare(strict_types=1);

use Module\Crm\GoogleCalendar\Controller\GoogleCalendarController;

return [
 ['methods'=>['POST'],'route'=>'/oauth/start','controller'=>GoogleCalendarController::class,'action'=>'oauthStart','auth'=>true,'required_permissions'=>['module.google-calendar.manage']],
 ['methods'=>['GET'],'route'=>'/oauth/callback','controller'=>GoogleCalendarController::class,'action'=>'oauthCallback','auth'=>true,'required_permissions'=>['module.google-calendar.manage']],
 ['methods'=>['GET'],'route'=>'/connections','controller'=>GoogleCalendarController::class,'action'=>'connections','auth'=>true,'required_permissions'=>['module.google-calendar.view']],
 ['methods'=>['DELETE'],'route'=>'/connections/{public_id}','controller'=>GoogleCalendarController::class,'action'=>'disconnect','auth'=>true,'required_permissions'=>['module.google-calendar.manage']],
 ['methods'=>['POST'],'route'=>'/connections/{public_id}/test','controller'=>GoogleCalendarController::class,'action'=>'test','auth'=>true,'required_permissions'=>['module.google-calendar.manage']],
 ['methods'=>['POST'],'route'=>'/connections/{public_id}/sync','controller'=>GoogleCalendarController::class,'action'=>'sync','auth'=>true,'required_permissions'=>['module.google-calendar.sync']],
 ['methods'=>['PATCH'],'route'=>'/calendars/{public_id}','controller'=>GoogleCalendarController::class,'action'=>'updateCalendar','auth'=>true,'required_permissions'=>['module.google-calendar.manage']],
];
