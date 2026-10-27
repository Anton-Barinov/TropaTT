<?php
declare(strict_types=1);

namespace Module\Crm\SlackIntegration\Hook;

use Api\System\Library\Module\ModuleEvents;
use Module\Crm\SlackIntegration\Service\SlackNotifier;

/**
 * Subscribes the Slack module to the core event system.
 *
 * Registered by SlackServiceProvider::boot() against the core HookManager so the
 * optional module reacts to task/project/user/comment/file events without any
 * core edit. Each event is forwarded to SlackNotifier, which matches enabled
 * rules and enqueues deliveries.
 */
final class SlackHook
{
    /** @var array<int, string> Core events the module listens to. */
    public const EVENTS = [
        ModuleEvents::TASK_CREATED,
        ModuleEvents::TASK_UPDATED,
        ModuleEvents::TASK_STATUS_CHANGED,
        ModuleEvents::TASK_ASSIGNEE_CHANGED,
        ModuleEvents::TASK_DELETED,
        ModuleEvents::COMMENT_ADDED,
        ModuleEvents::FILE_UPLOADED,
        ModuleEvents::PROJECT_CREATED,
        ModuleEvents::PROJECT_UPDATED,
        ModuleEvents::PROJECT_DELETED,
        ModuleEvents::USER_CREATED,
        ModuleEvents::USER_UPDATED,
        ModuleEvents::USER_DELETED,
    ];

    /**
     * @param array<string, mixed> $context
     */
    public static function handle(SlackNotifier $notifier, string $event, array $context): void
    {
        $notifier->enqueueForEvent($event, $context);
    }
}
