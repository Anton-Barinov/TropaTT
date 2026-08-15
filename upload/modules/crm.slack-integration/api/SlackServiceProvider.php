<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\SlackIntegration\Cron\SlackWorkerHandler;

final class SlackServiceProvider extends AbstractModuleServiceProvider
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
            'module.slack-integration.view',
            'module.slack-integration.manage',
            'module.slack-integration.secret_manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-slack-integration',
                'label' => 'Уведомления в Slack',
                'icon' => '<i class="fa-brands fa-slack"></i>',
                'permission' => 'module.slack-integration.view',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                name: 'process_slack_deliveries',
                description: 'Send queued Slack notifications',
                schedule: '* * * * *',
                handler: [SlackWorkerHandler::class, 'run'],
                enabled: true,
                timeout: 120,
                overlapAllowed: false,
                notifyOnError: true,
            ),
        ];
    }

    public function getConfig(): array
    {
        return [
            'request_timeout_seconds' => 10,
            'max_retries' => 3,
            'retry_backoff_seconds' => 30,
            'allowed_webhook_hosts' => ['hooks.slack.com'],
        ];
    }
}
