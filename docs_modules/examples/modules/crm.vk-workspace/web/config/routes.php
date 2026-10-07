<?php
declare(strict_types=1);

use Module\Crm\VkWorkspace\Web\Controller\VkWorkspaceWebController;

return [
    'module-vk-workspace' => [VkWorkspaceWebController::class, 'index'],
];
