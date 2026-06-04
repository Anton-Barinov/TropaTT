<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Common\UserRepository;
use Api\Model\Tag\TagRepository;

final class TaskBulkService
{
    public function __construct(
        private readonly TaskService $tasks,
        private readonly TagRepository $tags,
        private readonly UserRepository $users
    ) {
    }

    public function apply(array $payload, array $actor): array|string
    {
        $taskPublicIds = array_values(array_unique(array_filter(
            array_map(static fn($id): string => trim((string)$id), (array)($payload['task_public_ids'] ?? [])),
            static fn(string $id): bool => $id !== ''
        )));

        if ($taskPublicIds === []) {
            return 'TASK_IDS_REQUIRED';
        }

        $changes = (array)($payload['changes'] ?? []);

        $assigneeUserId = null;
        if (array_key_exists('assignee_user_public_id', $changes)) {
            $assigneePublicId = trim((string)$changes['assignee_user_public_id']);
            if ($assigneePublicId !== '') {
                $assignee = $this->users->findByPublicId($assigneePublicId);
                if (!$assignee || (int)($assignee['is_active'] ?? 0) !== 1) {
                    return 'ASSIGNEE_NOT_FOUND';
                }
                $assigneeUserId = (int)$assignee['id'];
            }
        }

        $addTagIds = $this->resolveTagIds((array)($changes['add_tag_public_ids'] ?? []));
        $removeTagIds = $this->resolveTagIds((array)($changes['remove_tag_public_ids'] ?? []));

        $updated = [];
        $skipped = [];

        foreach ($taskPublicIds as $taskPublicId) {
            $task = $this->tasks->get($taskPublicId, $actor);
            if (!$task) {
                $skipped[] = [
                    'task_public_id' => $taskPublicId,
                    'reason' => 'TASK_NOT_FOUND_OR_FORBIDDEN',
                ];
                continue;
            }

            $updateInput = [];
            if (array_key_exists('status', $changes)) {
                $updateInput['status'] = (string)$changes['status'];
            }
            if (array_key_exists('priority', $changes)) {
                $updateInput['priority'] = (string)$changes['priority'];
            }
            if (array_key_exists('archived', $changes)) {
                $updateInput['archived'] = (bool)$changes['archived'];
            }
            if (array_key_exists('assignee_user_public_id', $changes)) {
                $updateInput['assignee_user_id'] = $assigneeUserId;
            }

            if ($updateInput !== []) {
                $result = $this->tasks->update($taskPublicId, $updateInput, (int)($actor['id'] ?? 0), $actor);
                if (!$result) {
                    $skipped[] = [
                        'task_public_id' => $taskPublicId,
                        'reason' => 'TASK_UPDATE_FAILED',
                    ];
                    continue;
                }
            }

            foreach ($addTagIds as $tagId) {
                $this->tags->assignToEntity('task', $taskPublicId, $tagId);
            }
            foreach ($removeTagIds as $tagId) {
                $this->tags->detachFromEntity('task', $taskPublicId, $tagId);
            }

            $updated[] = [
                'task_public_id' => $taskPublicId,
                'status' => 'updated',
            ];
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'summary' => [
                'requested' => count($taskPublicIds),
                'updated' => count($updated),
                'skipped' => count($skipped),
            ],
        ];
    }

    /** @return int[] */
    private function resolveTagIds(array $publicIds): array
    {
        $ids = [];
        foreach ($publicIds as $publicIdRaw) {
            $publicId = trim((string)$publicIdRaw);
            if ($publicId === '') {
                continue;
            }

            $tag = $this->tags->findByPublicId($publicId);
            if ($tag) {
                $ids[] = (int)$tag['id'];
            }
        }

        return array_values(array_unique($ids));
    }
}
