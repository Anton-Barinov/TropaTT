<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Hook;

use Module\Crm\WipLimit\Service\WipLimitService;

/**
 * Module hook handlers. Registered by WipLimitServiceProvider::boot() against the
 * core HookManager so the optional module reacts to task lifecycle events.
 */
final class WipHook
{
    /**
     * @param array<string, mixed> $context
     */
    public static function onTaskStatusChanged(WipLimitService $service, array $context): void
    {
        $service->enforce($context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function onAssigneeChanged(WipLimitService $service, array $context): void
    {
        $service->enforce($context);
    }
}
