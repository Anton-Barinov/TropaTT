<?php
declare(strict_types=1);

use Module\Crm\OutlookCalendar\Controller\OutlookCalendarApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => OutlookCalendarApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.view']],
    ['methods' => ['POST'], 'route' => '/settings', 'controller' => OutlookCalendarApiController::class, 'action' => 'saveSettings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/oauth/start', 'controller' => OutlookCalendarApiController::class, 'action' => 'startAuthorization', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.edit']],
    ['methods' => ['GET'], 'route' => '/oauth/callback', 'controller' => OutlookCalendarApiController::class, 'action' => 'oauthCallback', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.edit']],
    ['methods' => ['POST'], 'route' => '/sync', 'controller' => OutlookCalendarApiController::class, 'action' => 'sync', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.edit']],
    ['methods' => ['DELETE'], 'route' => '/connection', 'controller' => OutlookCalendarApiController::class, 'action' => 'disconnect', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.edit']],
];
