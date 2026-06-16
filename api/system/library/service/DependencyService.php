<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Dependency\DependencyRepository;

final class DependencyService
{
    public function __construct(
        private readonly DependencyRepository $dependencies,
        private readonly TaskService $tasks,
        private readonly ?TaskActivityService $activity = null
    ) {
    }

    public function list(array $filters, array $actor): array
    {
        $items = $this->dependencies->list($filters);
        $result = [];

        foreach ($items as $item) {
            $taskPublicId = (string)($item['task_public_id'] ?? '');
            $dependsOnTaskPublicId = (string)($item['depends_on_task_public_id'] ?? '');

            $task = $this->tasks->get($taskPublicId, $actor);
            $dependsOn = $this->tasks->get($dependsOnTaskPublicId, $actor);
            if (!$task || !$dependsOn) {
                continue;
            }

            $result[] = $item;
        }

        return $result;
    }

    public function create(array $input, array $actor): array|string
    {
        $taskPublicId = trim((string)($input['task_public_id'] ?? ''));
        $dependsOnTaskPublicId = trim((string)($input['depends_on_task_public_id'] ?? ''));

        $task = $this->tasks->get($taskPublicId, $actor);
        $dependsOn = $this->tasks->get($dependsOnTaskPublicId, $actor);
        if (!$task || !$dependsOn) {
            return 'TASK_NOT_FOUND';
        }

        $type = strtoupper(trim((string)($input['dependency_type'] ?? 'FS')));
        if (!in_array($type, ['FS', 'SS', 'FF', 'SF', 'BLOCKS'], true)) {
            return 'INVALID_DEPENDENCY_TYPE';
        }

        $created = $this->dependencies->create($taskPublicId, $dependsOnTaskPublicId, $type);
        if (is_array($created) && $task) {
            $this->activity?->recordDependencyEvent($task, 'task.dependency_added', [
                'dependency_public_id' => (string)($created['public_id'] ?? ''),
                'type' => $type,
                'task_public_id' => $taskPublicId,
                'depends_on_task_public_id' => $dependsOnTaskPublicId,
            ], $actor);
        }
        return $created;
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $item = $this->dependencies->findByPublicId($publicId);
        if (!$item) {
            return false;
        }

        $task = $this->tasks->get((string)$item['task_public_id'], $actor);
        $dependsOn = $this->tasks->get((string)$item['depends_on_task_public_id'], $actor);
        if (!$task || !$dependsOn) {
            return 'TASK_NOT_FOUND';
        }

        $deleted = $this->dependencies->deleteByPublicId($publicId);
        if ($deleted && $task) {
            $this->activity?->recordDependencyEvent($task, 'task.dependency_deleted', [
                'dependency_public_id' => $publicId,
                'type' => (string)($item['dependency_type'] ?? ''),
                'task_public_id' => (string)($item['task_public_id'] ?? ''),
                'depends_on_task_public_id' => (string)($item['depends_on_task_public_id'] ?? ''),
            ], $actor);
        }
        return $deleted;
    }
}
