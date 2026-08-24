<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Team\TeamRepository;
use Api\Model\Task\TaskKeyCounterRepository;
use Api\System\Library\Support\Ulid;
use PDOException;

final class ProjectService
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly UserRepository $users,
        private readonly TeamRepository $teams,
        private readonly ?NotificationService $notifications = null,
        private readonly ?AiSemanticIndexService $semanticIndex = null,
        private readonly ?ChatService $chats = null,
        private readonly ?TaskKeyService $taskKeys = null,
        private readonly ?TaskKeyCounterRepository $keyCounters = null,
        private readonly ?ExternalUserService $externalUsers = null
    )
    {
    }

    public function list(array $filters, array $actor): array
    {
        $filters['accessible_team_public_ids'] = $this->accessibleTeamPublicIds($actor);

        // RLS: external users can only see projects for their counterparty.
        // Fail closed. An external actor whose counterparty cannot be resolved
        // (missing/broken contact link, or the service not wired) must see
        // nothing. Previously the scoping filter was simply skipped in that
        // case, leaving the query unscoped and returning every counterparty's
        // projects to a client-portal user.
        $rlsScoped = false;
        if (!empty((int)($actor['is_external'] ?? 0))) {
            $actorId = (int)($actor['id'] ?? 0);
            $externalRole = $this->externalUsers ? $this->externalUsers->getExternalRole($actorId) : ExternalUserService::ROLE_OBSERVER;

            if ($externalRole === ExternalUserService::ROLE_EXECUTOR) {
                // Executor: explicit per-project grants, never a counterparty
                // filter. Empty grant set is handled by the repository (fail
                // closed on the mere presence of this filter key).
                $filters['executor_project_ids'] = $this->externalUsers
                    ? $this->externalUsers->getExecutorProjectIds($actorId)
                    : [];
            } else {
                $cpPublicId = $this->externalUsers
                    ? $this->externalUsers->getCounterpartyPublicId($actorId)
                    : '';

                if ($cpPublicId === '') {
                    return $this->emptyListResult($filters);
                }

                $filters['client_public_id'] = $cpPublicId;
            }
            $rlsScoped = true;
        }

        $result = $this->projects->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false),
            $rlsScoped
        );

        $items = (array)($result['items'] ?? []);
        // Normalize the per-project task counters computed by the repository and
        // derive the progress percentage shown in the projects list/cards.
        foreach ($items as &$item) {
            $total = (int)($item['total_tasks_count'] ?? 0);
            $done = (int)($item['done_tasks_count'] ?? 0);
            $item['total_tasks_count'] = $total;
            $item['done_tasks_count'] = $done;
            $item['open_tasks_count'] = max(0, $total - $done);
            $item['progress_percent'] = $total > 0 ? (int)round(($done / $total) * 100) : 0;
            $item = $this->sanitizeProject($item, $actor);
        }
        unset($item);
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

    /**
     * Empty, well-formed list envelope. Used when access scoping cannot be
     * satisfied, so callers get a normal (empty) page rather than an unscoped one.
     *
     * @param array<string,mixed> $filters
     * @return array<string,mixed>
     */
    private function emptyListResult(array $filters): array
    {
        $limit = (int)($filters['limit'] ?? 20) === 0 ? 0 : min(100, max(1, (int)($filters['limit'] ?? 20)));

        return [
            'items' => [],
            'meta' => [
                'pagination_mode' => 'offset',
                'pagination' => [
                    'page' => 1,
                    'limit' => $limit,
                    'total' => 0,
                    'pages' => 0,
                ],
            ],
        ];
    }

    /** @return array<string,mixed>|'PROJECT_TASK_PREFIX_ALREADY_EXISTS' */
    public function create(array $input, array $actor): array|string
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

        // Resolve task_key_prefix. The database remains the final authority: two
        // simultaneous requests can both observe a free prefix before either insert.
        $prefixWasGenerated = empty($input['task_key_prefix']);
        $taskKeyPrefix = null;
        if (!empty($input['task_key_prefix'])) {
            $taskKeyPrefix = $this->resolveTaskKeyPrefix((string)$input['task_key_prefix'], null);
        } else {
            $taskKeyPrefix = $this->taskKeys?->generateProjectPrefix((string)($input['title'] ?? ''));
            $taskKeyPrefix = $this->taskKeys?->ensureUniqueProjectPrefix($taskKeyPrefix ?? 'PRJ', null) ?? 'PRJ';
        }

        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                $this->projects->create([
                    'public_id' => $publicId,
                    'title' => trim((string)$input['title']),
                    'description' => trim((string)($input['description'] ?? '')),
                    'status_code' => (string)($input['status'] ?? 'active'),
                    'priority_code' => (string)($input['priority'] ?? 'normal'),
                    'client_public_id' => (string)($input['client_public_id'] ?? ''),
                    'task_key_prefix' => $taskKeyPrefix,
                    'manager_user_id' => $managerUserId,
                    'team_public_id' => $teamPublicId,
                    'created_by_user_id' => $creatorUserId,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'row_version' => 1,
                ]);
                break;
            } catch (PDOException $e) {
                error_log('[ProjectService::create] task_key_prefix conflict: ' . $e->getMessage());
                if (!$this->isTaskKeyPrefixDuplicate($e)) {
                    throw $e;
                }

                if (!$prefixWasGenerated || $attempt === 2) {
                    return 'PROJECT_TASK_PREFIX_ALREADY_EXISTS';
                }

                $taskKeyPrefix = $this->taskKeys?->ensureUniqueProjectPrefix($taskKeyPrefix ?? 'PRJ', null) ?? 'PRJ';
            }
        }

        $project = $this->projects->findByPublicId($publicId);

        if (is_array($project)) {
            // Initialize task key counter for this project
            if ($taskKeyPrefix !== null && $this->keyCounters !== null) {
                $this->keyCounters->ensureProjectCounter((int)$project['id'], $taskKeyPrefix);
            }

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

    private function isTaskKeyPrefixDuplicate(PDOException $exception): bool
    {
        $message = strtolower($exception->getMessage());
        return (string)$exception->getCode() === '23000'
            && (str_contains($message, 'task_key_prefix') || str_contains($message, 'uq_projects_task_key_prefix'));
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

        return $this->sanitizeProject($project, $actor);
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

        $expectedRowVersion = null;
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($project['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
            if ($expected > 0) {
                $expectedRowVersion = $expected;
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
            // ТЗ 7.3: нельзя завершить проект, пока есть незакрытые задачи
            $oldStatus = (string)($project['status_code'] ?? '');
            if ($set['status_code'] !== $oldStatus && in_array($set['status_code'], ['done', 'completed'], true)) {
                $openTasks = $this->projects->countOpenTasksByProjectId((int)$project['id']);
                if ($openTasks > 0) {
                    return ['error' => 'PROJECT_HAS_OPEN_TASKS', 'open_task_count' => $openTasks];
                }
            }
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

        if (array_key_exists('task_key_prefix', $input)) {
            $resolved = $this->resolveTaskKeyPrefix((string)$input['task_key_prefix'], $publicId);
            if ($resolved === null) {
                return null;
            }
            $set['task_key_prefix'] = $resolved;
        }

        $updated = $this->projects->updateByPublicId($publicId, $set, $expectedRowVersion);
        if (!$updated && $expectedRowVersion !== null) {
            return 'ROW_VERSION_CONFLICT';
        }
        $this->semanticIndex?->removeEntityDocument('project', $publicId);

        // Sync counter prefix when project prefix changes
        if (array_key_exists('task_key_prefix', $set) && $this->keyCounters !== null) {
            $projectId = (int)($project['id'] ?? 0);
            if ($projectId > 0) {
                $this->keyCounters->ensureProjectCounter($projectId, (string)$set['task_key_prefix']);
            }
        }

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

        // RLS: external users can only access projects for their counterparty
        // (observer) or their explicitly granted projects (executor).
        if (!empty((int)($actor['is_external'] ?? 0))) {
            // Fail closed on a missing service too: without it the actor would
            // fall through to the internal ownership checks below and be judged
            // as if they were an employee.
            if (!$this->externalUsers) {
                return false;
            }

            if ($this->externalUsers->getExternalRole($actorId) === ExternalUserService::ROLE_EXECUTOR) {
                return $this->externalUsers->hasExecutorProjectAccess($actorId, (int)($project['id'] ?? 0));
            }

            $cpPublicId = $this->externalUsers->getCounterpartyPublicId($actorId);
            if ($cpPublicId === '') {
                return false;
            }
            return (string)($project['client_public_id'] ?? '') === $cpPublicId;
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

    private function resolveTaskKeyPrefix(string $rawPrefix, ?string $exceptProjectPublicId): ?string
    {
        if ($this->taskKeys === null) {
            return null;
        }

        $normalized = $this->taskKeys->normalizePrefix($rawPrefix);

        if ($normalized === '') {
            return null;
        }

        if (!$this->taskKeys->isValidPrefix($normalized)) {
            return null;
        }

        if ($this->taskKeys->isReservedPrefix($normalized)) {
            return null;
        }

        // Check for duplicate prefix - return null so controller can return PROJECT_TASK_PREFIX_ALREADY_EXISTS
        if ($this->projects->taskKeyPrefixExists($normalized, $exceptProjectPublicId)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Strip internal staff identities from project data for external users.
     * Mirrors TaskService::sanitizeTask() so external users cannot see staff
     * public IDs, names, or internal IDs even through the projects endpoint (M-6).
     */
    private function sanitizeProject(array $project, array $actor): array
    {
        if (empty((int)($actor['is_external'] ?? 0))) {
            return $project;
        }

        unset($project['manager_user_public_id'], $project['manager_user_name']);
        unset($project['creator_user_public_id'], $project['creator_user_name']);
        unset($project['team_manager_user_id'], $project['team_member_user_ids']);
        unset($project['manager_user_id'], $project['created_by_user_id']);
        unset($project['id']);

        return $project;
    }
}
