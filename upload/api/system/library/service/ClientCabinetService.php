<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Client\ClientCabinetRepository;

final class ClientCabinetService
{
    public function __construct(private readonly ClientCabinetRepository $repository)
    {
    }

    public function listProjects(string $clientPublicId, array $filters): array
    {
        [$items, $total, $page, $limit] = $this->repository->listProjects($clientPublicId, $filters);

        return [
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
                'client_public_id' => $clientPublicId,
            ],
        ];
    }

    public function getProject(string $clientPublicId, string $projectPublicId): array|string|null
    {
        $project = $this->repository->findProjectByPublicId($projectPublicId);
        if (!$project) {
            return null;
        }

        if ((string)($project['client_public_id'] ?? '') !== $clientPublicId) {
            return 'FORBIDDEN_CLIENT_SCOPE';
        }

        return $project;
    }

    public function listProjectTasks(string $clientPublicId, string $projectPublicId, array $filters): array|string
    {
        $project = $this->repository->findProjectByPublicId($projectPublicId);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        if ((string)($project['client_public_id'] ?? '') !== $clientPublicId) {
            return 'FORBIDDEN_CLIENT_SCOPE';
        }

        [$items, $total, $page, $limit] = $this->repository->listProjectTasks($projectPublicId, $filters);

        return [
            'project' => $project,
            'items' => $items,
            'meta' => [
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / max(1, $limit)),
                ],
                'client_public_id' => $clientPublicId,
            ],
        ];
    }
}
