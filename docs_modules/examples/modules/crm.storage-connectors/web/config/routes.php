<?php
declare(strict_types=1);

/** @var \Web\System\Library\Router $router */
$router->get('/admin/modules/storage-connectors', 'Module\Crm\StorageConnectors\Web\Controller\StorageConnectorsWebController@index');
