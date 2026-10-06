<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace\Web;

class VkWorkspaceWebController
{
    public function index(): string
    {
        return '<div class="crm-vk-workspace-wrap"><h2>VK WorkSpace Встречи и Звонки</h2><p>Планирование видеоконференций VK Звонки, синхронизация с корпоративным календарем и мессенджером VK Teams</p></div>';
    }
}
