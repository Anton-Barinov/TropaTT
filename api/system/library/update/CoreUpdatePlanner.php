<?php
declare(strict_types=1);

namespace Api\System\Library\Update;

final class CoreUpdatePlanner
{
    public function __construct(private readonly CoreUpdateClient $client, private readonly CoreVersion $version)
    {
    }

    public function check(): array
    {
        $current = $this->version->current();
        $currentBuild = (string)($current['core_build'] ?? '0');
        if ($currentBuild === '') {
            $currentBuild = '0';
        }
        $plan = $this->client->plan($currentBuild);
        if (($plan['ok'] ?? false) !== true) {
            return [
                'current' => $current,
                'plan' => [
                    'update_available' => null,
                    'current_build' => $currentBuild,
                    'target_build' => null,
                    'recommended_package' => null,
                    'summary' => null,
                    'update_center' => $this->updateCenterError($plan),
                    'error' => (string)($plan['error'] ?? 'update_center_unavailable'),
                    'message' => (string)($plan['message'] ?? 'Update center is unavailable'),
                ],
                'raw' => $plan,
                'unknown_local_core' => ($current['state'] ?? '') === 'unknown_local_core',
            ];
        }
        return [
            'current' => $current,
            'plan' => $plan['data'] ?? null,
            'raw' => $plan,
            'unknown_local_core' => ($current['state'] ?? '') === 'unknown_local_core',
        ];
    }

    private function updateCenterError(array $response): array
    {
        return [
            'url' => (string)($response['url'] ?? ''),
            'ok' => false,
            'status' => (int)($response['status'] ?? 0),
            'error' => (string)($response['error'] ?? 'update_center_unavailable'),
            'message' => (string)($response['message'] ?? 'Update center is unavailable'),
        ];
    }
}
