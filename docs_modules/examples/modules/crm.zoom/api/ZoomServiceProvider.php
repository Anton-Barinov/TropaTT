<?php

declare(strict_types=1);

namespace Module\Crm\Zoom;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

use Module\Crm\Zoom\Service\ZoomService;

class ZoomServiceProvider extends AbstractModuleServiceProvider
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
        // Register ZoomService in CRM container
    }

    public function boot(Container $container): void
    {
        // Register webhooks & listeners
    }

    public function getService(int $workspaceId = 1): ZoomService
    {
        return new ZoomService(
            $this->db,
            $workspaceId,
            $this->config['access_token'] ?? null
        );
    }
}