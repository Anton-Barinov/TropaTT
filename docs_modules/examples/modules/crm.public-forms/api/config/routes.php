<?php
declare(strict_types=1);

use Module\Crm\PublicForms\Api\Controller\PublicFormsApiController;

return [
    ['methods' => ['GET'], 'route' => '/forms/{slug}', 'controller' => PublicFormsApiController::class, 'action' => 'getForm', 'auth' => false],
    ['methods' => ['POST'], 'route' => '/forms/{slug}/submit', 'controller' => PublicFormsApiController::class, 'action' => 'submitForm', 'auth' => false],
];
