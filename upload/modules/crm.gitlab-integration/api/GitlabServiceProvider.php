<?php
declare(strict_types=1);

namespace Module\Crm\GitlabIntegration;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ModuleEvents;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\GitlabIntegration\Cron\GitLabWorkerHandler;
use Module\Crm\GitlabIntegration\Repository\GitLabRepository;
use Module\Crm\GitlabIntegration\Service\GitLabClient;
use Module\Crm\GitlabIntegration\Service\GitLabPushService;

final class GitlabServiceProvider extends AbstractModuleServiceProvider
{
    private ?Container $container = null;

    public function register(Container $container): void
    {
        $this->container = $container;
    }

    public function boot(Container $container): void
    {
        $this->container = $container;

        /** @var HookManager $hooks */
        $hooks = $container->get('hook.manager');
        $config = $this->moduleConfig($container);

        $hooks->register(ModuleEvents::COMMENT_ADDED, function (array &$context) use ($container, $config): void {
            $this->handlePush($container, $config, 'comment', $context);
        }, 100);

        $hooks->register(ModuleEvents::TASK_STATUS_CHANGED, function (array &$context) use ($container, $config): void {
            $this->handlePush($container, $config, 'status', $context);
        }, 100);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function handlePush(Container $container, array $config, string $kind, array $context): void
    {
        try {
            $push = new GitLabPushService(
                $container,
                new GitLabRepository($container->get('db.pdo')),
                new GitLabClient(30, 3),
                $config
            );
            if ($kind === 'comment') {
                $push->onCommentAdded($context);
            } else {
                $push->onStatusChanged($context);
            }
        } catch (\Throwable $e) {
            error_log('[GitlabServiceProvider] push handler failed: ' . $e->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleConfig(Container $container): array
    {
        if (!$container->has('module.config')) {
            return [];
        }
        $config = $container->get('module.config')->getAll('crm.gitlab-integration');
        return is_array($config) ? $config : [];
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
            'push_back_comments' => true,
            'push_back_status' => true,
        ];
    }
}
