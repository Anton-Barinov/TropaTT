<?php
declare(strict_types=1);

use Module\Crm\VkWorkspace\Api\Controller\VkWorkspaceApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => VkWorkspaceApiController::class, 'action' => 'getStatus', 'auth' => true, 'required_permissions' => ['calendar.view']],
    ['methods' => ['GET'], 'route' => '/meetings', 'controller' => VkWorkspaceApiController::class, 'action' => 'listMeetings', 'auth' => true, 'required_permissions' => ['calendar.view']],
];
