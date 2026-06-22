<?php
declare(strict_types=1);

namespace Module\Crm\JiraMigration;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\JiraMigration\Cron\JiraWorkerHandler;

final class JiraMigrationServiceProvider extends AbstractModuleServiceProvider
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
            'module.jira-migration.view',
            'module.jira-migration.manage',
            'module.jira-migration.run',
            'module.jira-migration.secret_manage',
            'module.jira-migration.delete',
            'module.jira-migration.report_view',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-jira-migration',
                'label' => 'Миграция из Jira',
                'icon' => '<i class="fa-brands fa-jira"></i>',
                'permission' => 'module.jira-migration.view',
                'parent' => null,
            ],
        ];
    }

    public function getScheduledTasks(): array
    {
        return [
            new ScheduledTask(
                name: 'process_jira_imports',
                description: 'Process queued Jira import jobs',
                schedule: '*/5 * * * *',
                handler: [JiraWorkerHandler::class, 'run'],
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
            'max_attachment_size_mb' => 20,
            'allowed_jira_hosts' => ['*.atlassian.net'],
            'custom_domain_allowlist' => [],
            'default_batch_size' => 100,
            'request_timeout_seconds' => 60,
            'max_retries' => 3,
            'dry_run_sample_limit' => 200,
            'jql_default_max_results' => 100,
            'import_issue_limit' => 0,
            'worklog_batch_size' => 50,
            'attachment_download_timeout' => 120,
        ];
    }
}
