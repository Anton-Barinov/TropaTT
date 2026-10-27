<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ScheduledTask;
use Module\Crm\SlackIntegration\Cron\SlackWorkerHandler;
use Module\Crm\SlackIntegration\Hook\SlackHook;
use Module\Crm\SlackIntegration\Service\SlackNotifier;

final class SlackServiceProvider extends AbstractModuleServiceProvider
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

        foreach (SlackHook::EVENTS as $event) {
            $hooks->register($event, function (array &$context) use ($event): void {
                $this->handleEvent($event, $context);
            }, 100);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function handleEvent(string $event, array $context): void
    {
        try {
            $notifier = $this->makeNotifier();
            SlackHook::handle($notifier, $event, $context);
        } catch (\Throwable $e) {
            error_log('[SlackServiceProvider] ' . $event . ' failed: ' . $e->getMessage());
        }
    }

    private function makeNotifier(): SlackNotifier
    {
        if ($this->container === null) {
            throw new \RuntimeException('SlackServiceProvider container not initialized');
        }

        return new SlackNotifier($this->container->get('db.pdo'));
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
