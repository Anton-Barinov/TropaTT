<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Team\TeamRepository;
use Api\System\Library\Support\Ulid;

final class TeamService
{
    public function __construct(
        private readonly TeamRepository $teams,
        private readonly ?NotificationService $notifications = null,
        private readonly ?ChatService $chats = null
    )
    {
    }

    public function list(array $filters, array $actor): array
    {
        [$items, $total, $page, $limit] = $this->teams->list($filters, (int)($actor['id'] ?? 0), (bool)($actor['is_root'] ?? false), $this->organizationId($actor));
        $items = array_map(fn(array $item): array => $this->decorateTeam($item, $actor), $items);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
            ],
        ];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $team = $this->teams->findByPublicId($publicId, $this->organizationId($actor));
        if (!$team || !$this->canView($team, $actor)) {
            return null;
        }

        return $this->decorateTeam($team, $actor);
    }

    public function create(array $input, array $actor): array
    {
        $actorId = (int)($actor['id'] ?? 0);
        $managerId = $this->resolveManagerIdForCreate($input, $actor);
        $memberUserIds = $this->resolveMemberUserIds($input, $actor);
        $parentId = $this->resolveParentId($input, $actor);

        $publicId = Ulid::generate('tem');
        $now = gmdate('Y-m-d H:i:s');

        $this->teams->create([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'team_type' => trim((string)($input['team_type'] ?? 'team')) ?: 'team',
            'parent_id' => $parentId,
            'code' => trim((string)($input['code'] ?? '')) ?: null,
            'manager_user_id' => $managerId > 0 ? $managerId : ($actorId > 0 ? $actorId : null),
            'created_by_user_id' => $actorId > 0 ? $actorId : null,
            'member_user_ids' => json_encode($memberUserIds, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ], $this->organizationId($actor));

        $team = $this->teams->findByPublicId($publicId, $this->organizationId($actor)) ?: ['public_id' => $publicId];
        if (!is_array($team)) {
            return ['public_id' => $publicId];
        }

        $this->notifications?->notifyTeamMembersAdded($team, $memberUserIds, $actor);
        $this->chats?->ensureTeamChat($team, $actor);
        return $this->decorateTeam($team, $actor);
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $team = $this->teams->findByPublicId($publicId, $this->organizationId($actor));
        if (!$team || !$this->canManage($team, $actor)) {
            return null;
        }

        $beforeMemberIds = $this->memberIds($team['member_user_ids'] ?? null);

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }

        if (array_key_exists('team_type', $input)) {
            $set['team_type'] = trim((string)$input['team_type']) ?: 'team';
        }

        if (array_key_exists('code', $input)) {
            $set['code'] = trim((string)$input['code']) ?: null;
        }

        if (array_key_exists('parent_public_id', $input) || array_key_exists('parent_id', $input)) {
            $rawParentPublic = $input['parent_public_id'] ?? null;
            $rawParentId = $input['parent_id'] ?? null;
            $hasParentPublic = ($rawParentPublic !== null && trim((string)$rawParentPublic) !== '');
            $hasParentId = ($rawParentId !== null && (int)$rawParentId > 0);

            if (!$hasParentPublic && !$hasParentId) {
                $set['parent_id'] = null;
            } else {
                $parentId = $this->resolveParentId($input, $actor);
                if ($parentId !== (int)($team['id'] ?? 0)) {
                    $set['parent_id'] = $parentId;
                }
            }
        }

        if (array_key_exists('manager_user_id', $input) || array_key_exists('manager_user_public_id', $input)) {
            $currentManager = isset($team['manager_user_id']) && (int)$team['manager_user_id'] > 0 ? (int)$team['manager_user_id'] : null;
            $set['manager_user_id'] = $this->resolveManagerIdForUpdate($input, $actor, $currentManager);
        }

        if (array_key_exists('member_user_public_ids', $input) || array_key_exists('member_user_ids', $input)) {
            $memberUserIds = $this->resolveMemberUserIds($input, $actor);
            $set['member_user_ids'] = json_encode($memberUserIds, JSON_UNESCAPED_UNICODE);
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->teams->updateByPublicId($publicId, $set, $this->organizationId($actor));

        $updated = $this->teams->findByPublicId($publicId, $this->organizationId($actor));
        if (!$updated) {
            return null;
        }

        $afterMemberIds = $this->memberIds($updated['member_user_ids'] ?? null);
        $addedMemberIds = array_values(array_diff($afterMemberIds, $beforeMemberIds));
        if ($addedMemberIds !== []) {
            $this->notifications?->notifyTeamMembersAdded($updated, $addedMemberIds, $actor);
        }

        $this->chats?->ensureTeamChat($updated, $actor);

        return $this->decorateTeam($updated, $actor);
    }

    public function delete(string $publicId, array $actor): bool
    {
        $team = $this->teams->findByPublicId($publicId, $this->organizationId($actor));
        if (!$team || !$this->canManage($team, $actor)) {
            return false;
        }

        $deleted = $this->teams->deleteByPublicId($publicId, $this->organizationId($actor));
        if ($deleted) {
            // the team's system chat must not outlive the team
            $this->chats?->archiveSystemChatFor((int)($team['id'] ?? 0), null);
        }

        return $deleted;
    }

    private function organizationId(array $actor): ?int
    {
        $id = (int)($actor['organization_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function canView(array $team, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        return $this->teams->userHasAccessToTeam($team, (int)($actor['id'] ?? 0));
    }

    private function canManage(array $team, array $actor): bool
    {
        return $this->teams->userCanManageTeam(
            $team,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );
    }

    private function resolveManagerId(array $input, array $actor): int
    {
        return (int)($this->resolveManagerIdForCreate($input, $actor) ?? (int)($actor['id'] ?? 0));
    }

    private function resolveManagerIdForCreate(array $input, array $actor): ?int
    {
        $actorId = (int)($actor['id'] ?? 0);
        $organizationId = $this->organizationId($actor);

        if (!empty($input['manager_user_public_id'])) {
            $managerId = $this->teams->userIdByPublicId(trim((string)$input['manager_user_public_id']), $organizationId);
            return $managerId ?? ($actorId > 0 ? $actorId : null);
        }

        // TROPATTCRM-555: a raw integer manager_user_id must be verified to
        // belong to the actor's own organization, exactly like the
        // *_public_id path above, instead of being trusted blindly.
        if (isset($input['manager_user_id']) && (int)$input['manager_user_id'] > 0) {
            $managerId = $this->teams->userIdInOrganization((int)$input['manager_user_id'], $organizationId);
            return $managerId ?? ($actorId > 0 ? $actorId : null);
        }

        return $actorId > 0 ? $actorId : null;
    }

    private function resolveManagerIdForUpdate(array $input, array $actor, ?int $currentManagerId): ?int
    {
        $organizationId = $this->organizationId($actor);
        $hasPublic = array_key_exists('manager_user_public_id', $input);
        $hasNumeric = array_key_exists('manager_user_id', $input);

        if (!$hasPublic && !$hasNumeric) {
            return $currentManagerId;
        }

        $rawPublic = $hasPublic ? $input['manager_user_public_id'] : null;
        $rawNumeric = $hasNumeric ? $input['manager_user_id'] : null;

        $hasPublicVal = ($rawPublic !== null && trim((string)$rawPublic) !== '');
        $hasNumericVal = ($rawNumeric !== null && (int)$rawNumeric > 0);

        if (!$hasPublicVal && !$hasNumericVal) {
            // Explicit clear (empty string or null) -> clears manager
            return null;
        }

        if ($hasPublicVal) {
            $resolved = $this->teams->userIdByPublicId(trim((string)$rawPublic), $organizationId);
            return $resolved !== null ? $resolved : $currentManagerId;
        }

        if ($hasNumericVal) {
            $resolved = $this->teams->userIdInOrganization((int)$rawNumeric, $organizationId);
            return $resolved !== null ? $resolved : $currentManagerId;
        }

        return $currentManagerId;
    }

    /** @return int[] */
    private function resolveMemberUserIds(array $input, ?array $actor = null): array
    {
        $orgId = $actor !== null ? $this->organizationId($actor) : null;

        if (isset($input['member_user_public_ids']) && is_array($input['member_user_public_ids'])) {
            return $this->teams->userIdsByPublicIds((array)$input['member_user_public_ids'], $orgId);
        }

        if (isset($input['member_user_ids']) && is_array($input['member_user_ids'])) {
            // INST-4 fix: if any values are public_id strings (e.g. "usr_XXX"),
            // convert them to integer IDs. intval() silently converts non-numeric
            // strings to 0, losing the data.
            $ids = [];
            $publicIds = [];
            foreach ($input['member_user_ids'] as $value) {
                if (is_int($value) || (is_string($value) && ctype_digit($value))) {
                    $intVal = (int)$value;
                    if ($intVal > 0) {
                        $verified = $orgId !== null ? $this->teams->userIdInOrganization($intVal, $orgId) : $intVal;
                        if ($verified !== null && $verified > 0) {
                            $ids[] = $verified;
                        }
                    }
                } elseif (is_string($value) && trim($value) !== '') {
                    $publicIds[] = trim($value);
                }
            }
            if ($publicIds !== []) {
                $ids = array_merge($ids, $this->teams->userIdsByPublicIds($publicIds, $orgId));
            }
            return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        }

        return [];
    }

    private function resolveParentId(array $input, array $actor): ?int
    {
        if (!empty($input['parent_public_id'])) {
            $parent = $this->teams->findByPublicId((string)$input['parent_public_id'], $this->organizationId($actor));
            if ($parent && $this->canView($parent, $actor)) {
                return (int)$parent['id'];
            }

            return null;
        }

        // TROPATTCRM-555: a raw integer parent_id must go through the same
        // organization-scoped existence + visibility check as parent_public_id
        // above, instead of being trusted as-is. Without this, an actor could
        // reference another organization's team as parent_id directly.
        if (isset($input['parent_id']) && (int)$input['parent_id'] > 0) {
            $parent = $this->teams->findById((int)$input['parent_id'], $this->organizationId($actor));
            if ($parent && $this->canView($parent, $actor)) {
                return (int)$parent['id'];
            }

            return null;
        }

        return null;
    }

    private function decorateTeam(array $team, array $actor): array
    {
        $team['can_manage'] = $this->canManage($team, $actor);
        return $team;
    }

    /** @return int[] */
    private function memberIds(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }
}
