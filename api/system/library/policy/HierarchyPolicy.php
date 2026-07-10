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

        if ($actorIsRoot) {
            return true;
        }

        if ($targetIsRoot) {
            return false;
        }

        if ($actorId === $targetId) {
            return true;
        }

        // Non-root can manage only their own subtree (actor must be ancestor of target)
        return $this->isAncestor($actorId, $targetId);
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
