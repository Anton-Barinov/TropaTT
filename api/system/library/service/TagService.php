<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Tag\TagRepository;
use Api\System\Library\Support\Ulid;

final class TagService
{
    public function __construct(
        private readonly TagRepository $tags,
        private readonly TaskService $tasks
    ) {
    }

    public function list(array $filters): array
    {
        [$items, $total, $page, $limit] = $this->tags->list($filters);

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

    public function get(string $publicId): ?array
    {
        return $this->tags->findByPublicId($publicId);
    }

    public function create(array $input)
    {
        $code = trim((string)$input['code']);
        if ($this->tags->findByCode($code)) {
            return 'TAG_CODE_EXISTS';
        }

        $publicId = Ulid::generate('tag');

        $this->tags->create([
            'public_id' => $publicId,
            'code' => $code,
            'title' => trim((string)$input['title']),
            'color' => (string)($input['color'] ?? '#64748b'),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->tags->findByPublicId($publicId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input)
    {
        $current = $this->tags->findByPublicId($publicId);
        if (!$current) {
            return null;
        }

        $set = [];
        if (array_key_exists('code', $input)) {
            $newCode = trim((string)$input['code']);
            if ($newCode !== (string)$current['code'] && $this->tags->findByCode($newCode)) {
                return 'TAG_CODE_EXISTS';
            }
            $set['code'] = $newCode;
        }

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }

        if (array_key_exists('color', $input)) {
            $set['color'] = (string)$input['color'];
        }

        $this->tags->updateByPublicId($publicId, $set);

        return $this->tags->findByPublicId($publicId);
    }

    public function delete(string $publicId): bool
    {
        return $this->tags->deleteByPublicId($publicId);
    }

    public function listTaskTags(string $taskPublicId, array $actor): ?array
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return null;
        }

        return $this->tags->listByEntity('task', $taskPublicId);
    }

    public function attachToTask(string $taskPublicId, string $tagPublicId, array $actor): bool
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return false;
        }

        $tag = $this->tags->findByPublicId($tagPublicId);
        if (!$tag) {
            return false;
        }

        $this->tags->assignToEntity('task', $taskPublicId, (int)$tag['id']);

        return true;
    }

    public function detachFromTask(string $taskPublicId, string $tagPublicId, array $actor): bool
    {
        $task = $this->tasks->get($taskPublicId, $actor);
        if (!$task) {
            return false;
        }

        $tag = $this->tags->findByPublicId($tagPublicId);
        if (!$tag) {
            return false;
        }

        return $this->tags->detachFromEntity('task', $taskPublicId, (int)$tag['id']);
    }
}
