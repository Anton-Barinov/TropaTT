<?php
declare(strict_types=1);

use Module\Crm\AutomationHub\Api\Controller\AutomationApiController;

return [
    ['methods' => ['GET'], 'route' => '/recipes', 'controller' => AutomationApiController::class, 'action' => 'listRecipes', 'auth' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/recipes', 'controller' => AutomationApiController::class, 'action' => 'saveRecipe', 'auth' => true, 'required_permissions' => ['settings.edit']],
    ['methods' => ['POST'], 'route' => '/test-webhook', 'controller' => AutomationApiController::class, 'action' => 'testWebhook', 'auth' => true, 'required_permissions' => ['settings.edit']],
];
