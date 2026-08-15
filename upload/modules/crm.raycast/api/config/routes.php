<?php
declare(strict_types=1);

use Module\Crm\Raycast\Controller\RaycastController;

return [
    ['methods' => ['GET'], 'route' => '/config', 'controller' => RaycastController::class, 'action' => 'getConfig', 'auth' => true, 'required_permissions' => ['module.raycast.view']],
];
