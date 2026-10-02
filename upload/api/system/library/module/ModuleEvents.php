<?php
declare(strict_types=1);

namespace Api\System\Library\Module;

/**
 * Catalog of module lifecycle events.
 *
 * Modules subscribe to these names through the HookManager (usually in the
 * service provider's boot() method) and the core dispatches them at well-defined
 * points. Keeping the names in one place prevents typos and makes the extension
 * surface greppable/documented for module authors.
 */
final class ModuleEvents
{
    // Task lifecycle (dispatched from TaskController).
    public const TASK_CREATED = 'task.created';
    public const TASK_UPDATED = 'task.updated';
    public const TASK_STATUS_CHANGED = 'task.status_changed';
    public const TASK_ASSIGNEE_CHANGED = 'task.assignee_changed';
    public const TASK_DELETED = 'task.deleted';

    // Project lifecycle (dispatched from ProjectController).
    public const PROJECT_CREATED = 'project.created';
    public const PROJECT_UPDATED = 'project.updated';
    public const PROJECT_DELETED = 'project.deleted';

    // User lifecycle (dispatched from UserController).
    public const USER_CREATED = 'user.created';
    public const USER_UPDATED = 'user.updated';
    public const USER_DELETED = 'user.deleted';

    // Work cycle / sprint lifecycle (dispatched from WorkCycleController).
    public const CYCLE_CREATED = 'cycle.created';
    public const CYCLE_STARTED = 'cycle.started';
    public const CYCLE_COMPLETED = 'cycle.completed';
    public const CYCLE_REOPENED = 'cycle.reopened';
    public const CYCLE_ARCHIVED = 'cycle.archived';
    public const CYCLE_DELETED = 'cycle.deleted';

    // CRM records (dispatched from their controllers).
    public const CLIENT_CREATED = 'client.created';
    public const CLIENT_UPDATED = 'client.updated';
    public const CLIENT_DELETED = 'client.deleted';
    public const COUNTERPARTY_CREATED = 'counterparty.created';
    public const COUNTERPARTY_UPDATED = 'counterparty.updated';
    public const COUNTERPARTY_DELETED = 'counterparty.deleted';
    public const CONTACT_CREATED = 'contact.created';
    public const CONTACT_UPDATED = 'contact.updated';
    public const CONTACT_DELETED = 'contact.deleted';
    public const COMPANY_CREATED = 'company.created';
    public const COMPANY_UPDATED = 'company.updated';
    public const COMPANY_DELETED = 'company.deleted';
    public const ORGANIZATION_CREATED = 'organization.created';
    public const ORGANIZATION_UPDATED = 'organization.updated';
    public const ORGANIZATION_DELETED = 'organization.deleted';

    // Taxonomy / configuration entities (dispatched from their controllers).
    public const TAG_CREATED = 'tag.created';
    public const TAG_UPDATED = 'tag.updated';
    public const TAG_DELETED = 'tag.deleted';
    public const STATUS_CREATED = 'status.created';
    public const STATUS_UPDATED = 'status.updated';
    public const STATUS_DELETED = 'status.deleted';
    public const PRIORITY_CREATED = 'priority.created';
    public const PRIORITY_UPDATED = 'priority.updated';
    public const PRIORITY_DELETED = 'priority.deleted';
    public const CUSTOM_FIELD_CREATED = 'custom_field.created';
    public const CUSTOM_FIELD_UPDATED = 'custom_field.updated';
    public const CUSTOM_FIELD_DELETED = 'custom_field.deleted';

    // Collaboration & Chat.
    public const COMMENT_ADDED = 'comment.added';
    public const FILE_UPLOADED = 'file.uploaded';
    public const CHAT_MESSAGE_CREATED = 'chat.message_created';
    public const CHAT_MESSAGE_UPDATED = 'chat.message_updated';
    public const CHAT_MESSAGE_DELETED = 'chat.message_deleted';

    // Intake (lead/idea queue). Dispatched by IntakeItemService so the web UI,
    // REST and MCP all produce exactly one event per successful mutation.
    public const INTAKE_CREATED = 'intake.created';
    public const INTAKE_UPDATED = 'intake.updated';
    public const INTAKE_ACCEPTED = 'intake.accepted';
    public const INTAKE_REJECTED = 'intake.rejected';
    public const INTAKE_REOPENED = 'intake.reopened';
    public const INTAKE_DELETED = 'intake.deleted';

    // Calendar events. Recurrence identity is carried by the payload.
    public const CALENDAR_EVENT_CREATED = 'calendar_event.created';
    public const CALENDAR_EVENT_UPDATED = 'calendar_event.updated';
    public const CALENDAR_EVENT_DELETED = 'calendar_event.deleted';

    // Worklogs. Financial fields (cost/bill rates and amounts) are stripped
    // from the generic payload by DomainEventPublisher — subscribers get the
    // time accounting, never the money.
    public const WORKLOG_CREATED = 'worklog.created';
    public const WORKLOG_UPDATED = 'worklog.updated';
    public const WORKLOG_DELETED = 'worklog.deleted';

    // Knowledge pages. The page body never travels in the payload; only the
    // space/page public ids and the lifecycle state do.
    public const KNOWLEDGE_PAGE_CREATED = 'knowledge_page.created';
    public const KNOWLEDGE_PAGE_UPDATED = 'knowledge_page.updated';
    public const KNOWLEDGE_PAGE_PUBLISHED = 'knowledge_page.published';
    public const KNOWLEDGE_PAGE_ARCHIVED = 'knowledge_page.archived';
    public const KNOWLEDGE_PAGE_DELETED = 'knowledge_page.deleted';

    // Web rendering (dispatched from Web\Controller::render()).
    public const RENDER_BEFORE = 'render.before';
    public const RENDER_AFTER = 'render.after';
}
