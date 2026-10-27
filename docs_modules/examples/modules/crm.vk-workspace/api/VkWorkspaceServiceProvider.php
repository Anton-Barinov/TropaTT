<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

use Module\Crm\VkWorkspace\Service\VkWorkspaceService;

class VkWorkspaceServiceProvider extends AbstractModuleServiceProvider
{
    private ?\PDO $db = null;
    private array $config = [];

    public function __construct(?\PDO $db = null, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function register(Container $container): void
    {
        // Service registration in CRM container
    }

    public function boot(Container $container): void
    {
        // Hooks & background worker registration
    }

    public function getService(int $workspaceId = 0): VkWorkspaceService
    {
        if (!$this->db instanceof \PDO) {
            throw new \RuntimeException('VK WorkSpace storage is unavailable.');
        }
        return new VkWorkspaceService($this->db, $workspaceId);
    }
}
