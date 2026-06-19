<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Cycle\CycleTaskRepository;
use Api\Model\Cycle\WorkCycleRepository;
use Api\Model\Cycle\CycleSnapshotRepository;
use Api\Model\Task\TaskRepository;
use Api\System\Library\Support\Ulid;

final class WorkCycleService
{
    private const ALLOWED_STATUSES = ['planned', 'active', 'completed', 'archived'];
    private const VALID_TRANSITIONS = [
        'planned' => ['active', 'archived'],
        'active' => ['completed', 'archived'],
        'completed' => ['archived'],
        'archived' => ['planned', 'active'],
    ];

    public function __construct(
        private readonly WorkCycleRepository $cycles,
        private readonly CycleTaskRepository $cycleTasks,
        private readonly CycleSnapshotRepository $snapshots,
        private readonly TaskRepository $tasks,
        private readonly TaskService $taskService,
        private readonly ProjectService $projects,
        private readonly ?TaskActivityService $activity = null,
    )
    {
    }

    public function list(array $filters, array $actor): array
    {
        $result = $this->cycles->list(
            $filters,
            (int)($actor['id'] ?? 0),
            $this->isCycleAdmin($actor)
        );

        $items = $result['items'] ?? [];
        $total = (int)($result['total'] ?? 0);
        $limit = (int)($result['limit'] ?? 20);
        $page = (int)($result['page'] ?? 1);

        $enriched = [];
        foreach ($items as $item) {
            $enriched[] = $this->enrichCycle($item);
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
            return 'CYCLE_TITLE_REQUIRED';
        }
        if (mb_strlen($title) > 255) {
            return 'CYCLE_TITLE_TOO_LONG';
        }

        // Validate project
        $projectPublicId = trim((string)($input['project_public_id'] ?? ''));
        if ($projectPublicId === '') {
            return 'CYCLE_PROJECT_REQUIRED';
        }
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'CYCLE_PROJECT_NOT_FOUND';
        }
        $projectId = (int)$project['id'];

        // Validate owner
        $ownerUserId = null;
        if (!empty($input['owner_user_public_id'])) {
            $ownerId = $this->cycles->userIdByPublicId((string)$input['owner_user_public_id']);
            if ($ownerId === null) {
                return 'CYCLE_OWNER_NOT_FOUND';
            }
            $ownerUserId = $ownerId;
        }

        // Validate dates
        $startAt = !empty($input['start_at']) ? (string)$input['start_at'] : null;
        $endAt = !empty($input['end_at']) ? (string)$input['end_at'] : null;

        if ($startAt !== null && !strtotime($startAt)) {
            return 'CYCLE_INVALID_START_AT';
        }
        if ($endAt !== null && !strtotime($endAt)) {
            return 'CYCLE_INVALID_END_AT';
        }
        if ($startAt !== null && $endAt !== null && $endAt < $startAt) {
            return 'CYCLE_INVALID_DATE_RANGE';
        }

        // Validate status
        $status = (string)($input['status'] ?? 'planned');
        if (!in_array($status, self::ALLOWED_STATUSES, true)) {
            return 'CYCLE_INVALID_STATUS';
        }

        if ($status === 'active' && $startAt === null) {
            $startAt = gmdate('Y-m-d H:i:s');
        }

        $now = gmdate('Y-m-d H:i:s');
        $publicId = Ulid::generate('cyc');
        $creatorUserId = (int)($actor['id'] ?? 0);

        $this->cycles->create([
            'public_id' => $publicId,
            'project_id' => $projectId,
            'title' => $title,
            'description' => trim((string)($input['description'] ?? '')),
            'goal' => trim((string)($input['goal'] ?? '')),
            'status' => $status,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'timezone' => (string)($input['timezone'] ?? 'UTC'),
            'owner_user_id' => $ownerUserId,
            'created_by_user_id' => $creatorUserId,
            'sort_order' => (int)($input['sort_order'] ?? 65535),
            'created_at' => $now,
            'updated_at' => $now,
            'row_version' => 1,
        ]);

        $cycle = $this->cycles->findByPublicId($publicId);

        // Activity event
        if ($cycle && $this->activity !== null) {
            $this->activity->recordSystemEvent(
                ['public_id' => $publicId, 'id' => 0],
                'cycle.created',
                [
                    'title' => $title,
                    'project_public_id' => $projectPublicId,
                    'status' => $status,
                ],
                ['source_type' => $input['source_type'] ?? 'web']
            );
        }

