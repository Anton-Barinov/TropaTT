<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\GithubIntegration\Cron\GitHubWorkerHandler;

final class GithubServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
    }

    public function getPermissions(): array
    {
        return [
            'module.github-integration.view',
            'module.github-integration.manage',
            'module.github-integration.run',
            'module.github-integration.secret_manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-github-integration',
                'label' => 'GitHub',
                'icon' => '<i class="fa-brands fa-github"></i>',
                'permission' => 'module.github-integration.view',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                name: 'poll_sync',
                description: 'Poll linked GitHub repositories and sync issues/PRs to TropaTT tasks',
                schedule: '* * * * *',
                handler: [GitHubWorkerHandler::class, 'run'],
                enabled: true,
                timeout: 300,
                overlapAllowed: false,
                notifyOnError: true,
            ),
        ];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 30,
            'max_retries' => 3,
            'batch_size' => 100,
            'default_base_url' => 'https://api.github.com',
            'allowed_api_hosts' => ['api.github.com'],
            'sync_comments' => true,
            'poll_interval_minutes' => 15,
        ];
    }
}
