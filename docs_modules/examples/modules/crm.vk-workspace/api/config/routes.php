<?php

declare(strict_types=1);

use Api\System\Library\Routing\RouteCollector;
use Module\Crm\VkWorkspace\Api\Controller\VkWorkspaceApiController;

/** @var RouteCollector $router */
$router->get('/api/v1/vk-workspace/status', [VkWorkspaceApiController::class, 'getStatus'], ['auth' => true]);
$router->post('/api/v1/vk-workspace/meetings', [VkWorkspaceApiController::class, 'scheduleMeeting'], ['auth' => true]);
$router->get('/api/v1/vk-workspace/meetings', [VkWorkspaceApiController::class, 'listMeetings'], ['auth' => true]);
