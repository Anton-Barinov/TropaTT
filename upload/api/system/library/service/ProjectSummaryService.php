<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

use Api\Model\Project\ProjectSummaryRepository;

final class ProjectSummaryService
{
    public function __construct(
        private readonly ProjectSummaryRepository $summary,
        private readonly ProjectService $projects
    ) {
    }

    public function summary(string $projectPublicId, array $actor): array|string
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        $now = gmdate('Y-m-d H:i:s');

        return [
            'project' => [
                'public_id' => (string)$project['public_id'],
                'title' => (string)($project['title'] ?? ''),
                'status_code' => (string)($project['status_code'] ?? ''),
                'priority_code' => (string)($project['priority_code'] ?? ''),
                'team_public_id' => (string)($project['team_public_id'] ?? ''),
                'team_title' => (string)($project['team_title'] ?? ''),
                'row_version' => (int)($project['row_version'] ?? 1),
            ],
            'milestones' => $this->summary->milestonesSummary($projectPublicId, $now),
            'risks' => $this->summary->risksSummary($projectPublicId, $now),
            'workload' => $this->summary->workloadSummary($projectPublicId, $now),
            'generated_at' => gmdate('c'),
        ];
    }

    public function milestones(string $projectPublicId, array $actor): array|string
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        return $this->summary->milestonesSummary($projectPublicId, gmdate('Y-m-d H:i:s'));
    }

    public function risks(string $projectPublicId, array $actor): array|string
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        return $this->summary->risksSummary($projectPublicId, gmdate('Y-m-d H:i:s'));
    }

    public function workload(string $projectPublicId, array $actor): array|string
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return 'PROJECT_NOT_FOUND';
        }

        return $this->summary->workloadSummary($projectPublicId, gmdate('Y-m-d H:i:s'));
    }
}
