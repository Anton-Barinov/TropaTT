<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Team\TeamRepository;
use Api\System\Library\Support\Ulid;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly UserRepository $users,
        private readonly TeamRepository $teams,
        private readonly ?NotificationService $notifications = null,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?ChatService $chats = null
    )
    {
    }

    public function list(array $filters, array $actor): array
    {
        $filters['accessible_team_public_ids'] = $this->accessibleTeamPublicIds($actor);

        $result = $this->projects->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        $items = (array)($result['items'] ?? []);
        $mode = (string)($result['mode'] ?? 'offset');
        $limit = (int)($result['limit'] ?? 20);
        $total = isset($result['total']) ? (int)$result['total'] : null;
        $page = (int)($result['page'] ?? 1);
        $nextCursor = isset($result['next_cursor']) ? (string)$result['next_cursor'] : null;
        $hasMore = (bool)($result['has_more'] ?? false);

        $meta = [
            'pagination_mode' => $mode,
        ];

        if ($mode === 'cursor') {
            $meta['cursor'] = [
                'next' => $nextCursor,
                'has_more' => $hasMore,
                'limit' => $limit,
            ];
        } else {
            $meta['pagination'] = [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil((int)$total / max(1, $limit)),
            ];
        }

        if (!empty($filters['updated_since'])) {
            $meta['sync'] = [
                'updated_since' => (string)$filters['updated_since'],
            ];
        }

        return [
            'items' => $items,
            'meta' => $meta,
        ];
    }

    public function create(array $input, array $actor): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('prj');
        $creatorUserId = (int)($actor['id'] ?? 0);
        $teamPublicId = $this->resolveTeamPublicId($input, $actor);
        $managerUserId = null;

        if (array_key_exists('manager_user_public_id', $input)) {
            $managerPublicId = trim((string)$input['manager_user_public_id']);
            if ($managerPublicId !== '') {
                $manager = $this->users->findByPublicId($managerPublicId);
                if ($manager && (int)($manager['is_active'] ?? 0) === 1) {
                    $managerUserId = (int)$manager['id'];
                }
            }
        } elseif (isset($input['manager_user_id'])) {
            $managerUserId = (int)$input['manager_user_id'];
        }

        $this->projects->create([
            'public_id' => $publicId,
            'title' => trim((string)$input['title']),
            'description' => trim((string)($input['description'] ?? '')),
            'status_code' => (string)($input['status'] ?? 'active'),
            'priority_code' => (string)($input['priority'] ?? 'normal'),
            'client_public_id' => (string)($input['client_public_id'] ?? ''),
            'manager_user_id' => $managerUserId,
            'team_public_id' => $teamPublicId,
            'created_by_user_id' => $creatorUserId,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ]);

        $project = $this->projects->findByPublicId($publicId);

        if (is_array($project)) {
            if ($managerUserId !== null && $managerUserId > 0) {
                $this->notifications?->notifyProjectManagerAssigned($project, $managerUserId, $actor);
            }

            $memberIds = $this->extractTeamMemberIdsByPublicId($teamPublicId);
            if ($memberIds !== []) {
                $this->notifications?->notifyProjectMembersAdded($project, $memberIds, $actor);
            }

            $this->chats?->ensureProjectChat($project, $actor);
        }

        return $project ?: ['public_id' => $publicId];
    }

    public function get(string $publicId, array $actor): ?array
    {
        $project = $this->projects->findByPublicId($publicId);
        if (!$project) {
            return null;
        }

        if (!$this->canAccess($project, $actor)) {
            return null;
        }

        return $project;
    }

    /** @return array<string,mixed>|null|'ROW_VERSION_CONFLICT' */
    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        $project = $this->projects->findByPublicId($publicId);
        if (!$project) {
            return null;
        }
        if (!$this->canManage($project, $actor)) {
            return null;
        }

        $beforeManagerUserId = (int)($project['manager_user_id'] ?? 0);
        $beforeTeamPublicId = trim((string)($project['team_public_id'] ?? ''));

        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($project['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('description', $input)) {
            $set['description'] = trim((string)$input['description']);
        }
        if (array_key_exists('status', $input)) {
            $set['status_code'] = (string)$input['status'];
        }
        if (array_key_exists('priority', $input)) {
            $set['priority_code'] = (string)$input['priority'];
        }
        if (array_key_exists('client_public_id', $input)) {
            $set['client_public_id'] = (string)$input['client_public_id'];
        }
        if (array_key_exists('manager_user_id', $input)) {
            $set['manager_user_id'] = $input['manager_user_id'] !== null ? (int)$input['manager_user_id'] : null;
        }
        if (array_key_exists('manager_user_public_id', $input)) {
            $managerPublicId = trim((string)$input['manager_user_public_id']);
            if ($managerPublicId === '') {
                $set['manager_user_id'] = null;
            } else {
                $manager = $this->users->findByPublicId($managerPublicId);
                if (!$manager || (int)($manager['is_active'] ?? 0) !== 1) {
                    return null;
                }
                $set['manager_user_id'] = (int)$manager['id'];
            }
        }
        if (array_key_exists('team_public_id', $input)) {
            $set['team_public_id'] = $this->resolveTeamPublicId($input, $actor);
        }
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        $this->projects->updateByPublicId($publicId, $set);
        $this->semanticIndex?->removeEntityDocument('project', $publicId);

        $updated = $this->projects->findByPublicId($publicId);
        if (!$updated || !$this->canAccess($updated, $actor)) {
            return null;
        }

        $afterManagerUserId = (int)($updated['manager_user_id'] ?? 0);
        if ($afterManagerUserId > 0 && $afterManagerUserId !== $beforeManagerUserId) {
            $this->notifications?->notifyProjectManagerAssigned($updated, $afterManagerUserId, $actor);
        }

        $afterTeamPublicId = trim((string)($updated['team_public_id'] ?? ''));
        if ($afterTeamPublicId !== '' && $afterTeamPublicId !== $beforeTeamPublicId) {
            $beforeMembers = $this->extractTeamMemberIdsByPublicId($beforeTeamPublicId);
            $afterMembers = $this->extractTeamMemberIdsByPublicId($afterTeamPublicId);
            $addedMembers = array_values(array_diff($afterMembers, $beforeMembers));
            if ($addedMembers !== []) {
                $this->notifications?->notifyProjectMembersAdded($updated, $addedMembers, $actor);
            }
        }

        $this->chats?->ensureProjectChat($updated, $actor);

        return $updated;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $project = $this->projects->findByPublicId($publicId);
        if (!$project) {
            return false;
        }
        if (!$this->canManage($project, $actor)) {
            return false;
        }

        $archived = $this->projects->archiveByPublicId($publicId, gmdate('Y-m-d H:i:s'));
        if ($archived) {
            $this->semanticIndex?->removeEntityDocument('project', $publicId);
        }

        return $archived;
    }

    /** @param array<string,mixed> $project */
    /** @param array<string,mixed> $actor */
    private function canAccess(array $project, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        return (int)($project['created_by_user_id'] ?? 0) === $actorId
            || (int)($project['manager_user_id'] ?? 0) === $actorId
            || (int)($project['team_manager_user_id'] ?? 0) === $actorId
            || in_array($actorId, $this->decodeTeamMemberIds($project['team_member_user_ids'] ?? null), true);
    }

    private function canManage(array $project, array $actor): bool
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return true;
        }

        $actorId = (int)($actor['id'] ?? 0);
        if ($actorId <= 0) {
            return false;
        }

        return (int)($project['created_by_user_id'] ?? 0) === $actorId
            || (int)($project['manager_user_id'] ?? 0) === $actorId
            || (int)($project['team_manager_user_id'] ?? 0) === $actorId;
    }

    /** @return string[] */
    private function accessibleTeamPublicIds(array $actor): array
    {
        if ((bool)($actor['is_root'] ?? false)) {
            return [];
        }

        return $this->teams->listAccessiblePublicIdsForUser((int)($actor['id'] ?? 0));
    }

    private function resolveTeamPublicId(array $input, array $actor): ?string
    {
        if (!array_key_exists('team_public_id', $input)) {
            return null;
        }

        $teamPublicId = trim((string)$input['team_public_id']);
        if ($teamPublicId === '') {
            return null;
        }

        if (!$this->teams->teamExists($teamPublicId)) {
            return null;
        }

        if ((bool)($actor['is_root'] ?? false)) {
            return $teamPublicId;
        }

        $accessibleTeamIds = $this->accessibleTeamPublicIds($actor);
        return in_array($teamPublicId, $accessibleTeamIds, true) ? $teamPublicId : null;
    }

    /** @return int[] */
    private function extractTeamMemberIdsByPublicId(?string $teamPublicId): array
    {
        $publicId = trim((string)$teamPublicId);
        if ($publicId === '') {
            return [];
        }

        $team = $this->teams->findByPublicId($publicId);
        if (!$team) {
            return [];
        }

        $raw = $team['member_user_ids'] ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $decoded), static fn(int $value): bool => $value > 0)));
    }

    /** @return int[] */
    private function decodeTeamMemberIds(mixed $raw): array
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
