<?php
declare(strict_types=1);

/** @var \Api\System\Library\Router $router */
$router->get('/api/v1/modules/crm.automation-hub/recipes', 'Module\Crm\AutomationHub\Api\Controller\AutomationApiController@listRecipes');
$router->post('/api/v1/modules/crm.automation-hub/recipes', 'Module\Crm\AutomationHub\Api\Controller\AutomationApiController@saveRecipe');
$router->post('/api/v1/modules/crm.automation-hub/test-webhook', 'Module\Crm\AutomationHub\Api\Controller\AutomationApiController@testWebhook');
