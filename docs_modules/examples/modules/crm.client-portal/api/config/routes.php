<?php
declare(strict_types=1);

use Module\Crm\ClientPortal\Api\Controller\ClientPortalApiController;

return [
    ['methods' => ['GET'], 'route' => '/requests', 'controller' => ClientPortalApiController::class, 'action' => 'listRequests', 'auth' => true, 'workspace_required' => true, 'external_ok' => true],
    ['methods' => ['GET'], 'route' => '/requests/{public_id}', 'controller' => ClientPortalApiController::class, 'action' => 'getRequest', 'auth' => true, 'workspace_required' => true, 'external_ok' => true],
    ['methods' => ['GET'], 'route' => '/requests/{public_id}/messages', 'controller' => ClientPortalApiController::class, 'action' => 'listMessages', 'auth' => true, 'workspace_required' => true, 'external_ok' => true],
    ['methods' => ['POST'], 'route' => '/requests', 'controller' => ClientPortalApiController::class, 'action' => 'createRequest', 'auth' => true, 'workspace_required' => true, 'external_ok' => true, 'external_write_ok' => true],
    ['methods' => ['POST'], 'route' => '/requests/{public_id}/messages', 'controller' => ClientPortalApiController::class, 'action' => 'addMessage', 'auth' => true, 'workspace_required' => true, 'external_ok' => true, 'external_write_ok' => true],
    ['methods' => ['POST'], 'route' => '/requests/{public_id}/approve', 'controller' => ClientPortalApiController::class, 'action' => 'approveScope', 'auth' => true, 'workspace_required' => true, 'external_ok' => true, 'external_write_ok' => true],
];
