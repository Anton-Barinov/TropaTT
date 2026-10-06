<?php

declare(strict_types=1);

use Api\System\Library\Routing\RouteCollector;
use Module\Crm\Zoom\Web\ZoomWebController;

/** @var RouteCollector $router */
$router->get('/zoom', [ZoomWebController::class, 'index'], ['auth' => true]);
