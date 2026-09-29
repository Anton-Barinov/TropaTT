<?php
declare(strict_types=1);

namespace Api\System\Library\Security;

/**
 * Shared predicates for the "permission = capability" rule.
 *
 * Domain services used to authorize purely by ownership relations (creator,
 * assignee, project manager, team member), which made an administrator with
 * the relevant permission unable to touch anything they did not personally
 * create: a root admin could not rename someone else's task and a role admin
 * with `task.manage` could not even open it. The permission registry already
 * gates the routes for those endpoints, so the service layer now honours the
 * same permission instead of re-deriving access from ownership.
 *
 * Rules that must stay true:
 *  - client-portal (external) actors are governed by their own RLS branches
 *    and never reach these predicates — `can*()` fails closed for them;
 *  - an actor without `permission_codes` in the envelope (system callers,
 *    hand-built test actors) fails closed, exactly like before;
 *  - the organization (workspace) stays the boundary: `sameOrganization()`
 *    compares the actor's active workspace with the row's scope and is
 *    lenient only for legacy rows that predate organization scoping.
 */
final class ActorPermission
{
    /** True when the internal actor may perform task-management operations. */
    public static function canManageTasks(array $actor): bool
    {
        if (!empty((int)($actor['is_external'] ?? 0))) {
            return false;
        }

        return self::has($actor, 'task.manage');
    }

    /** True when the internal actor may perform project-management operations. */
    public static function canManageProjects(array $actor): bool
    {
        if (!empty((int)($actor['is_external'] ?? 0))) {
            return false;
        }

        return self::has($actor, 'project.manage');
    }

    /**
     * True when the internal actor may read projects. Task flows need it as
     * much as the project screens do: picking a project for a new task, moving
     * a task between projects and filtering the task list by project all go
     * through ProjectService::get().
     */
    public static function canReadProjects(array $actor): bool
    {
        if (!empty((int)($actor['is_external'] ?? 0))) {
            return false;
        }

        return self::has($actor, 'project.manage') || self::has($actor, 'task.manage');
    }

    /**
     * True when the row may be touched by an actor bound to an active
     * workspace. Actors without a workspace context keep the legacy,
     * unscoped behaviour; rows that never received an organization id are
     * treated as legacy data and stay reachable.
     */
    public static function sameOrganization(array $actor, array $row): bool
    {
        $actorOrganizationId = (int)($actor['organization_id'] ?? 0);
        if ($actorOrganizationId <= 0) {
            return true;
        }

        $rowOrganizationId = $row['organization_id'] ?? null;
        if ($rowOrganizationId === null || $rowOrganizationId === '') {
            return true;
        }

        return (int)$rowOrganizationId === $actorOrganizationId;
    }

    private static function has(array $actor, string $code): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $codes = $actor['permission_codes'] ?? null;
        if (!is_array($codes)) {
            return false;
        }

        return in_array('*', $codes, true) || in_array($code, $codes, true);
    }
}
