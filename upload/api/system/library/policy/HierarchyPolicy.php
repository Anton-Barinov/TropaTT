<?php
declare(strict_types=1);

namespace Api\System\Library\Policy;

use Api\Model\User\UserManagementRepository;

final class HierarchyPolicy
{
    public function __construct(private readonly UserManagementRepository $users)
    {
    }

    public function canManageUser(array $actor, array $target): bool
    {
        $actorId = (int)($actor['id'] ?? 0);
        $targetId = (int)($target['id'] ?? 0);
        $actorIsRoot = (int)($actor['is_root'] ?? 0) === 1;
        $targetIsRoot = (int)($target['is_root'] ?? 0) === 1;

        if ($actorId <= 0 || $targetId <= 0) {
            return false;
        }

        if ($actorId === $targetId) {
            return true;
        }

        // TROPATTCRM-608: an actor working inside an organization (active
        // workspace resolved by OrganizationContextService) may only manage
        // members of that organization. This applies to root as well — before
        // this check a multi-organization root could read, edit, delete and
        // rotate/revoke tokens of any user of any other organization.
        // Actors without an active organization keep the previous behaviour,
        // mirroring UserController::list().
        if (!$this->isWithinActorOrganization($actor, $targetId)) {
            return false;
        }

        if ($actorIsRoot) {
            return true;
        }

        if ($targetIsRoot) {
            return false;
        }

        // Non-root can manage only their own subtree (actor must be ancestor of target)
        return $this->isAncestor($actorId, $targetId);
    }

    /**
     * True when the actor has no active organization, or the target user is a
     * member of the actor's active organization.
     */
    public function isWithinActorOrganization(array $actor, int $targetUserId): bool
    {
        $organizationId = (int)($actor['organization_id'] ?? 0);
        if ($organizationId <= 0) {
            return true;
        }

        return $this->users->isOrganizationMember($targetUserId, $organizationId);
    }

    public function isAncestor(int $candidateAncestorId, int $userId): bool
    {
        $currentId = $userId;
        $safety = 0;

        while ($currentId > 0 && $safety < 1000) {
            $user = $this->users->findById($currentId);
            if (!$user) {
                return false;
            }

            $parentId = $user['created_by_user_id'] !== null ? (int)$user['created_by_user_id'] : 0;
            if ($parentId === 0) {
                return false;
            }
            if ($parentId === $candidateAncestorId) {
                return true;
            }

            $currentId = $parentId;
            $safety++;
        }

        return false;
    }
}
