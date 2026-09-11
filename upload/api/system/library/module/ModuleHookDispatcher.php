<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;
use Api\System\Library\Service\WebhookService;

/**
 * Single entry point for core code to dispatch module hooks.
 *
 * Core controllers never talk to the HookManager directly; they call
 * ModuleHookDispatcher::dispatch($container, ModuleEvents::TASK_*, $payload).
 * The dispatcher swallows errors so a broken third-party module can never break
 * the core request, and every handler receives the same payload shape.
 *
 * The same call also fans the event out to the core webhook subscriptions
 * (`webhook_subscriptions`), which is what makes a subscription receive real
 * events instead of only the manual "test delivery".
 */
final class ModuleHookDispatcher
{
    /**
     * @param array<string, mixed> $payload Passed by reference to handlers, so
     *                                    modules can both observe and (when the
     *                                    event semantics allow) enrich it.
     */
    public static function dispatch(Container $container, string $event, array $payload): void
    {
        try {
            /** @var HookManager $hooks */
            $hooks = $container->get('hook.manager');
            $hooks->dispatch($event, $payload);
        } catch (\Throwable $e) {
            self::log($container, 'module_hook_dispatch_failed', $event, $e);
        }

        try {
            /** @var WebhookService $webhooks */
            $webhooks = $container->get('service.webhook');
            $webhooks->dispatchEvent($event, $payload);
        } catch (\Throwable $e) {
            // Webhook delivery must never break the request that produced the event.
            self::log($container, 'webhook_event_dispatch_failed', $event, $e);
        }
    }

    private static function log(Container $container, string $message, string $event, \Throwable $e): void
    {
        $context = ['event' => $event, 'error' => $e->getMessage()];

        try {
            $logger = $container->get('logger');
            if ($logger !== null && method_exists($logger, 'warning')) {
                $logger->warning($message, $context);
                return;
            }
        } catch (\Throwable $ignored) {
            // fall through to error_log()
        }

        // error_log() is discarded by stock PHP-FPM, so it is only a last resort.
        error_log('[' . $message . '][' . $event . '] ' . $e->getMessage());
    }
}
