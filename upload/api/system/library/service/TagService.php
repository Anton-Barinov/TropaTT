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

    public function list(array $filters, array $actor = []): array
    {
        $organizationId = $this->organizationId($actor);
        if ($organizationId !== null) {
            $filters['organization_id'] = $organizationId;
        }
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

    public function get(string $publicId, array $actor = []): ?array
    {
        return $this->tags->findByPublicId($publicId, $this->organizationId($actor));
    }

    public function create(array $input, array $actor = [])
    {
        $organizationId = $this->organizationId($actor);
        $code = trim((string)$input['code']);
        if ($this->tags->findByCode($code, $organizationId)) {
            return 'TAG_CODE_EXISTS';
        }

        $publicId = Ulid::generate('tag');

        $title = trim((string)($input['title'] ?? $input['name'] ?? ''));

        $this->tags->create([
            'public_id' => $publicId,
            'code' => $code,
            'title' => $title,
            'color' => (string)($input['color'] ?? '#64748b'),
            'description' => (string)($input['description'] ?? ''),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ], $organizationId);

        return $this->tags->findByPublicId($publicId, $organizationId) ?: ['public_id' => $publicId];
    }

    public function update(string $publicId, array $input, array $actor = [])
    {
        $organizationId = $this->organizationId($actor);
        $current = $this->tags->findByPublicId($publicId, $organizationId);
        if (!$current) {
            return null;
        }

        $set = [];
        if (array_key_exists('code', $input)) {
            $newCode = trim((string)$input['code']);
            if ($newCode !== (string)$current['code'] && $this->tags->findByCode($newCode, $organizationId)) {
                return 'TAG_CODE_EXISTS';
            }
            $set['code'] = $newCode;
        }

        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        } elseif (array_key_exists('name', $input)) {
            $set['title'] = trim((string)$input['name']);
        }

        if (array_key_exists('color', $input)) {
            $set['color'] = (string)$input['color'];
        }

        if (array_key_exists('description', $input)) {
            $set['description'] = (string)$input['description'];
        }

        $this->tags->updateByPublicId($publicId, $set, $organizationId);

        return $this->tags->findByPublicId($publicId, $organizationId);
    }

    public function delete(string $publicId, array $actor = []): bool
    {
        return $this->tags->deleteByPublicId($publicId, $this->organizationId($actor));
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

        $tag = $this->tags->findByPublicId($tagPublicId, $this->organizationId($actor));
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

        $tag = $this->tags->findByPublicId($tagPublicId, $this->organizationId($actor));
        if (!$tag) {
            return false;
        }

        return $this->tags->detachFromEntity('task', $taskPublicId, (int)$tag['id']);
    }

    private function organizationId(array $actor): ?int
    {
        $id = (int)($actor['organization_id'] ?? 0);
        return $id > 0 ? $id : null;
    }
}
