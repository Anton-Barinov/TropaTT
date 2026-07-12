<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class AiContextBuilder
{
    public function __construct(
        private readonly TaskAiContextBuilder $task,
        private readonly ProjectAiContextBuilder $project,
        private readonly ClientAiContextBuilder $client,
        private readonly CalendarAiContextBuilder $calendar,
        private readonly DashboardAiContextBuilder $dashboard,
        private readonly AdminAiContextBuilder $admin,
        private readonly ImportAiContextBuilder $import,
        private readonly SecurityAiContextBuilder $security
    ) {
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildTaskSummaryContext(array $task, array $input, array $actor): array
    {
        return $this->task->buildSummaryContext($task, $input, $actor);
    }

    /**
     * @param array<string,mixed> $task
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildFullTaskContext(array $task, array $input, array $actor): array
    {
        return $this->task->buildFullTaskContext($task, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildProjectSummaryContext(string $projectPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($projectPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->project->buildSummaryContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildClientSummaryContext(string $clientPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($clientPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->client->buildSummaryContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildClientMeetingPrepContext(string $clientPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($clientPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->client->buildMeetingPrepContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildClientDataQualityContext(string $clientPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($clientPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->client->buildDataQualityContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildClientSafeReportContext(string $clientPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($clientPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->client->buildClientSafeReportContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{agenda:array<string,mixed>,candidate_tasks:array<int,array<string,mixed>>,date:string}
     */
    public function buildMyDayPlanContext(array $input, array $actor): array
    {
        return $this->calendar->buildMyDayContext($input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array{agenda:array<string,mixed>,candidate_tasks:array<int,array<string,mixed>>,date:string}
     */
    public function buildMyWeekPlanContext(array $input, array $actor): array
    {
        return $this->calendar->buildMyWeekContext($input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildCalendarEventAgendaContext(string $eventPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($eventPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->calendar->buildEventAgendaContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildDashboardDigestContext(array $input, array $actor): array
    {
        return $this->dashboard->buildDigestContext($input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildAnalyticsOverviewContext(array $input, array $actor): array
    {
        return $this->dashboard->buildAnalyticsOverviewContext($input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildAdminLogReviewContext(array $input): array
    {
        return $this->admin->buildLogReviewContext($input);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildWebhookHealthContext(array $input): array
    {
        return $this->admin->buildWebhookHealthContext($input);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildWorkflowRuleAuditContext(array $input, array $actor): array
    {
        return $this->admin->buildWorkflowRuleAuditContext($input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>|null
     */
    public function buildImportReviewContext(string $importJobPublicId, array $input, array $actor): ?array
    {
        $normalizedPublicId = $this->normalizeEntityPublicId($importJobPublicId);
        if ($normalizedPublicId === null) {
            return null;
        }

        return $this->import->buildReviewContext($normalizedPublicId, $input, $actor);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function buildSecurityReviewContext(array $input): array
    {
        return $this->security->buildSecurityReviewContext($input);
    }

    private function normalizeEntityPublicId(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_]{2,127}$/', $value)) {
            return null;
        }

        return $value;
    }
}
