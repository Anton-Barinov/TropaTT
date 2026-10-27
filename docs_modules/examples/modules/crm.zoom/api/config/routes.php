<?php
declare(strict_types=1);

use Module\Crm\Zoom\Api\Controller\ZoomApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => ZoomApiController::class, 'action' => 'getStatus', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/settings', 'controller' => ZoomApiController::class, 'action' => 'saveSettings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-connection', 'controller' => ZoomApiController::class, 'action' => 'testConnection', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/meetings', 'controller' => ZoomApiController::class, 'action' => 'createMeeting', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.edit']],
    ['methods' => ['GET'], 'route' => '/meetings', 'controller' => ZoomApiController::class, 'action' => 'listMeetings', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['calendar.view']],
];
