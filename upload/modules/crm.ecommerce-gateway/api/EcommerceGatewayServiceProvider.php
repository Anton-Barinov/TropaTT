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
        // Outbound status synchronisation (E-COM-04) registers its hooks here.
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
