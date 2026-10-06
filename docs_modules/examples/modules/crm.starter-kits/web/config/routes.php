<?php
declare(strict_types=1);

/** @var \Web\System\Library\Router $router */
$router->get('/admin/modules/starter-kits', 'Module\Crm\StarterKits\Web\Controller\StarterKitsWebController@index');
