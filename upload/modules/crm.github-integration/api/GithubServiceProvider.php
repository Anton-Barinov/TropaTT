<?php
declare(strict_types=1);

namespace Module\Crm\GithubIntegration;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ModuleEvents;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\GithubIntegration\Cron\GitHubWorkerHandler;
use Module\Crm\GithubIntegration\Repository\GitHubRepository;
use Module\Crm\GithubIntegration\Service\GitHubClient;
use Module\Crm\GithubIntegration\Service\GitHubPushService;

final class GithubServiceProvider extends AbstractModuleServiceProvider
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
            $push = new GitHubPushService(
                $container,
                new GitHubRepository($container->get('db.pdo')),
                new GitHubClient(30, 3),
                $config
            );
            if ($kind === 'comment') {
                $push->onCommentAdded($context);
            } else {
                $push->onStatusChanged($context);
            }
        } catch (\Throwable $e) {
            error_log('[GithubServiceProvider] push handler failed: ' . $e->getMessage());
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
        $config = $container->get('module.config')->getAll('crm.github-integration');
        return is_array($config) ? $config : [];
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
            'push_back_comments' => true,
            'push_back_status' => true,
        ];
    }
}
