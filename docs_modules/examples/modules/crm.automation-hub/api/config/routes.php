<?php
declare(strict_types=1);

use Module\Crm\AutomationHub\Api\Controller\AutomationApiController;

return [
    ['methods' => ['GET'], 'route' => '/recipes', 'controller' => AutomationApiController::class, 'action' => 'listRecipes', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['settings.view']],
    ['methods' => ['POST'], 'route' => '/recipes', 'controller' => AutomationApiController::class, 'action' => 'saveRecipe', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['webhook.manage']],
    ['methods' => ['POST'], 'route' => '/recipes/active', 'controller' => AutomationApiController::class, 'action' => 'setRecipeActive', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['webhook.manage']],
    ['methods' => ['POST'], 'route' => '/test-webhook', 'controller' => AutomationApiController::class, 'action' => 'testWebhook', 'auth' => true, 'workspace_required' => true, 'required_permissions' => ['webhook.manage']],
];
