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
        return [
            'current' => $current,
            'plan' => $plan['data'] ?? null,
            'raw' => $plan,
            'unknown_local_core' => ($current['state'] ?? '') === 'unknown_local_core',
        ];
    }
}
