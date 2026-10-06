<?php

declare(strict_types=1);

use Api\System\Library\Routing\RouteCollector;
use Module\Crm\VkWorkspace\Web\VkWorkspaceWebController;

/** @var RouteCollector $router */
$router->get('/vk-workspace', [VkWorkspaceWebController::class, 'index'], ['auth' => true]);
