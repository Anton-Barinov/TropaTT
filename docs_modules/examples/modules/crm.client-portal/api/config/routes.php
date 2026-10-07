<?php
declare(strict_types=1);

use Module\Crm\ClientPortal\Api\Controller\ClientPortalApiController;

return [
    ['methods' => ['GET'], 'route' => '/requests', 'controller' => ClientPortalApiController::class, 'action' => 'listRequests', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/requests/{public_id}/messages', 'controller' => ClientPortalApiController::class, 'action' => 'listMessages', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/requests', 'controller' => ClientPortalApiController::class, 'action' => 'createRequest', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/requests/{public_id}/messages', 'controller' => ClientPortalApiController::class, 'action' => 'addMessage', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/requests/{public_id}/approve', 'controller' => ClientPortalApiController::class, 'action' => 'approveScope', 'auth' => true],
];
