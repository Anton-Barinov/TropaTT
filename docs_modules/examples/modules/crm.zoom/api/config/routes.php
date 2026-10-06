<?php

declare(strict_types=1);

use Api\System\Library\Routing\RouteCollector;
use Module\Crm\Zoom\Api\Controller\ZoomApiController;

/** @var RouteCollector $router */
$router->get('/api/v1/zoom/status', [ZoomApiController::class, 'getStatus'], ['auth' => true]);
$router->post('/api/v1/zoom/meetings', [ZoomApiController::class, 'createMeeting'], ['auth' => true]);
$router->get('/api/v1/zoom/meetings', [ZoomApiController::class, 'listMeetings'], ['auth' => true]);
