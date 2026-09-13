<?php
declare(strict_types=1);

namespace Module\Crm\EcommerceGateway;

use Api\System\Library\Container;
use Api\System\Library\Module\AbstractModuleServiceProvider;

final class EcommerceGatewayServiceProvider extends AbstractModuleServiceProvider
{
    public function register(Container $container): void
    {
    }

    public function boot(Container $container): void
    {
        // Outbound status synchronisation (E-COM-04)
        if (!$container->has('hook.manager')) {
            return;
        }

        /** @var \Api\System\Library\Hook\HookManager $hooks */
        $hooks = $container->get('hook.manager');
        $hooks->register(\Api\System\Library\Module\ModuleEvents::TASK_STATUS_CHANGED, function (array $payload) use ($container) {
            try {
                $pdo = $container->has('db.pdo') ? $container->get('db.pdo') : null;
                if (!$pdo instanceof \PDO) {
                    return;
                }

                $outboxRepo = new \Module\Crm\EcommerceGateway\Repository\OutboxRepository($pdo);
                $statusMappingService = new \Module\Crm\EcommerceGateway\Service\StatusMappingService($pdo);
                $storeRepo = new \Module\Crm\EcommerceGateway\Repository\StoreRepository($pdo);
                $jobDispatcher = $container->has('module.job_dispatcher') ? $container->get('module.job_dispatcher') : null;

                $config = $this->getConfig();
                $syncService = new \Module\Crm\EcommerceGateway\Service\StatusSyncService(
                    $outboxRepo,
                    $statusMappingService,
                    $storeRepo,
                    $jobDispatcher,
                    $config
                );

                $syncService->handleTaskStatusChanged($payload);
            } catch (\Throwable $e) {
                error_log('[EcommerceGateway] Hook task.status_changed error: ' . $e->getMessage());
            }
        });
    }

    public function getPermissions(): array
    {
        return [
            'module.ecommerce-gateway.view',
            'module.ecommerce-gateway.manage',
            'module.ecommerce-gateway.secret_manage',
            'module.ecommerce-gateway.run',
        ];
    }

    public function getConfig(): array
    {
        return [
            'timestamp_tolerance_seconds' => 300,
            'nonce_retention_seconds' => 900,
            'max_body_bytes' => 1048576,
            'default_locale' => 'ru-ru',
            'default_on_duplicate' => 'merge',
            'ingest_events_retention_days' => 90,
            'request_timeout_seconds' => 10,
            'outbox_batch_size' => 20,
            'outbox_max_attempts' => 8,
        ];
    }
}
