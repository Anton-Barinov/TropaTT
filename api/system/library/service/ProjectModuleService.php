<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Project\ProjectModuleRepository;
use Api\Model\Project\ProjectModuleTaskRepository;
use Api\Model\Project\ProjectModuleMemberRepository;
use Api\Model\Project\ProjectModuleLinkRepository;
use Api\Model\Project\ProjectRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Support\Ulid;

final class ProjectModuleService
{
    private const ALLOWED_STATUSES = ['backlog', 'planned', 'in_progress', 'paused', 'completed', 'cancelled', 'archived'];
    private const VALID_LINK_TYPES = ['doc', 'design', 'repository', 'api', 'file', 'client', 'analytics', 'other'];

    public function __construct(
        private readonly ProjectModuleRepository $modules,
        private readonly ProjectModuleTaskRepository $moduleTasks,
        private readonly ProjectModuleMemberRepository $moduleMembers,
        private readonly ProjectModuleLinkRepository $moduleLinks,
        private readonly ProjectRepository $projects,
        private readonly TaskRepository $tasks,
        private readonly TaskService $taskService,
    ) {
    }

    // ── CRUD ──

    public function list(array $filters, array $actor): array
    {
        $result = $this->modules->list(
            $filters,
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false)
        );

        $items = $result['items'] ?? [];
        $total = (int)($result['total'] ?? 0);
        $limit = (int)($result['limit'] ?? 20);
        $page = (int)($result['page'] ?? 1);

        $enriched = [];
        foreach ($items as $item) {
            $enriched[] = $this->enrichModule($item);
        }

