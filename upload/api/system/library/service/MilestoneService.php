<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Milestone\MilestoneRepository;

final class MilestoneService
{
    public function __construct(
        private readonly MilestoneRepository $milestones,
        private readonly ProjectService $projects
    ) {
    }

    public function list(string $projectPublicId, array $actor): array|string
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        return $this->milestones->listByProjectPublicId($projectPublicId);
    }

    public function listByProjectIds(array $projectPublicIds, array $actor): array
    {
        $grouped = [];
        $items = $this->milestones->listByProjectPublicIds($projectPublicIds);
        foreach ($items as $item) {
            $projectPub = (string)$item['project_public_id'];
            if (!isset($grouped[$projectPub])) {
                $grouped[$projectPub] = [];
            }
            $grouped[$projectPub][] = $item;
        }
        return $grouped;
    }

    public function get(string $publicId, array $actor): array|null
    {
        $item = $this->milestones->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $project = $this->projects->get((string)$item['project_public_id'], $actor);
        if (!$project) {
            return null;
        }

        return $item;
    }

    public function create(array $input, array $actor): array|string
    {
        $projectPublicId = trim((string)($input['project_public_id'] ?? ''));
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        return $this->milestones->create(
            $projectPublicId,
            trim((string)($input['title'] ?? '')),
            !empty($input['due_at']) ? (string)$input['due_at'] : null,
            trim((string)($input['status'] ?? 'planned'))
        );
    }

    public function update(string $publicId, array $input, array $actor): array|string|null
    {
        $item = $this->milestones->findByPublicId($publicId);
        if (!$item) {
            return null;
        }

        $project = $this->projects->get((string)$item['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        $set = [];
        if (array_key_exists('title', $input)) {
            $set['title'] = trim((string)$input['title']);
        }
        if (array_key_exists('due_at', $input)) {
            $set['due_at'] = $input['due_at'] !== '' ? (string)$input['due_at'] : null;
        }
        if (array_key_exists('status', $input)) {
            $set['status'] = trim((string)$input['status']);
        }

        if ($set !== []) {
            $this->milestones->updateByPublicId($publicId, $set);
        }

        return $this->milestones->findByPublicId($publicId);
    }

    public function delete(string $publicId, array $actor): bool|string
    {
        $item = $this->milestones->findByPublicId($publicId);
        if (!$item) {
            return false;
        }

        $project = $this->projects->get((string)$item['project_public_id'], $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        return $this->milestones->deleteByPublicId($publicId);
    }
}
