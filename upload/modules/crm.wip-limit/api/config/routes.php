<?php
declare(strict_types=1);

return [
    ['methods' => ['GET'], 'route' => '/limits', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'list', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/limits/{user_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'get', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/limits-for-task/{task_public_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'getForTask', 'auth' => true],
    ['methods' => ['POST'], 'route' => '/limits', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'set', 'auth' => true],
    ['methods' => ['DELETE'], 'route' => '/limits/{user_id}', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'delete', 'auth' => true],
    ['methods' => ['GET'], 'route' => '/summary', 'controller' => Module\Crm\WipLimit\Controller\WipApiController::class, 'action' => 'summary', 'auth' => true],
];