        return [
            'items' => $enriched,
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

    public function create(array $input, array $actor): array|string
    {
        // Validate title
        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            return 'PROJECT_MODULE_TITLE_REQUIRED';
        }
        if (mb_strlen($title) > 255) {
            return 'PROJECT_MODULE_TITLE_TOO_LONG';
        }

        // Validate project
        $projectPublicId = trim((string)($input['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return 'PROJECT_MODULE_PROJECT_REQUIRED';
        }
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_MODULE_PROJECT_NOT_FOUND';
        }
        $projectId = (int)$project['id'];

        // Validate lead
        $leadUserId = null;
        if (!empty($input['lead_user_public_id'])) {
            $leadId = $this->modules->userIdByPublicId((string)$input['lead_user_public_id']);
            if ($leadId === null) {
                return 'PROJECT_MODULE_LEAD_NOT_FOUND';
            }
            $leadUserId = $leadId;
        }

        // Validate dates
        $startAt = !empty($input['start_at']) ? (string)$input['start_at'] : null;
        $targetAt = !empty($input['target_at']) ? (string)$input['target_at'] : null;

        if ($startAt !== null && !strtotime($startAt)) {
            return 'PROJECT_MODULE_INVALID_START_AT';
        }
        if ($targetAt !== null && !strtotime($targetAt)) {
            return 'PROJECT_MODULE_INVALID_TARGET_AT';
        }
        if ($startAt !== null && $targetAt !== null && $targetAt < $startAt) {
            return 'PROJECT_MODULE_INVALID_DATE_RANGE';
        }

        // Validate status
        $status = (string)($input['status'] ?? 'planned');
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return 'PROJECT_MODULE_INVALID_STATUS';
        }

        // Validate color (optional)
        $color = isset($input['color']) ? (string)$input['color'] : null;
        if ($color !== null && $color !== '' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return 'PROJECT_MODULE_INVALID_COLOR';
        }

        // Validate icon (optional)
        $icon = isset($input['icon']) ? (string)$input['icon'] : null;
        if ($icon !== null && $icon !== '' && !preg_match('/^[a-z0-9-]{1,64}$/', $icon)) {
            return 'PROJECT_MODULE_INVALID_ICON';
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('pmod');
        $creatorUserId = (int)($actor['id'] ?? 0);

        $this->modules->create([
            'public_id' => $publicId,
            'project_id' => $projectId,
            'title' => $title,
            'description' => trim((string)($input['description'] ?? '')),
            'status' => $status,
            'lead_user_id' => $leadUserId,
            'start_at' => $startAt,
            'target_at' => $targetAt,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => (int)($input['sort_order'] ?? 65535),
            'created_by_user_id' => $creatorUserId,
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ]);

        // Auto-add lead as member
        if ($leadUserId !== null) {
            $memberPublicId = Ulid::generate('pmm');
            $this->moduleMembers->addMember([
                'public_id' => $memberPublicId,
                'module_id' => $projectId, // Will be updated after we get the actual module ID
                'user_id' => $leadUserId,
                'role_code' => 'lead',
                'active_key' => null,
                'added_by_user_id' => $creatorUserId,
                'added_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $this->get($publicId, $actor);
    }

    public function get(string $modulePublicId, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        // Check project access
        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        return $this->enrichModule($module);
    }

    public function update(string $modulePublicId, array $input, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        // Check project access
        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        // Row version check
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($module['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $set = [];

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
            if ($set['title'] === '') {
                return 'PROJECT_MODULE_TITLE_REQUIRED';
            }
        }

        if (array_key_exists('description', $input)) {
            $set['description'] = trim((string)$input['description']);
        }

        if (array_key_exists('lead_user_public_id', $input)) {
            $val = (string)$input['lead_user_public_id'];
            if ($val === '') {
                $set['lead_user_id'] = null;
            } else {
                $leadId = $this->modules->userIdByPublicId($val);
                if ($leadId === null) {
                    return 'PROJECT_MODULE_LEAD_NOT_FOUND';
                }
                $set['lead_user_id'] = $leadId;

                // Auto-add lead as member if not already
                $moduleId = (int)$module['id'];
                if (!$this->moduleMembers->memberAlreadyExists($moduleId, $leadId)) {
                    $now = gmdate('Y-m-d H:i:s');
                    $memberPublicId = Ulid::generate('pmm');
                    $activeKey = 'module:' . $moduleId . ':user:' . $leadId;
                    $this->moduleMembers->addMember([
                        'public_id' => $memberPublicId,
                        'module_id' => $moduleId,
                        'user_id' => $leadId,
                        'role_code' => 'lead',
                        'active_key' => $activeKey,
                        'added_by_user_id' => (int)($actor['id'] ?? 0),
                        'added_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        if (array_key_exists('start_at', $input)) {
            $val = $input['start_at'];
            $set['start_at'] = $val !== '' ? (string)$val : null;
            if ($set['start_at'] !== null && !strtotime($set['start_at'])) {
                return 'PROJECT_MODULE_INVALID_START_AT';
            }
        }

        if (array_key_exists('target_at', $input)) {
            $val = $input['target_at'];
            $set['target_at'] = $val !== '' ? (string)$val : null;
            if ($set['target_at'] !== null && !strtotime($set['target_at'])) {
                return 'PROJECT_MODULE_INVALID_TARGET_AT';
            }
        }

        // Validate date range
        $finalStart = $set['start_at'] ?? $module['start_at'];
        $finalTarget = $set['target_at'] ?? $module['target_at'];
        if ($finalStart !== null && $finalTarget !== null && $finalTarget < $finalStart) {
            return 'PROJECT_MODULE_INVALID_DATE_RANGE';
        }

        if (array_key_exists('color', $input)) {
            $color = (string)$input['color'];
            if ($color === '') {
                $set['color'] = null;
            } else {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    return 'PROJECT_MODULE_INVALID_COLOR';
                }
                $set['color'] = $color;
            }
        }

        if (array_key_exists('icon', $input)) {
            $icon = (string)$input['icon'];
            if ($icon === '') {
                $set['icon'] = null;
            } else {
                if (!preg_match('/^[a-z0-9-]{1,64}$/', $icon)) {
                    return 'PROJECT_MODULE_INVALID_ICON';
                }
                $set['icon'] = $icon;
            }
        }

        if (array_key_exists('status', $input)) {
            $status = (string)$input['status'];
            if (!in_array($status, self::ALLOWED_STATUSES, true)) {
                return 'PROJECT_MODULE_INVALID_STATUS';
            }
            $set['status'] = $status;

            if ($status === 'completed' && !$module['completed_at']) {
                $set['completed_at'] = gmdate('Y-m-d H:i:s');
            }
            if ($status !== 'completed') {
                $set['completed_at'] = null;
            }
        }

        if (array_key_exists('sort_order', $input)) {
            $set['sort_order'] = (int)$input['sort_order'];
        }

        if (array_key_exists('meta_json', $input)) {
            $set['meta_json'] = is_array($input['meta_json']) ? json_encode($input['meta_json']) : null;
        }

        $set['updated_by_user_id'] = (int)($actor['id'] ?? 0);
        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        if (count($set) <= 2) {
            // Only updated_by_user_id and updated_at — nothing changed
            return $this->get($modulePublicId, $actor);
        }

        $this->modules->updateByPublicId($modulePublicId, $set);

        return $this->get($modulePublicId, $actor);
    }

    public function archive(string $modulePublicId, array $actor): bool|string
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $this->modules->archiveByPublicId($modulePublicId, gmdate('Y-m-d H:i:s'));
        return true;
    }

    public function delete(string $modulePublicId, array $actor): bool|string
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $this->modules->softDeleteByPublicId($modulePublicId, gmdate('Y-m-d H:i:s'));
        return true;
    }

    // ── Tasks ──

    public function tasks(string $modulePublicId, array $filters, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        return $this->moduleTasks->listTasksByModuleId((int)$module['id'], $filters);
    }

    public function addTasks(string $modulePublicId, array $input, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $moduleId = (int)$module['id'];
        $moduleProjectId = (int)$module['project_id'];
        $actorUserId = (int)($actor['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $taskPublicIds = (array)($input['task_public_ids'] ?? []);
        $taskKeys = (array)($input['task_keys'] ?? []);

        $allPublicIds = [];
        foreach ($taskPublicIds as $pid) {
            $pid = trim((string)$pid);
            if ($pid !== '') {
                $allPublicIds[] = $pid;
            }
        }

        if ($taskKeys !== [] && method_exists($this->taskService, 'resolveKeys')) {
            $resolved = $this->taskService->resolveKeys($taskKeys);
            if (is_array($resolved)) {
                foreach ($resolved as $task) {
                    $allPublicIds[] = (string)$task['public_id'];
                }
            }
        }

        if ($allPublicIds === []) {
            return 'PROJECT_MODULE_TASK_TARGET_REQUIRED';
        }

        $allPublicIds = array_slice(array_unique($allPublicIds), 0, 100);

        $added = [];
        $errors = [];

        foreach ($allPublicIds as $taskPublicId) {
            $taskId = $this->moduleTasks->taskIdByPublicId($taskPublicId);
            if ($taskId === null) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'PROJECT_MODULE_TASK_NOT_FOUND'];
                continue;
            }

            $task = $this->tasks->findByPublicId($taskPublicId);
            if (!$task) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'PROJECT_MODULE_TASK_NOT_FOUND'];
                continue;
            }

            // Check project match
            $taskProjectId = (int)($task['project_id'] ?? 0);
            if ($taskProjectId > 0 && $taskProjectId !== $moduleProjectId) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'PROJECT_MODULE_TASK_PROJECT_MISMATCH'];
                continue;
            }

            // Check duplicate
            if ($this->moduleTasks->taskAlreadyInModule($moduleId, $taskId)) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'PROJECT_MODULE_TASK_ALREADY_EXISTS'];
                continue;
            }

            $moduleTaskPublicId = Ulid::generate('pmt');
            $activeKey = 'module:' . $moduleId . ':task:' . $taskId;

            $this->moduleTasks->addTask([
                'public_id' => $moduleTaskPublicId,
                'module_id' => $moduleId,
                'task_id' => $taskId,
                'active_key' => $activeKey,
                'added_by_user_id' => $actorUserId,
                'added_at' => $now,
                'sort_order' => 65535,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $added[] = $taskPublicId;
        }

        if ($added === []) {
            return $errors;
        }

        return [
            'added' => $added,
            'errors' => $errors,
        ];
    }

    public function removeTask(string $modulePublicId, string $taskPublicId, array $actor): bool|string
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $moduleId = (int)$module['id'];
        $taskId = $this->moduleTasks->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return 'PROJECT_MODULE_TASK_NOT_FOUND';
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        return $this->moduleTasks->removeTask($moduleId, $taskId, $actorUserId, $now);
    }

    // ── Members ──

    public function members(string $modulePublicId, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        return $this->moduleMembers->listByModuleId((int)$module['id']);
    }

    public function addMembers(string $modulePublicId, array $input, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $moduleId = (int)$module['id'];
        $actorUserId = (int)($actor['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $members = (array)($input['members'] ?? []);
        if ($members === []) {
            return 'PROJECT_MODULE_MEMBER_TARGET_REQUIRED';
        }

        $members = array_slice($members, 0, 100);
        $added = [];
        $errors = [];

        foreach ($members as $member) {
            $userPublicId = (string)($member['user_public_id'] ?? '');
            $roleCode = (string)($member['role_code'] ?? 'member');

            if ($userPublicId === '') {
                $errors[] = ['error' => 'PROJECT_MODULE_MEMBER_NOT_FOUND'];
                continue;
            }

            $userId = $this->moduleMembers->userIdByPublicId($userPublicId);
            if ($userId === null) {
                $errors[] = ['user_public_id' => $userPublicId, 'error' => 'PROJECT_MODULE_MEMBER_NOT_FOUND'];
                continue;
            }

            if ($this->moduleMembers->memberAlreadyExists($moduleId, $userId)) {
                $errors[] = ['user_public_id' => $userPublicId, 'error' => 'PROJECT_MODULE_MEMBER_ALREADY_EXISTS'];
                continue;
            }

            $memberPublicId = Ulid::generate('pmm');
            $activeKey = 'module:' . $moduleId . ':user:' . $userId;

            $this->moduleMembers->addMember([
                'public_id' => $memberPublicId,
                'module_id' => $moduleId,
                'user_id' => $userId,
                'role_code' => $roleCode,
                'active_key' => $activeKey,
                'added_by_user_id' => $actorUserId,
                'added_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $added[] = $userPublicId;
        }

        return [
            'added' => $added,
            'errors' => $errors,
        ];
    }

    public function removeMember(string $modulePublicId, string $userPublicId, array $actor): bool|string
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $moduleId = (int)$module['id'];
        $userId = $this->moduleMembers->userIdByPublicId($userPublicId);
        if ($userId === null) {
            return 'PROJECT_MODULE_MEMBER_NOT_FOUND';
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        return $this->moduleMembers->removeMember($moduleId, $userId, $actorUserId, $now);
    }

    // ── Links ──

    public function links(string $modulePublicId, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $links = $this->moduleLinks->listByModuleId((int)$module['id']);

        // Clean internal fields
        foreach ($links as &$link) {
            unset($link['id'], $link['module_id'], $link['created_by_user_id']);
        }
        unset($link);

        return $links;
    }

    public function addLink(string $modulePublicId, array $input, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $title = trim((string)($input['title'] ?? ''));
        if ($title === '') {
            return 'PROJECT_MODULE_LINK_TITLE_REQUIRED';
        }

        $url = trim((string)($input['url'] ?? ''));
        if ($url === '') {
            return 'PROJECT_MODULE_LINK_URL_REQUIRED';
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? '';
        if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
            return 'PROJECT_MODULE_LINK_INVALID_URL';
        }

        if (preg_match('/^(javascript|data|file|vbscript):/i', $url)) {
            return 'PROJECT_MODULE_LINK_INVALID_URL';
        }

        $linkType = (string)($input['link_type'] ?? 'other');
        if (!in_array($linkType, self::VALID_LINK_TYPES, true)) {
            $linkType = 'other';
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('pml');
        $actorUserId = (int)($actor['id'] ?? 0);

        $this->moduleLinks->create([
            'public_id' => $publicId,
            'module_id' => (int)$module['id'],
            'title' => $title,
            'url' => $url,
            'link_type' => $linkType,
            'created_by_user_id' => $actorUserId,
            'sort_order' => (int)($input['sort_order'] ?? 65535),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->moduleLinks->findByPublicId($publicId);
    }

    public function updateLink(string $linkPublicId, array $input, array $actor): array|string|null
    {
        $link = $this->moduleLinks->findByPublicId($linkPublicId);
        if (!$link) {
            return 'PROJECT_MODULE_LINK_NOT_FOUND';
        }

        // Check module access
        $module = $this->modules->findById((int)$link['module_id']);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $set = [];

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
            if ($set['title'] === '') {
                return 'PROJECT_MODULE_LINK_TITLE_REQUIRED';
            }
        }

        if (array_key_exists('url', $input)) {
            $url = trim((string)$input['url']);
            if ($url === '') {
                return 'PROJECT_MODULE_LINK_URL_REQUIRED';
            }
            $parsed = parse_url($url);
            $scheme = $parsed['scheme'] ?? '';
            if (!in_array(strtolower($scheme), ['http', 'https'], true)) {
                return 'PROJECT_MODULE_LINK_INVALID_URL';
            }
            if (preg_match('/^(javascript|data|file|vbscript):/i', $url)) {
                return 'PROJECT_MODULE_LINK_INVALID_URL';
            }
            $set['url'] = $url;
        }

        if (array_key_exists('link_type', $input)) {
            $linkType = (string)$input['link_type'];
            $set['link_type'] = in_array($linkType, self::VALID_LINK_TYPES, true) ? $linkType : 'other';
        }

        if (array_key_exists('sort_order', $input)) {
            $set['sort_order'] = (int)$input['sort_order'];
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');

        if (count($set) <= 1) {
            return $this->moduleLinks->findByPublicId($linkPublicId);
        }

        $this->moduleLinks->updateByPublicId($linkPublicId, $set);

        return $this->moduleLinks->findByPublicId($linkPublicId);
    }

    public function deleteLink(string $linkPublicId, array $actor): bool|string
    {
        $link = $this->moduleLinks->findByPublicId($linkPublicId);
        if (!$link) {
            return 'PROJECT_MODULE_LINK_NOT_FOUND';
        }

        $module = $this->modules->findById((int)$link['module_id']);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $this->moduleLinks->softDeleteByPublicId($linkPublicId, gmdate('Y-m-d H:i:s'));
        return true;
    }

    // ── Summary ──

    public function summary(string $modulePublicId, array $actor): array|string|null
    {
        $module = $this->modules->findByPublicId($modulePublicId);
        if (!$module) {
            return 'PROJECT_MODULE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$module['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_MODULE_FORBIDDEN';
        }

        $summary = $this->moduleTasks->moduleSummary((int)$module['id']);

        return ['summary' => $summary];
    }

    // ── Private ──

    private function enrichModule(array $module): array
    {
        $total = max(0, (int)($module['tasks_count'] ?? 0));
        $completed = 0;

        // Calculate open_tasks_count from the counts
        if ($total > 0 && isset($module['completed_tasks_count'])) {
            $completed = max(0, (int)$module['completed_tasks_count']);
        }

        $module['progress_percent'] = $total > 0 ? (int)round(($completed / $total) * 100) : 0;
        $module['open_tasks_count'] = $total - $completed;

        // Clean internal fields
        unset($module['id']);
        unset($module['project_id']);
        unset($module['lead_user_id']);
        unset($module['created_by_user_id']);
        unset($module['updated_by_user_id']);
        unset($module['meta_json']);
        unset($module['progress_snapshot_json']);

        return $module;
    }
}
