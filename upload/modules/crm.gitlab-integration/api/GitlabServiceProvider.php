<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\GitlabIntegration\Cron\GitLabWorkerHandler;

final class GitlabServiceProvider extends AbstractModuleServiceProvider
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
            'module.gitlab-integration.view',
            'module.gitlab-integration.manage',
            'module.gitlab-integration.run',
            'module.gitlab-integration.secret_manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-gitlab-integration',
                'label' => 'Интеграция с GitLab',
                'icon' => '<i class="fa-brands fa-gitlab"></i>',
                'permission' => 'module.gitlab-integration.view',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                name: 'poll_sync',
                description: 'Poll linked GitLab projects and sync merge requests to TropaTT tasks',
                schedule: '* * * * *',
                handler: [GitLabWorkerHandler::class, 'run'],
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
            'default_base_url' => 'https://gitlab.com/api/v4',
            'sync_comments' => true,
            'poll_interval_minutes' => 15,
        ];
    }
}
