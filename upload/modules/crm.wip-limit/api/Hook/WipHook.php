<?php
declare(strict_types=1);

namespace Module\Crm\WipLimit\Hook;

use Module\Crm\WipLimit\Service\WipNotifier;

/**
 * Module hook handlers. Registered by WipLimitServiceProvider::boot() against the
 * core HookManager so the optional module reacts to task lifecycle events.
 */
final class WipHook
{
    /**
     * @param array<string, mixed> $context
     */
    public static function onTaskStatusChanged(WipNotifier $notifier, array $context): void
    {
        $notifier->onTaskChanged($context, 'task.status_changed');
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function onAssigneeChanged(WipNotifier $notifier, array $context): void
    {
        $notifier->onTaskChanged($context, 'task.assignee_changed');
    }
}
