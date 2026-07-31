<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class ProjectAiContextBuilder
{
    public function __construct(
        private readonly ProjectService $projects,
        private readonly ProjectSummaryService $projectSummary,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildSummaryContext(string $projectPublicId, array $input, array $actor): ?array
    {
        $project = $this->projects->get($projectPublicId, $actor);
        if (!$project) {
            return null;
        }
        $summary = $this->projectSummary->summary($projectPublicId, $actor);
        $summaryData = is_array($summary) ? $summary : [];
        $milestones = is_array($summaryData['milestones'] ?? null) ? (array)$summaryData['milestones'] : [];
        $risks = is_array($summaryData['risks'] ?? null) ? (array)$summaryData['risks'] : [];
        $workload = is_array($summaryData['workload'] ?? null) ? (array)$summaryData['workload'] : [];
        $workloadTotals = is_array($workload['totals'] ?? null) ? (array)$workload['totals'] : [];

        $inputPrompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));

        return [
            'project_public_id' => (string)($project['public_id'] ?? ''),
            'title' => trim((string)($project['title'] ?? '')),
            'description' => $this->masking->maskSensitiveText(trim((string)($project['description'] ?? ''))),
            'status' => trim((string)($project['status_code'] ?? '')),
            'priority' => trim((string)($project['priority_code'] ?? '')),
            'client_public_id' => (string)($project['client_public_id'] ?? ''),
            'manager_user_public_id' => (string)($project['manager_user_public_id'] ?? ''),
            'evidence' => [
                'overdue_tasks' => (int)($risks['overdue_tasks'] ?? 0),
                'blocked_tasks' => (int)($risks['blocked_tasks'] ?? 0),
                'total_tasks' => (int)($workloadTotals['total_tasks'] ?? 0),
                'done_tasks' => (int)($workloadTotals['done_tasks'] ?? 0),
                'logged_minutes' => (int)($workloadTotals['logged_minutes'] ?? 0),
                'milestones_total' => (int)($milestones['total'] ?? 0),
                'milestones_done' => (int)($milestones['done'] ?? 0),
                'milestones_overdue' => (int)($milestones['overdue'] ?? 0),
                'milestones_upcoming_7_days' => (int)($milestones['upcoming_7_days'] ?? 0),
                'generated_at' => (string)($summaryData['generated_at'] ?? ''),
            ],
            'prompt' => $this->masking->maskSensitiveText($inputPrompt),
        ];
    }
}
