<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Module\Crm\WipLimit\Hook\WipHook;
use Module\Crm\WipLimit\Service\WipLimitService;

final class WipLimitServiceProvider extends AbstractModuleServiceProvider
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

        $hooks->register('task.status_changed', function (array &$context): void {
            $this->handleHook($context);
        }, 100);

        $hooks->register('task.assignee_changed', function (array &$context): void {
            $this->handleHook($context);
        }, 100);
    }

    public function getPermissions(): array
    {
        return [
            'module.wip-limit.view',
            'module.wip-limit.manage',
        ];
    }

    public function getMenuItems(): array
    {
        return [
            [
                'route' => 'module-wip-limit',
                'label' => 'WIP-лимиты',
                'icon' => '<i class="fa-solid fa-gauge-high"></i>',
                'permission' => 'module.wip-limit.view',
                'parent' => null,
            ],
        ];
    }

    public function getConfig(): array
    {
        return [
            'default_limit' => 5,
            'enforce_on_status' => ['in_progress', 'review'],
            'notify_on_exceed' => true,
            'excluded_role_ids' => [],
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function handleHook(array $context): void
    {
        try {
            $service = $this->makeService();
            WipHook::onTaskStatusChanged($service, $context);
        } catch (\Throwable $e) {
            error_log('[WipLimitServiceProvider] Hook handler failed: ' . $e->getMessage());
        }
    }

    private function makeService(): WipLimitService
    {
        if ($this->container === null) {
            throw new \RuntimeException('WipLimitServiceProvider container not initialized');
        }

        $pdo = $this->container->get('db.pdo');
        $moduleConfig = $this->container->get('module.config');
        $config = $moduleConfig->getAll('crm.wip-limit');

        return new WipLimitService($pdo, $config);
    }
}
