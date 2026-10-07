<?php
declare(strict_types=1);

use Module\Crm\Zoom\Api\Controller\ZoomApiController;

return [
    ['methods' => ['GET'], 'route' => '/status', 'controller' => ZoomApiController::class, 'action' => 'getStatus', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/meetings', 'controller' => ZoomApiController::class, 'action' => 'createMeeting', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/meetings', 'controller' => ZoomApiController::class, 'action' => 'listMeetings', 'auth' => true],
];
