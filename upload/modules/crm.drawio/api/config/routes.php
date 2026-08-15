<?php
declare(strict_types=1);

use Module\Crm\Drawio\Controller\DrawioController;

return [
    ['methods' => ['GET'], 'route' => '/diagrams', 'controller' => DrawioController::class, 'action' => 'listDiagrams', 'auth' => true, 'required_permissions' => ['module.drawio.view']],
    ['methods' => ['POST'], 'route' => '/diagrams', 'controller' => DrawioController::class, 'action' => 'createDiagram', 'auth' => true, 'required_permissions' => ['module.drawio.manage']],
    ['methods' => ['GET'], 'route' => '/diagrams/{public_id}', 'controller' => DrawioController::class, 'action' => 'getDiagram', 'auth' => true, 'required_permissions' => ['module.drawio.view']],
    ['methods' => ['PATCH'], 'route' => '/diagrams/{public_id}', 'controller' => DrawioController::class, 'action' => 'updateDiagram', 'auth' => true, 'required_permissions' => ['module.drawio.manage']],
    ['methods' => ['DELETE'], 'route' => '/diagrams/{public_id}', 'controller' => DrawioController::class, 'action' => 'deleteDiagram', 'auth' => true, 'required_permissions' => ['module.drawio.manage']],
];
