<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

use Api\System\Library\Container;
use Api\System\Library\Hook\HookManager;

/**
 * Single entry point for core code to dispatch module hooks.
 *
 * Core controllers never talk to the HookManager directly; they call
 * ModuleHookDispatcher::dispatch($container, ModuleEvents::TASK_*, $payload).
 * The dispatcher swallows errors so a broken third-party module can never break
 * the core request, and every handler receives the same payload shape.
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
            error_log('[ModuleHookDispatcher][' . $event . '] ' . $e->getMessage());
        }
    }
}