        return $cycle ?: ['public_id' => $publicId];
    }

    public function get(string $cyclePublicId, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        if (!$this->canViewCycle($cycle, $actor)) {
            return 'CYCLE_FORBIDDEN';
        }

        return $this->enrichCycle($cycle);
    }

    public function update(string $cyclePublicId, array $input, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        // Check project access
        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        // Row version check
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($cycle['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $set = [];

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
            if ($set['title'] === '') {
                return 'CYCLE_TITLE_REQUIRED';
            }
        }

        if (array_key_exists('description', $input)) {
            $set['description'] = trim((string)$input['description']);
        }

        if (array_key_exists('goal', $input)) {
            $set['goal'] = trim((string)$input['goal']);
        }

        if (array_key_exists('start_at', $input)) {
            $val = $input['start_at'];
            $set['start_at'] = $val !== '' ? (string)$val : null;
            if ($set['start_at'] !== null && !strtotime($set['start_at'])) {
                return 'CYCLE_INVALID_START_AT';
            }
        }

        if (array_key_exists('end_at', $input)) {
            $val = $input['end_at'];
            $set['end_at'] = $val !== '' ? (string)$val : null;
            if ($set['end_at'] !== null && !strtotime($set['end_at'])) {
                return 'CYCLE_INVALID_END_AT';
            }
        }

        // Validate date range
        $finalStart = $set['start_at'] ?? $cycle['start_at'];
        $finalEnd = $set['end_at'] ?? $cycle['end_at'];
        if ($finalStart !== null && $finalEnd !== null && $finalEnd < $finalStart) {
            return 'CYCLE_INVALID_DATE_RANGE';
        }

        if (array_key_exists('timezone', $input)) {
            $set['timezone'] = (string)$input['timezone'];
        }

        if (array_key_exists('owner_user_public_id', $input)) {
            $val = (string)$input['owner_user_public_id'];
            if ($val === '') {
                $set['owner_user_id'] = null;
            } else {
                $ownerId = $this->cycles->userIdByPublicId($val);
                if ($ownerId === null) {
                    return 'CYCLE_OWNER_NOT_FOUND';
                }
                $set['owner_user_id'] = $ownerId;
            }
        }

        if (array_key_exists('sort_order', $input)) {
            $set['sort_order'] = (int)$input['sort_order'];
        }

        if (array_key_exists('meta_json', $input)) {
            $set['meta_json'] = is_array($input['meta_json']) ? json_encode($input['meta_json']) : null;
        }

        // Don't allow status change via regular update
        if (array_key_exists('status', $input)) {
            return 'CYCLE_INVALID_STATUS_TRANSITION';
        }

        if ($set === []) {
            return $this->get($cyclePublicId, $actor);
        }

        $this->cycles->updateByPublicId($cyclePublicId, $set);

        // Activity
        if ($this->activity !== null) {
            $this->activity->recordSystemEvent(
                $cycle,
                'cycle.updated',
                ['changes' => array_keys($set)],
                ['source_type' => $input['source_type'] ?? 'web']
            );
        }

        return $this->get($cyclePublicId, $actor);
    }

    public function delete(string $cyclePublicId, array $actor): bool|string
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        $this->cycles->softDeleteByPublicId($cyclePublicId, gmdate('Y-m-d H:i:s'));
        return true;
    }

    public function start(string $cyclePublicId, array $input, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        if ((string)$cycle['status'] !== 'planned') {
            return 'CYCLE_INVALID_STATUS_TRANSITION';
        }

        // Row version check
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($cycle['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $set = [
            'status' => 'active',
        ];

        if ($cycle['start_at'] === null) {
            $set['start_at'] = gmdate('Y-m-d H:i:s');
        }

        $this->cycles->updateByPublicId($cyclePublicId, $set);

        // Activity
        if ($this->activity !== null) {
            $this->activity->recordSystemEvent(
                $cycle,
                'cycle.started',
                [],
                ['source_type' => $input['source_type'] ?? 'web']
            );
        }

        return $this->get($cyclePublicId, $actor);
    }

    public function complete(string $cyclePublicId, array $input, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        if ((string)$cycle['status'] === 'archived') {
            return 'CYCLE_CANNOT_COMPLETE_ARCHIVED';
        }

        if ((string)$cycle['status'] === 'completed') {
            return 'CYCLE_INVALID_STATUS_TRANSITION';
        }

        // Row version check
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($cycle['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $actorUserId = (int)($actor['id'] ?? 0);

        // Create final snapshot
        $cycleId = (int)$cycle['id'];
        $summary = $this->cycleTasks->cycleSummary($cycleId);
        $this->snapshots->createOrUpdateDailySnapshot($cycleId, gmdate('Y-m-d'), $summary);

        // Handle unfinished tasks
        $unfinishedAction = (string)($input['unfinished_action'] ?? 'leave');
        $transferred = [];

        if ($unfinishedAction === 'move') {
            $targetCyclePublicId = (string)($input['target_cycle_public_id'] ?? '');
            if ($targetCyclePublicId === '') {
                return 'CYCLE_TARGET_CYCLE_REQUIRED';
            }

            $targetCycle = $this->cycles->findByPublicId($targetCyclePublicId);
            if (!$targetCycle) {
                return 'CYCLE_TARGET_CYCLE_NOT_FOUND';
            }

            if (in_array((string)$targetCycle['status'], ['completed', 'archived'], true)) {
                return 'CYCLE_TARGET_CYCLE_INVALID';
            }

            $transferred = $this->cycleTasks->moveUnfinishedTasks($cycleId, (int)$targetCycle['id'], $actorUserId);
        } elseif ($unfinishedAction === 'remove') {
            // Remove all unfinished from cycle
            $unfinished = $this->cycleTasks->listUnfinishedTasks($cycleId);
            foreach ($unfinished as $task) {
                $this->cycleTasks->removeTask($cycleId, (int)$task['task_id'], $actorUserId, $now);
            }
        } // 'leave' — do nothing, tasks stay in completed cycle

        // Update cycle
        $this->cycles->updateByPublicId($cyclePublicId, [
            'status' => 'completed',
            'completed_by_user_id' => $actorUserId,
            'completed_at' => $now,
        ]);

        // Activity
        if ($this->activity !== null) {
            $this->activity->recordSystemEvent(
                $cycle,
                'cycle.completed',
                [
                    'unfinished_action' => $unfinishedAction,
                    'transferred_count' => count($transferred),
                ],
                ['source_type' => 'web']
            );
        }

        return $this->get($cyclePublicId, $actor);
    }

    public function reopen(string $cyclePublicId, array $input, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        $status = (string)$cycle['status'];
        if (!in_array($status, ['completed', 'archived'], true)) {
            return 'CYCLE_INVALID_STATUS_TRANSITION';
        }

        // Row version check
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($cycle['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $this->cycles->updateByPublicId($cyclePublicId, [
            'status' => 'active',
            'completed_by_user_id' => null,
            'completed_at' => null,
        ]);

        // Activity
        if ($this->activity !== null) {
            $this->activity->recordSystemEvent(
                $cycle,
                'cycle.started',
                ['reopened' => true],
                ['source_type' => 'web']
            );
        }

        return $this->get($cyclePublicId, $actor);
    }

    public function archive(string $cyclePublicId, array $input, array $actor): bool|string
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        if ((string)$cycle['status'] === 'completed' && empty($actor['is_root'])) {
            return 'CYCLE_CANNOT_ARCHIVE_COMPLETED_WITHOUT_CONFIRMATION';
        }

        // Row version check
        if (array_key_exists('row_version', $input)) {
            $expected = (int)$input['row_version'];
            $current = (int)($cycle['row_version'] ?? 0);
            if ($expected > 0 && $expected !== $current) {
                return 'ROW_VERSION_CONFLICT';
            }
        }

        $this->cycles->archiveByPublicId($cyclePublicId, gmdate('Y-m-d H:i:s'));

        // Activity
        if ($this->activity !== null) {
            $this->activity->recordSystemEvent(
                $cycle,
                'cycle.archived',
                [],
                ['source_type' => $input['source_type'] ?? 'web']
            );
        }

        return true;
    }

    public function addTasks(string $cyclePublicId, array $input, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        if (in_array((string)$cycle['status'], ['completed', 'archived'], true)) {
            return 'CYCLE_INVALID_STATUS_TRANSITION';
        }

        $cycleId = (int)$cycle['id'];
        $cycleProjectId = (int)$cycle['project_id'];
        $actorUserId = (int)($actor['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $taskPublicIds = (array)($input['task_public_ids'] ?? []);
        $taskKeys = (array)($input['task_keys'] ?? []);

        // Collect task public IDs
        $allPublicIds = [];
        foreach ($taskPublicIds as $pid) {
            $pid = trim((string)$pid);
            if ($pid !== '') {
                $allPublicIds[] = $pid;
            }
        }

        // If task_keys provided, resolve them via task service (TZ 002)
        if ($taskKeys !== [] && method_exists($this->taskService, 'resolveKeys')) {
            $resolved = $this->taskService->resolveKeys($taskKeys);
            if (is_array($resolved)) {
                foreach ($resolved as $task) {
                    $allPublicIds[] = (string)$task['public_id'];
                }
            }
        }

        if ($allPublicIds === []) {
            return 'CYCLE_TASK_TARGET_REQUIRED';
        }

        // Max 100 tasks per request
        $allPublicIds = array_slice(array_unique($allPublicIds), 0, 100);

        $added = [];
        $errors = [];

        foreach ($allPublicIds as $taskPublicId) {
            $taskId = $this->cycleTasks->taskIdByPublicId($taskPublicId);
            if ($taskId === null) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'CYCLE_TASK_NOT_FOUND'];
                continue;
            }

            // Check project match
            $task = $this->tasks->findByPublicId($taskPublicId);
            if (!$task) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'CYCLE_TASK_NOT_FOUND'];
                continue;
            }

            $taskProjectId = (int)($task['project_id'] ?? 0);
            if ($taskProjectId > 0 && $taskProjectId !== $cycleProjectId) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'CYCLE_TASK_PROJECT_MISMATCH'];
                continue;
            }

            // Check if task already in cycle
            $existing = $this->cycleTasks->taskAlreadyInActiveCycle($taskId);
            if ($existing !== null) {
                $errors[] = ['task_public_id' => $taskPublicId, 'error' => 'CYCLE_TASK_ALREADY_IN_ACTIVE_CYCLE', 'cycle_public_id' => $existing['cycle_public_id']];
                continue;
            }

            $cycleTaskPublicId = Ulid::generate('ctl');
            $activeKey = 'task:' . $taskId;

            $this->cycleTasks->addTask([
                'public_id' => $cycleTaskPublicId,
                'cycle_id' => $cycleId,
                'task_id' => $taskId,
                'active_key' => $activeKey,
                'added_by_user_id' => $actorUserId,
                'added_at' => $now,
                'sort_order' => 65535,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $added[] = $taskPublicId;

            // Activity
            if ($this->activity !== null && $task) {
                $this->activity->recordRelationEvent(
                    $task,
                    'task.added_to_cycle',
                    [
                        'type' => 'cycle',
                        'cycle_public_id' => $cyclePublicId,
                        'cycle_title' => (string)$cycle['title'],
                        'relation_public_id' => $cycleTaskPublicId,
                    ],
                    $actor,
                    ['source_type' => $input['source_type'] ?? 'web']
                );
            }
        }

        if ($added === []) {
            return $errors;
        }

        return [
            'added' => $added,
            'errors' => $errors,
        ];
    }

    public function removeTask(string $cyclePublicId, string $taskPublicId, array $actor): bool|string
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        $cycleId = (int)$cycle['id'];
        $taskId = $this->cycleTasks->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return 'CYCLE_TASK_NOT_FOUND';
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $removed = $this->cycleTasks->removeTask($cycleId, $taskId, $actorUserId, $now);

        // Activity
        if ($removed && $this->activity !== null) {
            $task = $this->tasks->findByPublicId($taskPublicId);
            if ($task) {
                $this->activity->recordRelationEvent(
                    $task,
                    'task.removed_from_cycle',
                    [
                        'type' => 'cycle',
                        'cycle_public_id' => $cyclePublicId,
                        'cycle_title' => (string)$cycle['title'],
                    ],
                    $actor,
                    ['source_type' => 'web']
                );
            }
        }

        return $removed;
    }

    public function tasks(string $cyclePublicId, array $filters, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        if (!$this->canViewCycle($cycle, $actor)) {
            return 'CYCLE_FORBIDDEN';
        }

        return $this->cycleTasks->listTasksByCycleId((int)$cycle['id'], $filters);
    }

    public function summary(string $cyclePublicId, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        if (!$this->canViewCycle($cycle, $actor)) {
            return 'CYCLE_FORBIDDEN';
        }

        $summary = $this->cycleTasks->cycleSummary((int)$cycle['id']);

        // Get time state
        $summary['time_state'] = $this->computeTimeState($cycle);

        return ['summary' => $summary];
    }

    private function canViewCycle(array $cycle, array $actor): bool
    {
        if ($this->isCycleAdmin($actor)) {
            return true;
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        if ($actorUserId <= 0) {
            return false;
        }

        if ((int)($cycle['created_by_user_id'] ?? 0) === $actorUserId) {
            return true;
        }

        if ((int)($cycle['owner_user_id'] ?? 0) === $actorUserId) {
            return true;
        }

        return $this->cycles->hasAssigneeInCycle((int)($cycle['id'] ?? 0), $actorUserId);
    }

    private function isCycleAdmin(array $actor): bool
    {
        if (!empty($actor['is_root'])) {
            return true;
        }

        $roles = array_map(
            static fn($role): string => strtolower(trim((string)$role)),
            is_array($actor['roles'] ?? null) ? (array)$actor['roles'] : []
        );
        if (array_intersect($roles, ['admin', 'administrator', 'super_admin', 'root']) !== []) {
            return true;
        }

        $permissions = array_map(
            static fn($code): string => strtolower(trim((string)$code)),
            is_array($actor['permission_codes'] ?? null) ? (array)$actor['permission_codes'] : []
        );
        return in_array('*', $permissions, true);
    }

    public function transferUnfinished(string $cyclePublicId, array $input, array $actor): array|string|null
    {
        $cycle = $this->cycles->findByPublicId($cyclePublicId);
        if (!$cycle) {
            return 'CYCLE_NOT_FOUND';
        }

        $project = $this->projects->get((string)$cycle['project_public_id'], $actor);
        if (!$project) {
            return 'CYCLE_FORBIDDEN';
        }

        $targetCyclePublicId = (string)($input['target_cycle_public_id'] ?? '');
        if ($targetCyclePublicId === '') {
            return 'CYCLE_TARGET_CYCLE_REQUIRED';
        }

        $targetCycle = $this->cycles->findByPublicId($targetCyclePublicId);
        if (!$targetCycle) {
            return 'CYCLE_TARGET_CYCLE_NOT_FOUND';
        }

        if (in_array((string)$targetCycle['status'], ['completed', 'archived'], true)) {
            return 'CYCLE_TARGET_CYCLE_INVALID';
        }

        $actorUserId = (int)($actor['id'] ?? 0);
        $cycleId = (int)$cycle['id'];
        $targetCycleId = (int)$targetCycle['id'];

        $transferred = $this->cycleTasks->moveUnfinishedTasks($cycleId, $targetCycleId, $actorUserId);

        // Record individual task.moved_to_cycle events
        if ($this->activity !== null) {
            foreach ($transferred as $taskPublicId) {
                $movedTask = $this->tasks->findByPublicId((string)$taskPublicId);
                if ($movedTask) {
                    $this->activity->recordRelationEvent(
                        $movedTask,
                        'task.moved_to_cycle',
                        [
                            'type' => 'cycle',
                            'source_cycle_public_id' => $cyclePublicId,
                            'source_cycle_title' => (string)$cycle['title'],
                            'target_cycle_public_id' => $targetCyclePublicId,
                            'target_cycle_title' => (string)$targetCycle['title'],
                        ],
                        $actor,
                        ['source_type' => 'web']
                    );
                }
            }

            $this->activity->recordSystemEvent(
                $cycle,
                'cycle.unfinished_transferred',
                [
                    'target_cycle_public_id' => $targetCyclePublicId,
                    'transferred_count' => count($transferred),
                    'transferred_tasks' => $transferred,
                ],
                ['source_type' => 'web']
            );
        }

        return [
            'transferred' => $transferred,
            'count' => count($transferred),
        ];
    }

    // ----- Private helpers -----

    private function enrichCycle(array $cycle): array
    {
        $cycle['time_state'] = $this->computeTimeState($cycle);

        $total = max(0, (int)($cycle['tasks_count'] ?? 0));
        $completed = max(0, (int)($cycle['completed_tasks_count'] ?? 0));
        $cycle['progress_percent'] = $total > 0 ? (int)round(($completed / $total) * 100) : 0;

        // Clean up internal fields
        unset($cycle['id']);
        unset($cycle['project_id']);
        unset($cycle['owner_user_id']);
        unset($cycle['created_by_user_id']);
        unset($cycle['completed_by_user_id']);

        return $cycle;
    }

    private function computeTimeState(array $cycle): string
    {
        $now = time();
        $startAt = trim((string)($cycle['start_at'] ?? ''));
        $endAt = trim((string)($cycle['end_at'] ?? ''));
        $start = $startAt !== '' ? strtotime($startAt) : null;
        $end = $endAt !== '' ? strtotime($endAt) : null;

        if ($start !== null && $start !== false && $start > $now) {
            return 'not_started';
        }
        if ($end !== null && $end !== false && $end < $now) {
            return 'ended';
        }
        return 'running';
    }
}
