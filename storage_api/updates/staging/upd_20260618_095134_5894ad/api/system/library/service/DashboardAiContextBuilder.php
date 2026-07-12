<?php
declare(strict_types=1);

namespace Api\System\Library\Service;

final class DashboardAiContextBuilder
{
    public function __construct(
        private readonly DashboardService $dashboard,
        private readonly AnalyticsService $analytics,
        private readonly AiMaskingService $masking
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildDigestContext(array $input, array $actor): array
    {
        $prompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));

        return [
            'dashboard' => $this->dashboard->summary($actor),
            'analytics' => $this->analytics->summary($actor),
            'prompt' => $this->masking->maskSensitiveText($prompt),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed> $actor
     * @return array<string,mixed>
     */
    public function buildAnalyticsOverviewContext(array $input, array $actor): array
    {
        $prompt = trim((string)($input['prompt'] ?? $input['input_text'] ?? ''));
        $period = trim((string)($input['period'] ?? 'week'));
        if ($period === '') {
            $period = 'week';
        }

        return [
            'period' => $period,
            'analytics' => $this->analytics->summary($actor),
            'projects' => $this->analytics->projects($actor, ['limit' => 30]),
            'users' => $this->analytics->users($actor, ['limit' => 30]),
            'prompt' => $this->masking->maskSensitiveText($prompt),
        ];
    }
}
