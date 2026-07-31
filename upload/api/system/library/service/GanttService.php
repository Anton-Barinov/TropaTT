<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Milestone\MilestoneRepository;
use Api\Model\Dependency\DependencyRepository;
use Api\Model\Task\TaskRepository;

final class GanttService
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly TaskRepository $tasks,
        private readonly MilestoneRepository $milestones,
        private readonly DependencyRepository $dependencies
    ) {
    }

    public function timeline(string $projectPublicId, array $filters, array $actor): array|string
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        $tasks = $this->tasks->boardItems(
            [
                'project_public_id' => $projectPublicId,
                'archived' => (string)($filters['archived'] ?? '0'),
                'search' => (string)($filters['search'] ?? ''),
                'status' => (string)($filters['status'] ?? ''),
                'priority' => (string)($filters['priority'] ?? ''),
                'sort' => (string)($filters['sort'] ?? 'due_at'),
                'order' => (string)($filters['order'] ?? 'ASC'),
            ],
            (int)($actor['id'] ?? 0),
            (bool)($actor['is_root'] ?? false),
            min(2000, max(1, (int)($filters['limit'] ?? 1000)))
        );

        $dependencies = $this->dependencies->list(['project_public_id' => $projectPublicId]);
        $milestones = $this->milestones->listByProjectPublicId($projectPublicId);

        return [
            'project' => [
                'public_id' => $project['public_id'],
                'title' => $project['title'],
                'status_code' => $project['status_code'] ?? null,
                'priority_code' => $project['priority_code'] ?? null,
            ],
            'tasks' => array_map(static function (array $task): array {
                return [
                    'public_id' => (string)$task['public_id'],
                    'title' => (string)$task['title'],
                    'status_code' => (string)($task['status_code'] ?? 'new'),
                    'priority_code' => (string)($task['priority_code'] ?? 'normal'),
                    'start_at' => $task['start_at'] ?? null,
                    'due_at' => $task['due_at'] ?? null,
                    'end_at' => $task['end_at'] ?? null,
                    'updated_at' => $task['updated_at'] ?? null,
                    'row_version' => (int)($task['row_version'] ?? 1),
                    'assignee_user_public_id' => $task['assignee_user_public_id'] ?? null,
                    'assignee_name' => $task['assignee_name'] ?? null,
                ];
            }, $tasks),
            'dependencies' => array_map(static function (array $dep): array {
                return [
                    'public_id' => (string)$dep['public_id'],
                    'dependency_type' => (string)($dep['dependency_type'] ?? 'FS'),
                    'task_public_id' => (string)$dep['task_public_id'],
                    'depends_on_task_public_id' => (string)$dep['depends_on_task_public_id'],
                    'created_at' => $dep['created_at'] ?? null,
                ];
            }, $dependencies),
            'milestones' => array_map(static function (array $milestone): array {
                return [
                    'public_id' => (string)$milestone['public_id'],
                    'title' => (string)$milestone['title'],
                    'due_at' => $milestone['due_at'] ?? null,
                    'status' => (string)($milestone['status'] ?? 'planned'),
                ];
            }, $milestones),
        ];
    }
}
