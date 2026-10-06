<?php

declare(strict_types=1);

namespace Module\Crm\VkWorkspace;

use Module\Crm\VkWorkspace\Service\VkWorkspaceService;

class VkWorkspaceServiceProvider
{
    private \PDO $db;
    private array $config;

    public function __construct(\PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function register(): void
    {
        // Service registration in CRM container
    }

    public function boot(): void
    {
        // Hooks & background worker registration
    }

    public function getService(int $workspaceId = 1): VkWorkspaceService
    {
        return new VkWorkspaceService(
            $this->db,
            $workspaceId,
            $this->config['api_token'] ?? null,
            $this->config['bot_token'] ?? null,
            $this->config['domain'] ?? 'myteam.mail.ru'
        );
    }
}
