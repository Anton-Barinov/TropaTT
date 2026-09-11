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

    // Collaboration.
    public const COMMENT_ADDED = 'comment.added';
    public const FILE_UPLOADED = 'file.uploaded';

    // Web rendering (dispatched from Web\Controller::render()).
    public const RENDER_BEFORE = 'render.before';
    public const RENDER_AFTER = 'render.after';
}
