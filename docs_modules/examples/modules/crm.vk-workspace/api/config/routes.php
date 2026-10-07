<?php
declare(strict_types=1);

use Module\Crm\VkWorkspace\Api\Controller\VkWorkspaceApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => VkWorkspaceApiController::class, 'action' => 'getStatus', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/meetings', 'controller' => VkWorkspaceApiController::class, 'action' => 'scheduleMeeting', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/meetings', 'controller' => VkWorkspaceApiController::class, 'action' => 'listMeetings', 'auth' => true],
];
