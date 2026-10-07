<?php
declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Web\Controller;

use Web\System\Core\Controller;

final class VkWorkspaceWebController extends Controller
{
    public function index(): void
    {
        $this->render(__DIR__ . '/../template/page/crm_vk_workspace.php', [
            'title' => 'VK WorkSpace',
            'route' => 'module-vk-workspace',
        ]);
    }
}
