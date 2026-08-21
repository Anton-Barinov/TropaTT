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
        $workload = $this->summary->workloadSummary($projectPublicId, $now);

        // SEC: an external guest (client portal observer/executor) must never see
        // per-employee workload data — assignee_name/assignee_user_public_id and
        // individual task counts are internal team information, not something the
        // client portal's own UI even renders for guests (the "Команда" sidebar
        // block is stripped from the template for is_external_user). This route
        // is external_ok (routes.php) so the guest's browser DOES receive this
        // JSON — the per-person breakdown must be dropped here, not just hidden
        // in the UI, or a guest could read it straight out of the network tab.
        // The aggregate totals (task counts, logged minutes) are safe: they carry
        // no staff identity and mirror data already visible on the guest's own
        // task list.
        if ((int)($actor['is_external'] ?? 0) === 1) {
            $workload = ['items' => [], 'totals' => $workload['totals'] ?? []];
        }

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
            'workload' => $workload,
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
