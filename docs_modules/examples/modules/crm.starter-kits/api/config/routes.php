<?php
declare(strict_types=1);

/** @var \Api\System\Library\Router $router */
$router->get('/api/v1/modules/crm.starter-kits/kits', 'Module\Crm\StarterKits\Api\Controller\StarterKitsApiController@listKits');
$router->get('/api/v1/modules/crm.starter-kits/preview', 'Module\Crm\StarterKits\Api\Controller\StarterKitsApiController@preview');
$router->post('/api/v1/modules/crm.starter-kits/apply', 'Module\Crm\StarterKits\Api\Controller\StarterKitsApiController@apply');
