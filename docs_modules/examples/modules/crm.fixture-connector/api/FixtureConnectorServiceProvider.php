<?php
declare(strict_types=1);

namespace Module\Crm\FixtureConnector;

use Api\System\Library\Container;
use Api\System\Library\HookManager;
use Api\System\Library\Module\AbstractModuleServiceProvider;
use Api\System\Library\Module\ModuleEvents;
use Api\System\Library\Module\ModuleExecutionContext;
use Api\System\Library\Module\ModuleJobDispatcher;
use Module\Crm\FixtureConnector\Job\SyncIntakeJob;

final class FixtureConnectorServiceProvider extends AbstractModuleServiceProvider
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

        // Listen for intake created event and schedule async synchronization job
        $hooks->register(
            ModuleEvents::INTAKE_CREATED,
            function (array &$payload) use ($container): void {
                $orgId = (int)($payload['organization_id'] ?? 0);
                $orgPublicId = (string)($payload['organization_public_id'] ?? '');

                if ($orgId <= 0) {
                    return; // Skip if no workspace context
                }

                $context = new ModuleExecutionContext(
                    moduleName: 'crm.fixture-connector',
                    organizationId: $orgId,
                    organizationPublicId: $orgPublicId,
                    actorPublicId: (string)($payload['actor_public_id'] ?? ''),
                    source: 'event',
                    correlationId: (string)($payload['event_id'] ?? uniqid('corr_', true))
                );

                ModuleJobDispatcher::dispatch(
                    container: $container,
                    moduleName: 'crm.fixture-connector',
                    handlerClass: SyncIntakeJob::class,
                    payload: [
                        'event_type' => 'intake.created',
                        'intake_public_id' => $payload['intake_public_id'] ?? '',
                        'title' => $payload['title'] ?? '',
                        'idempotency_key' => $payload['event_id'] ?? null,
                    ],
                    context: $context,
                    availableAt: 0,
                    priority: 50
                );
            },
            10
        );
    }

    public function getPermissions(): array
    {
        return [
            'settings.view',
            'settings.edit',
        ];
    }
}
