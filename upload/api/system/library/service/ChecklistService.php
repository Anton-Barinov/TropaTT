<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Checklist\ChecklistRepository;
use Api\System\Library\Support\Ulid;

final class ChecklistService
{
    public function __construct(
        private readonly ChecklistRepository $checklists,
        private readonly TaskService $tasks,
        private readonly ?TaskActivityService $activity = null
    ) {
    }

    public function listByTask(string $taskPublicId, array $actor): ?array
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        return $this->checklists->listByTaskPublicId($taskPublicId);
    }

    public function create(string $taskPublicId, array $input, array $actor): ?array
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        $taskId = $this->checklists->taskIdByPublicId($taskPublicId);
        if ($taskId === null) {
            return null;
        }

        $publicId = Ulid::generate('chk');
        $now = gmdate('Y-m-d H:i:s');

        $this->checklists->create([
            'public_id' => $publicId,
            'task_id' => $taskId,
            'title' => trim((string)$input['title']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $created = $this->checklists->findByPublicId($publicId);
        if ($created && $task) {
            $this->activity?->recordChecklistEvent($task, 'task.checklist_created', [
                'checklist_public_id' => $publicId,
                'title' => trim((string)$input['title']),
            ], $actor);
        }

        return $created;
    }

    public function get(string $publicId, array $actor): ?array
    {
        $item = $this->checklists->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $task = $this->tasks->get((string)$item['task_public_id'], $actor);
        if (!$task) {
            return null;
        }

        return $item;
    }

    public function update(string $publicId, array $input, array $actor): ?array
    {
        $current = $this->checklists->findByPublicId($publicId);
        if (!$current) {
            return null;
        }

        $task = $this->tasks->get((string)$current['task_public_id'], $actor);
        if (!$task) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->checklists->updateByPublicId($publicId, $set);

        $updated = $this->checklists->findByPublicId($publicId);
        if ($updated && $task) {
            $this->activity?->recordChecklistEvent($task, 'task.checklist_updated', [
                'checklist_public_id' => $publicId,
                'title' => $updated['title'] ?? '',
            ], $actor);
        }

        return $updated;
    }

    public function delete(string $publicId, array $actor): bool
    {
        $current = $this->checklists->findByPublicId($publicId);
        if (!$current) {
            return false;
        }

        $task = $this->tasks->get((string)$current['task_public_id'], $actor);
        if (!$task) {
            return false;
        }

        $deleted = $this->checklists->deleteByPublicId($publicId);
        if ($deleted && $task) {
            $this->activity?->recordChecklistEvent($task, 'task.checklist_deleted', [
                'checklist_public_id' => $publicId,
                'title' => $current['title'] ?? '',
            ], $actor);
        }
        return $deleted;
    }

    public function listItems(string $checklistPublicId, array $actor): ?array
    {
        $checklist = $this->checklists->findByPublicId($checklistPublicId);
        if (!$checklist) {
            return null;
        }

        $task = $this->tasks->get((string)$checklist['task_public_id'], $actor);
        if (!$task) {
            return null;
        }

        return $this->checklists->listItemsByChecklistPublicId($checklistPublicId);
    }

    public function createItem(string $checklistPublicId, array $input, array $actor): ?array
    {
        $checklist = $this->checklists->findByPublicId($checklistPublicId);
        if (!$checklist) {
            return null;
        }

        $task = $this->tasks->get((string)$checklist['task_public_id'], $actor);
        if (!$task) {
            return null;
        }

        $checklistId = $this->checklists->checklistIdByPublicId($checklistPublicId);
        if ($checklistId === null) {
            return null;
        }

        $publicId = Ulid::generate('cki');
        $now = gmdate('Y-m-d H:i:s');

        $this->checklists->createItem([
            'public_id' => $publicId,
            'checklist_id' => $checklistId,
            'title' => trim((string)$input['title']),
            'is_done' => !empty($input['is_done']) ? 1 : 0,
            'sort_order' => isset($input['sort_order']) ? (int)$input['sort_order'] : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $createdItem = $this->checklists->findItemByPublicId($publicId);
        if ($createdItem && $task) {
            $this->activity?->recordChecklistEvent($task, 'task.checklist_item_created', [
                'checklist_public_id' => $checklistPublicId,
                'item_public_id' => $publicId,
                'item_title' => trim((string)$input['title']),
            ], $actor);
        }
        return $createdItem;
    }

    public function getItem(string $publicId, array $actor): ?array
    {
        $item = $this->checklists->findItemByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $task = $this->tasks->get((string)$item['task_public_id'], $actor);
        if (!$task) {
            return null;
        }

        return $item;
    }

    public function updateItem(string $publicId, array $input, array $actor): ?array
    {
        $item = $this->checklists->findItemByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $task = $this->tasks->get((string)$item['task_public_id'], $actor);
        if (!$task) {
            return null;
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('is_done', $input)) {
            $set['is_done'] = !empty($input['is_done']) ? 1 : 0;
        }
        if (array_key_exists('sort_order', $input)) {
            $set['sort_order'] = (int)$input['sort_order'];
        }

        $set['updated_at'] = gmdate('Y-m-d H:i:s');
        $this->checklists->updateItemByPublicId($publicId, $set);

        $updatedItem = $this->checklists->findItemByPublicId($publicId);
        if ($updatedItem && $task) {
            $wasDone = (int)($item['is_done'] ?? 0);
            $nowDone = (int)($updatedItem['is_done'] ?? 0);
            if ($wasDone !== $nowDone) {
                $eventType = $nowDone ? 'task.checklist_item_completed' : 'task.checklist_item_reopened';
                $this->activity?->recordChecklistEvent($task, $eventType, [
                    'checklist_public_id' => $item['checklist_public_id'] ?? '',
                    'item_public_id' => $publicId,
                    'item_title' => $updatedItem['title'] ?? '',
                ], $actor);
            } else {
                $this->activity?->recordChecklistEvent($task, 'task.checklist_item_updated', [
                    'checklist_public_id' => $item['checklist_public_id'] ?? '',
                    'item_public_id' => $publicId,
                    'item_title' => $updatedItem['title'] ?? '',
                ], $actor);
            }
        }
        return $updatedItem;
    }

    public function deleteItem(string $publicId, array $actor): bool
    {
        $item = $this->checklists->findItemByPublicId($publicId);
        if (!$item) {
            return false;
        }

        $task = $this->tasks->get((string)$item['task_public_id'], $actor);
        if (!$task) {
            return false;
        }

        $deleted = $this->checklists->deleteItemByPublicId($publicId);
        if ($deleted && $task) {
            $this->activity?->recordChecklistEvent($task, 'task.checklist_item_deleted', [
                'checklist_public_id' => $item['checklist_public_id'] ?? '',
                'item_public_id' => $publicId,
                'item_title' => $item['title'] ?? '',
            ], $actor);
        }
        return $deleted;
    }
}
